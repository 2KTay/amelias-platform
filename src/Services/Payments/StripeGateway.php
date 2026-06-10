<?php

declare(strict_types=1);

namespace Amelias\Services\Payments;

use Amelias\Services\Settings;
use RuntimeException;

/**
 * Thin wrapper over the Stripe PHP SDK for the operations we need: creating
 * PaymentIntents and issuing refunds. We never touch card data (SAQ-A), only
 * PaymentIntent / Checkout objects.
 *
 * The SDK (stripe/stripe-php) is declared in composer.json but may not be
 * installed locally, so every SDK touch is guarded with class_exists(). When
 * the SDK or keys are missing, methods that need them throw RuntimeException so
 * callers can degrade gracefully.
 */
final class StripeGateway
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

    /** True when a secret key is configured (DB or env). */
    public function isConfigured(): bool
    {
        return Settings::isConfigured('stripe.secret_key');
    }

    /** Publishable key for the client-side Stripe.js (safe to expose). */
    public function publishableKey(): string
    {
        return (string) (Settings::get('stripe.publishable_key') ?? '');
    }

    /**
     * Create a PaymentIntent for an integer-cents amount.
     *
     * @param array<string,scalar> $metadata e.g. ['order_id' => 42]
     * @return array{id:string,client_secret:string}
     */
    public function createPaymentIntent(int $amountCents, string $currency = 'usd', array $metadata = []): array
    {
        if ($this->client === null) {
            throw new RuntimeException('Stripe is not configured (missing SDK or secret key).');
        }
        if ($amountCents <= 0) {
            throw new RuntimeException('PaymentIntent amount must be positive cents.');
        }

        $intent = $this->client->paymentIntents->create([
            'amount'                    => $amountCents,
            'currency'                  => strtolower($currency),
            'metadata'                  => $metadata,
            'automatic_payment_methods' => ['enabled' => true],
        ]);

        return [
            'id'            => (string) $intent->id,
            'client_secret' => (string) $intent->client_secret,
        ];
    }

    /**
     * Refund a PaymentIntent in full or part (null = full).
     *
     * @return array<string,mixed>
     */
    public function refund(string $paymentIntentId, ?int $amountCents = null): array
    {
        if ($this->client === null) {
            throw new RuntimeException('Stripe is not configured (missing SDK or secret key).');
        }

        $params = ['payment_intent' => $paymentIntentId];
        if ($amountCents !== null) {
            $params['amount'] = abs($amountCents);
        }

        $refund = $this->client->refunds->create($params);

        return [
            'id'           => (string) $refund->id,
            'status'       => (string) $refund->status,
            'amount_cents' => (int) $refund->amount,
        ];
    }
}
