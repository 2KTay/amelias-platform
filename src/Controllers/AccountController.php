<?php

declare(strict_types=1);

namespace Amelias\Controllers;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Models\Address;
use Amelias\Models\Favorite;
use Amelias\Support\Session;

/**
 * Customer account portal (screen 17, 6.6).
 *
 * Order history, reorder, saved favorites + addresses, Wine Club status, and
 * upcoming/past reservations, plus an editable profile form. Requires a
 * logged-in customer (Session::customer); anonymous visitors are redirected to
 * /login. Money is integer cents.
 */
final class AccountController extends Controller
{
    public function index(Request $request): void
    {
        $customer = Session::customer();
        if ($customer === null) {
            $this->redirect(url('/login'));
            return;
        }
        $customerId = (int) $customer['id'];

        // Optional POSTs for favorite/address management (CSRF-guarded, PRG).
        if ($request->isPost()) {
            $this->handleManage($request, $customerId);
            return;
        }

        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        $this->viewPublic('public/account', [
            'title'        => 'Your account',
            'bodyClass'    => 'has-grain',
            'styles'       => ['pages/account.css'],
            'customer'     => $customer,
            'profile'      => $this->profile($customerId),
            'orders'       => $this->orders($customerId),
            'favorites'    => Favorite::forCustomer($customerId),
            'addresses'    => Address::forCustomer($customerId),
            'subscription' => $this->subscription($customerId),
            'upcoming'     => $this->reservations($customerId, true),
            'past'         => $this->reservations($customerId, false),
            'flash'        => $flash,
        ]);
    }

    /**
     * Reorder: rebuild a cart from a past order's snapshotted line items,
     * re-priced against current products. 86'd or removed items are flagged.
     * Cart shape is the simple ['product_id' => qty] map other code can adopt.
     */
    public function reorder(Request $request): void
    {
        $customer = Session::customer();
        if ($customer === null) {
            $this->redirect(url('/login'));
            return;
        }
        if (!csrf_verify((string) $request->input('_csrf'))) {
            $this->redirect(url('/account'));
            return;
        }
        $customerId = (int) $customer['id'];
        $orderId    = (int) $request->param('id');

        // Ownership check: the order must belong to this customer.
        $owns = Database::fetchValue(
            'SELECT 1 FROM orders WHERE id = ? AND customer_id = ?',
            [$orderId, $customerId]
        );
        if (!$owns) {
            $_SESSION['_flash'] = ['type' => 'error', 'message' => 'That order could not be found.'];
            $this->redirect(url('/account'));
            return;
        }

        $items = Database::fetchAll(
            'SELECT product_id, name_snapshot, quantity FROM order_items WHERE order_id = ?',
            [$orderId]
        );

        $cart    = $_SESSION['cart'] ?? [];
        $flagged = [];
        $added   = 0;
        foreach ($items as $item) {
            $productId = $item['product_id'] !== null ? (int) $item['product_id'] : 0;
            $qty       = max(1, (int) $item['quantity']);
            if ($productId === 0) {
                $flagged[] = (string) $item['name_snapshot'] . ' (no longer available)';
                continue;
            }
            $product = Database::fetch(
                'SELECT id, name, is_86, is_active FROM products WHERE id = ?',
                [$productId]
            );
            if ($product === null || !$product['is_active']) {
                $flagged[] = (string) $item['name_snapshot'] . ' (no longer available)';
                continue;
            }
            if ((int) $product['is_86'] === 1) {
                $flagged[] = (string) $product['name'] . ' (currently sold out)';
                continue;
            }
            $cart[$productId] = ($cart[$productId] ?? 0) + $qty;
            $added++;
        }
        $_SESSION['cart'] = $cart;

        if ($added === 0 && $flagged !== []) {
            $msg = 'None of those items are available right now: ' . implode(', ', $flagged) . '.';
            $type = 'error';
        } elseif ($flagged !== []) {
            $msg = 'Added to your cart. Some items were skipped: ' . implode(', ', $flagged) . '.';
            $type = 'warning';
        } else {
            $msg = 'Your previous order was added to the cart.';
            $type = 'success';
        }
        $_SESSION['_flash'] = ['type' => $type, 'message' => $msg];
        $this->redirect(url('/cart'));
    }

    /** Favorite/address add/remove from the portal. */
    private function handleManage(Request $request, int $customerId): void
    {
        if (csrf_verify((string) $request->input('_csrf'))) {
            switch ((string) $request->input('action')) {
                case 'fav_remove':
                    Favorite::remove($customerId, (int) $request->input('favorite_id'));
                    break;
                case 'addr_add':
                    Address::add($customerId, [
                        'label'   => trim((string) $request->input('label')),
                        'line1'   => trim((string) $request->input('line1')),
                        'line2'   => trim((string) $request->input('line2')),
                        'city'    => trim((string) $request->input('city')),
                        'state'   => trim((string) $request->input('state')),
                        'postal'  => trim((string) $request->input('postal')),
                        'purpose' => (string) $request->input('purpose', 'shipping'),
                    ]);
                    break;
                case 'addr_remove':
                    Address::remove($customerId, (int) $request->input('address_id'));
                    break;
                case 'profile_update':
                    $this->updateProfile($request, $customerId);
                    break;
            }
        }
        $this->redirect(url('/account'));
    }

    /**
     * Persist the customer's profile (first/last name, email, phone) from the
     * account form. Email is required and must be unique across customers; the
     * session display name is refreshed so the greeting stays in sync.
     */
    private function updateProfile(Request $request, int $customerId): void
    {
        $firstName = trim((string) $request->input('first_name'));
        $lastName  = trim((string) $request->input('last_name'));
        $email     = trim((string) $request->input('email'));
        $phone     = trim((string) $request->input('phone'));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $_SESSION['_flash'] = ['type' => 'error', 'message' => 'Please enter a valid email address.'];
            return;
        }

        // Email must stay unique across customers.
        $taken = Database::fetchValue(
            'SELECT 1 FROM customers WHERE email = ? AND id <> ?',
            [$email, $customerId]
        );
        if ($taken) {
            $_SESSION['_flash'] = ['type' => 'error', 'message' => 'That email is already in use.'];
            return;
        }

        Database::run(
            'UPDATE customers SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?',
            [
                $firstName !== '' ? $firstName : null,
                $lastName !== '' ? $lastName : null,
                $email,
                $phone !== '' ? $phone : null,
                $customerId,
            ]
        );

        // Keep the session actor (greeting + email) in sync with the new values.
        $_SESSION['customer']['email'] = $email;
        $_SESSION['customer']['name']  = trim($firstName . ' ' . $lastName);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Your profile has been updated.'];
    }

    /** @return array{first_name:string,last_name:string,email:string,phone:string} */
    private function profile(int $customerId): array
    {
        $row = Database::fetch(
            'SELECT first_name, last_name, email, phone FROM customers WHERE id = ?',
            [$customerId]
        ) ?? [];
        return [
            'first_name' => (string) ($row['first_name'] ?? ''),
            'last_name'  => (string) ($row['last_name'] ?? ''),
            'email'      => (string) ($row['email'] ?? ''),
            'phone'      => (string) ($row['phone'] ?? ''),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function orders(int $customerId): array
    {
        $orders = Database::fetchAll(
            'SELECT id, public_token, total_cents, status, placed_at
               FROM orders
              WHERE customer_id = ? AND status NOT IN (\'pending\',\'expired\')
              ORDER BY placed_at DESC LIMIT 30',
            [$customerId]
        );
        // Attach a short item summary per order.
        foreach ($orders as &$o) {
            $names = Database::fetchAll(
                'SELECT name_snapshot, quantity FROM order_items WHERE order_id = ?',
                [(int) $o['id']]
            );
            $parts = [];
            foreach ($names as $n) {
                $qty = (int) $n['quantity'];
                $parts[] = $qty > 1 ? $n['name_snapshot'] . ' x' . $qty : (string) $n['name_snapshot'];
            }
            $o['items_summary'] = implode(', ', $parts);
        }
        unset($o);
        return $orders;
    }

    /** @return array<string,mixed>|null */
    private function subscription(int $customerId): ?array
    {
        return Database::fetch(
            'SELECT s.status, s.current_period_end, s.created_at AS member_since,
                    t.name AS tier_name, t.price_cents, t.`interval`
               FROM subscriptions s
               JOIN subscription_tiers t ON t.id = s.tier_id
              WHERE s.customer_id = ?
              ORDER BY s.created_at DESC LIMIT 1',
            [$customerId]
        );
    }

    /**
     * Upcoming (future, active) or past reservations.
     *
     * @return list<array<string,mixed>>
     */
    private function reservations(int $customerId, bool $upcoming): array
    {
        if ($upcoming) {
            return Database::fetchAll(
                "SELECT b.id, b.public_token, b.party_size, b.status, s.slot_start
                   FROM bookings b
                   JOIN booking_slots s ON s.id = b.slot_id
                  WHERE b.customer_id = ?
                    AND s.slot_start >= UTC_TIMESTAMP()
                    AND b.status IN ('held','confirmed','seated')
                  ORDER BY s.slot_start ASC LIMIT 10",
                [$customerId]
            );
        }
        return Database::fetchAll(
            "SELECT b.id, b.public_token, b.party_size, b.status, s.slot_start
               FROM bookings b
               JOIN booking_slots s ON s.id = b.slot_id
              WHERE b.customer_id = ?
                AND (s.slot_start < UTC_TIMESTAMP() OR b.status IN ('completed','cancelled','no_show'))
              ORDER BY s.slot_start DESC LIMIT 10",
            [$customerId]
        );
    }
}
