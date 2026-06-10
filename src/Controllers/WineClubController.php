<?php

declare(strict_types=1);

namespace Amelias\Controllers;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Services\AlcoholCompliance;
use Amelias\Services\Subscriptions;
use Amelias\Support\Session;

/**
 * Wine Club (screens 13 + 14, Task 4.4).
 *
 *   /wine-club          , storefront: perks + $40/mo join card (from
 *                          subscription_tiers). join CTA requires login.
 *   /wine-club/join     , POST: create a Stripe Billing subscription via the
 *                          Subscriptions service (creating the Stripe customer
 *                          if needed). Requires a logged-in customer.
 *   /wine-club/portal   , member portal: status, next billing, pause/cancel.
 *                          Redirects anonymous visitors to /login.
 *
 * Wine is 21+ and, under the DTC gate (Q#1/#2), pickup-only: bottles are held
 * at the counter, never shipped, until the feature flag opens. The portal and
 * storefront both surface that compliance note.
 *
 * Money is integer cents; CSRF on POST; Stripe SDK calls are guarded inside the
 * Subscriptions service and degrade to a friendly message when unavailable.
 */
final class WineClubController extends Controller
{
    public function index(Request $request): void
    {
        $tier = Subscriptions::tier('wine-club');
        $customer = Session::customer();
        $hasMembership = $customer !== null && Subscriptions::hasActive((int) $customer['id']);

        $this->viewPublic('public/wineclub', [
            'title'         => 'The Wine Club',
            'bodyClass'     => 'has-grain',
            'styles'        => ['pages/wineclub.css'],
            'tier'          => $tier,
            'perks'         => $this->perks($tier),
            'loggedIn'      => $customer !== null,
            'hasMembership' => $hasMembership,
            'dtcEnabled'    => AlcoholCompliance::dtcShippingEnabled(),
            'flash'         => $this->flash(),
        ]);
    }

    /** POST /wine-club/join, start a Stripe Billing subscription. */
    public function join(Request $request): void
    {
        if (!csrf_verify((string) $request->input('_csrf'))) {
            $this->fail('Your session expired. Please try again.', '/wine-club');
            return;
        }

        $sessionCustomer = Session::customer();
        if ($sessionCustomer === null) {
            // Membership is billed to a card on an account, require login first.
            $_SESSION['_flash'] = [
                'type'    => 'info',
                'message' => 'Please sign in or create an account to join the Wine Club.',
            ];
            $this->redirect(url('/login'));
            return;
        }
        $customerId = (int) $sessionCustomer['id'];

        // Already a member? Send them to the portal.
        if (Subscriptions::hasActive($customerId)) {
            $this->redirect(url('/wine-club/portal'));
            return;
        }

        $tier = Subscriptions::tier('wine-club');
        if ($tier === null) {
            $this->fail('The Wine Club is not open for sign-ups right now.', '/wine-club');
            return;
        }

        $customer = Database::fetch('SELECT * FROM customers WHERE id = ?', [$customerId]);
        if ($customer === null) {
            $this->fail('We could not find your account. Please sign in again.', '/login');
            return;
        }

        $service = new Subscriptions();
        try {
            $result = $service->createSubscription($customer, $tier);
        } catch (\Throwable $e) {
            error_log('[wineclub] join failed: ' . $e->getMessage());
            $this->fail($e->getMessage(), '/wine-club');
            return;
        }

        $_SESSION['_flash'] = [
            'type'    => 'success',
            'message' => $result['status'] === 'active'
                ? 'Welcome to the Wine Club! Your membership is active.'
                : 'Almost there, your membership is being set up. We\'ll confirm by email once your first payment clears.',
        ];
        $this->redirect(url('/wine-club/portal'));
    }

    /** GET/POST /wine-club/portal, member dashboard + pause/cancel actions. */
    public function portal(Request $request): void
    {
        $sessionCustomer = Session::customer();
        if ($sessionCustomer === null) {
            $this->redirect(url('/login'));
            return;
        }
        $customerId = (int) $sessionCustomer['id'];

        if ($request->isPost()) {
            $this->portalAction($request, $customerId);
            return;
        }

        $subscription = Subscriptions::forCustomer($customerId);
        if ($subscription === null) {
            // Not a member yet, bounce to the storefront to join.
            $this->redirect(url('/wine-club'));
            return;
        }

        $this->viewPublic('public/wineclub-portal', [
            'title'        => 'Your Wine Club',
            'bodyClass'    => 'has-grain',
            'styles'       => ['pages/wineclub.css'],
            'customer'     => $sessionCustomer,
            'subscription' => $subscription,
            'payment'      => $this->paymentMethod($subscription),
            'bottles'      => $this->currentBottles($subscription),
            'history'      => $this->pickupHistory($customerId),
            'dtcEnabled'   => AlcoholCompliance::dtcShippingEnabled(),
            'flash'        => $this->flash(),
        ]);
    }

    /**
     * The saved payment method (card brand / last4 / expiry) for the membership,
     * when known. No local column stores this today, so we read it from the
     * subscription row if a future migration/webhook populates it; otherwise the
     * portal renders a "no card on file" state. Wire to Stripe PM data here.
     *
     * @param array<string,mixed> $subscription
     * @return array{brand:string,last4:string,exp:string}|null
     */
    private function paymentMethod(array $subscription): ?array
    {
        $last4 = trim((string) ($subscription['card_last4'] ?? ''));
        if ($last4 === '') {
            return null;
        }
        return [
            'brand' => trim((string) ($subscription['card_brand'] ?? 'Card')),
            'last4' => $last4,
            'exp'   => trim((string) ($subscription['card_exp'] ?? '')),
        ];
    }

    /**
     * This month's selected bottles for the member's portal. No bottle-selection
     * table exists yet, so this returns an empty list and the portal degrades to
     * a generic "held for you" note. Source from a wine_club_selections table here.
     *
     * @param array<string,mixed> $subscription
     * @return list<array{name:string,meta:string,desc:string,image:?string}>
     */
    private function currentBottles(array $subscription): array
    {
        unset($subscription);
        return [];
    }

    /**
     * Pickup & shipment history rows for the member. No shipments/pickups table
     * exists yet, so this returns an empty list and the portal shows an empty
     * state. Source from a wine_club_shipments table (date, bottles, status) here.
     *
     * @return list<array{date:string,bottles:string,status:string,status_class:string}>
     */
    private function pickupHistory(int $customerId): array
    {
        unset($customerId);
        return [];
    }

    /** Apply a pause / resume / cancel from the portal. */
    private function portalAction(Request $request, int $customerId): void
    {
        if (!csrf_verify((string) $request->input('_csrf'))) {
            $this->redirect(url('/wine-club/portal'));
            return;
        }
        $action = (string) $request->input('action', '');
        $service = new Subscriptions();

        try {
            match ($action) {
                'pause'  => $service->pause($customerId),
                'resume' => $service->resume($customerId),
                'cancel' => $service->cancel($customerId),
                // 'update_payment' has no local handler yet (needs a Stripe
                // Billing portal session / setup-intent flow); fall through to
                // the friendly default so the button is safe to wire up later.
                default  => null,
            };
            $_SESSION['_flash'] = ['type' => 'success', 'message' => match ($action) {
                'pause'  => 'Your membership is paused. Resume anytime.',
                'resume' => 'Welcome back, your membership is active again.',
                'cancel' => 'Your membership is set to cancel at the end of the current period.',
                'update_payment' => 'To update your card, please contact us, secure self-service card updates are coming soon.',
                default  => 'No change made.',
            }];
        } catch (\Throwable $e) {
            error_log('[wineclub] portal action failed: ' . $e->getMessage());
            $_SESSION['_flash'] = ['type' => 'error', 'message' => $e->getMessage()];
        }

        $this->redirect(url('/wine-club/portal'));
    }

    /**
     * Split the tier's perks string into display lines.
     *
     * @param array<string,mixed>|null $tier
     * @return list<string>
     */
    private function perks(?array $tier): array
    {
        $raw = (string) ($tier['perks'] ?? '');
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/\s*·\s*/u', $raw) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn (string $s): bool => $s !== ''));
    }

    private function fail(string $message, string $to): void
    {
        $_SESSION['_flash'] = ['type' => 'error', 'message' => $message];
        $this->redirect(url($to));
    }

    private function flash(): ?array
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return is_array($flash) ? $flash : null;
    }
}
