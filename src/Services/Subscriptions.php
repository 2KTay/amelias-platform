<?php

declare(strict_types=1);

namespace Amelias\Services;

use Amelias\Database\Database;
use RuntimeException;

/**
 * Wine Club subscriptions via Stripe Billing (Task 4.4).
 *
 * StripeGateway only creates one-time PaymentIntents, so recurring billing
 * lives here against the Stripe SDK's \Stripe\StripeClient->subscriptions API.
 * Every SDK touch is class_exists()-guarded: when the SDK or the tier's
 * stripe_price_id is missing, methods degrade gracefully (throw RuntimeException
 * with a human message the controller catches) rather than fatal.
 *
 * Stripe is the source of truth for billing state. The local `subscriptions`
 * row mirrors status from invoice/subscription webhooks; mirrorStatus() is the
 * only writer of lifecycle status from the Stripe side. pause()/cancel() call
 * Stripe and optimistically reflect locally (the webhook reconciles).
 *
 * Money is integer cents; period ends stored UTC.
 */
final class Subscriptions
{
    private ?object $client = null;

    public function __construct()
    {
        $secret = Settings::get('stripe.secret_key');
        if ($secret !== null && $secret !== '' && $this->sdkAvailable()) {
            $clientClass = '\\Stripe\\StripeClient';
            /** @var object $client */
            $client = new $clientClass($secret);
            $this->client = $client;
        }
    }

    /** True when the Stripe SDK is installed (vendor/ present). */
    private function sdkAvailable(): bool
    {
        return class_exists('\\Stripe\\StripeClient');
    }

    /** True when Stripe Billing is usable (SDK + secret key present). */
    public function isConfigured(): bool
    {
        return $this->client !== null;
    }

    /**
     * Fetch the active Wine Club tier (or any tier by slug).
     *
     * @return array<string,mixed>|null
     */
    public static function tier(string $slug = 'wine-club'): ?array
    {
        return Database::fetch(
            'SELECT id, slug, name, price_cents, `interval`, stripe_price_id, perks, is_active
               FROM subscription_tiers WHERE slug = ? AND is_active = 1',
            [$slug]
        );
    }

    /**
     * The customer's current subscription row joined to its tier (or null).
     *
     * @return array<string,mixed>|null
     */
    public static function forCustomer(int $customerId): ?array
    {
        return Database::fetch(
            "SELECT s.id, s.status, s.stripe_subscription_id, s.current_period_end,
                    s.created_at, t.name AS tier_name, t.slug AS tier_slug,
                    t.price_cents, t.`interval`
               FROM subscriptions s
               JOIN subscription_tiers t ON t.id = s.tier_id
              WHERE s.customer_id = ?
              ORDER BY FIELD(s.status, 'active','past_due','paused','incomplete','canceled'), s.id DESC
              LIMIT 1",
            [$customerId]
        );
    }

    /** Does this customer have a usable (active or paused) membership? */
    public static function hasActive(int $customerId): bool
    {
        $row = self::forCustomer($customerId);
        return $row !== null && in_array((string) $row['status'], ['active', 'paused', 'past_due'], true);
    }

    /**
     * Ensure the customer has a Stripe Customer id, creating one if needed.
     * Persists the id on the local customers row. Returns the Stripe id.
     */
    public function ensureStripeCustomer(array $customer): string
    {
        $existing = (string) ($customer['stripe_customer_id'] ?? '');
        if ($existing !== '') {
            return $existing;
        }
        if ($this->client === null) {
            throw new RuntimeException('Wine Club sign-up is not available yet, Stripe is not configured.');
        }

        $sc = $this->client->customers->create([
            'email' => (string) ($customer['email'] ?? ''),
            'name'  => trim((string) (($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))) ?: null,
            'metadata' => ['customer_id' => (int) $customer['id']],
        ]);
        $stripeId = (string) $sc->id;

        Database::run(
            'UPDATE customers SET stripe_customer_id = ? WHERE id = ?',
            [$stripeId, (int) $customer['id']]
        );
        return $stripeId;
    }

    /**
     * Create a Stripe Billing subscription for the customer on the given tier.
     *
     * Creates (or reuses) the Stripe Customer, then a subscription on the
     * tier's stripe_price_id. Inserts the local mirror row as `incomplete`;
     * the invoice.paid webhook (mirrored via mirrorStatus) flips it to active.
     *
     * @param array<string,mixed> $customer a customers row (needs id, email)
     * @param array<string,mixed> $tier     a subscription_tiers row
     * @return array{subscription_id:string,status:string,client_secret:?string}
     */
    public function createSubscription(array $customer, array $tier): array
    {
        if ($this->client === null) {
            throw new RuntimeException('Wine Club sign-up is not available yet, Stripe is not configured.');
        }
        $priceId = (string) ($tier['stripe_price_id'] ?? '');
        if ($priceId === '') {
            throw new RuntimeException('Wine Club pricing is not set up in Stripe yet. Please check back soon.');
        }

        $customerId = (int) $customer['id'];
        $stripeCustomer = $this->ensureStripeCustomer($customer);

        $sub = $this->client->subscriptions->create([
            'customer' => $stripeCustomer,
            'items'    => [['price' => $priceId]],
            'payment_behavior' => 'default_incomplete',
            'expand'   => ['latest_invoice.payment_intent'],
            'metadata' => ['customer_id' => $customerId, 'tier_id' => (int) $tier['id']],
        ]);

        $stripeSubId = (string) $sub->id;
        $status = $this->mapStatus((string) ($sub->status ?? 'incomplete'));
        $periodEnd = isset($sub->current_period_end) && $sub->current_period_end
            ? gmdate('Y-m-d H:i:s', (int) $sub->current_period_end)
            : null;

        // Pull the PaymentIntent client secret for the first invoice (if any),
        // so the front end can confirm the initial payment with Stripe.js.
        $clientSecret = null;
        $invoice = $sub->latest_invoice ?? null;
        if (is_object($invoice) && isset($invoice->payment_intent) && is_object($invoice->payment_intent)) {
            $clientSecret = (string) ($invoice->payment_intent->client_secret ?? '') ?: null;
        }

        $this->upsertLocal($customerId, (int) $tier['id'], $stripeSubId, $status, $periodEnd);

        return [
            'subscription_id' => $stripeSubId,
            'status'          => $status,
            'client_secret'   => $clientSecret,
        ];
    }

    /**
     * Mirror subscription lifecycle status from a Stripe webhook (the ONLY
     * status writer from the Stripe side). Idempotent upsert by Stripe id.
     */
    public static function mirrorStatus(
        string $stripeSubscriptionId,
        string $stripeStatus,
        ?int $currentPeriodEnd = null
    ): void {
        if ($stripeSubscriptionId === '') {
            return;
        }
        $status = self::mapStatusStatic($stripeStatus);
        $periodEnd = $currentPeriodEnd !== null && $currentPeriodEnd > 0
            ? gmdate('Y-m-d H:i:s', $currentPeriodEnd)
            : null;

        $affected = Database::affected(
            'UPDATE subscriptions
                SET status = ?, current_period_end = COALESCE(?, current_period_end)
              WHERE stripe_subscription_id = ?',
            [$status, $periodEnd, $stripeSubscriptionId]
        );
        // No local row yet (e.g. created out-of-band): nothing to mirror onto.
        unset($affected);
    }

    /**
     * Pause the customer's membership in Stripe (pause_collection) and reflect
     * it locally. The webhook reconciles authoritatively.
     */
    public function pause(int $customerId): void
    {
        $row = self::forCustomer($customerId);
        if ($row === null) {
            throw new RuntimeException('No active membership to pause.');
        }
        $stripeSubId = (string) ($row['stripe_subscription_id'] ?? '');
        if ($this->client !== null && $stripeSubId !== '') {
            $this->client->subscriptions->update($stripeSubId, [
                'pause_collection' => ['behavior' => 'void'],
            ]);
        }
        Database::run(
            'UPDATE subscriptions SET status = ? WHERE customer_id = ? AND id = ?',
            ['paused', $customerId, (int) $row['id']]
        );
    }

    /** Resume a paused membership (clear pause_collection). */
    public function resume(int $customerId): void
    {
        $row = self::forCustomer($customerId);
        if ($row === null) {
            throw new RuntimeException('No membership to resume.');
        }
        $stripeSubId = (string) ($row['stripe_subscription_id'] ?? '');
        if ($this->client !== null && $stripeSubId !== '') {
            $this->client->subscriptions->update($stripeSubId, [
                'pause_collection' => '',
            ]);
        }
        Database::run(
            'UPDATE subscriptions SET status = ? WHERE customer_id = ? AND id = ?',
            ['active', $customerId, (int) $row['id']]
        );
    }

    /**
     * Cancel the customer's membership at period end in Stripe and reflect it
     * locally. The webhook records the final canceled state.
     */
    public function cancel(int $customerId): void
    {
        $row = self::forCustomer($customerId);
        if ($row === null) {
            throw new RuntimeException('No membership to cancel.');
        }
        $stripeSubId = (string) ($row['stripe_subscription_id'] ?? '');
        if ($this->client !== null && $stripeSubId !== '') {
            $this->client->subscriptions->update($stripeSubId, [
                'cancel_at_period_end' => true,
            ]);
        }
        Database::run(
            'UPDATE subscriptions SET status = ? WHERE customer_id = ? AND id = ?',
            ['canceled', $customerId, (int) $row['id']]
        );
    }

    /** Insert or update the local mirror row keyed by Stripe subscription id. */
    private function upsertLocal(
        int $customerId,
        int $tierId,
        string $stripeSubId,
        string $status,
        ?string $periodEnd
    ): void {
        $existing = Database::fetchValue(
            'SELECT id FROM subscriptions WHERE stripe_subscription_id = ?',
            [$stripeSubId]
        );
        if ($existing !== false && $existing !== null) {
            Database::run(
                'UPDATE subscriptions SET status = ?, current_period_end = ?, tier_id = ? WHERE id = ?',
                [$status, $periodEnd, $tierId, (int) $existing]
            );
            return;
        }
        Database::run(
            'INSERT INTO subscriptions
                (customer_id, tier_id, stripe_subscription_id, status, current_period_end)
             VALUES (?, ?, ?, ?, ?)',
            [$customerId, $tierId, $stripeSubId, $status, $periodEnd]
        );
    }

    /** Map a Stripe subscription status onto our local enum. */
    private function mapStatus(string $stripeStatus): string
    {
        return self::mapStatusStatic($stripeStatus);
    }

    private static function mapStatusStatic(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active', 'trialing' => 'active',
            'past_due', 'unpaid' => 'past_due',
            'paused'             => 'paused',
            'canceled'           => 'canceled',
            default              => 'incomplete',
        };
    }
}
