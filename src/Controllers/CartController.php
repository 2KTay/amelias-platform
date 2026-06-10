<?php

declare(strict_types=1);

namespace Amelias\Controllers;

use Amelias\Http\Request;
use Amelias\Services\Availability;
use Amelias\Services\Cart;
use Amelias\Services\GiftCard;
use Amelias\Services\Promotion;
use Amelias\Services\Pricing;
use Amelias\Support\Session;

/**
 * Cart screen (screen 6, Task 2.4): edit/remove lines, ASAP/scheduled pickup
 * slot picker (full slots disabled), tip, promo + gift-card fields, and live
 * reservable-stock guard messaging. All mutations are CSRF-guarded POSTs that
 * PRG back to /cart. Money is integer cents; tax is basis points.
 */
final class CartController extends Controller
{
    public function index(Request $request): void
    {
        $cart = Cart::fromSession();

        if ($request->isPost()) {
            $this->applyMeta($request, $cart);
            $this->redirect(url('/cart'));
            return;
        }

        // Mini-cart drawer hydration: GET /cart with an XHR/JSON accept header
        // returns the lightweight payload instead of the full HTML page.
        if ($request->expectsJson()) {
            $this->json($this->drawerPayload($cart));
            return;
        }

        $this->render($cart);
    }

    public function add(Request $request): void
    {
        $wantsJson = $request->expectsJson();
        if (!csrf_verify((string) $request->input('_csrf'))) {
            if ($wantsJson) {
                $this->json(['ok' => false, 'error' => 'Your session expired — please refresh and try again.'], 422);
                return;
            }
            $this->redirect(url('/menu'));
            return;
        }
        $cart = Cart::fromSession();

        $productId = (int) $request->input('product_id');
        if ($productId > 0) {
            $variantId = $request->input('variant_id') !== null && $request->input('variant_id') !== ''
                ? (int) $request->input('variant_id') : null;
            $qty = max(1, (int) $request->input('quantity', '1'));
            $modifiers = array_map('intval', (array) $request->input('modifiers', []));
            $instructions = (string) $request->input('instructions', '');
            $cart->add($productId, $qty, $variantId, $modifiers, $instructions);
            if (!$wantsJson) {
                $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Added to your cart.'];
            }
        }

        if ($wantsJson) {
            $this->json(['ok' => true] + $this->drawerPayload($cart));
            return;
        }
        $this->redirect(url('/cart'));
    }

    public function update(Request $request): void
    {
        $wantsJson = $request->expectsJson();
        if (!csrf_verify((string) $request->input('_csrf'))) {
            if ($wantsJson) {
                $this->json(['ok' => false, 'error' => 'Your session expired — please refresh and try again.'], 422);
                return;
            }
            $this->redirect(url('/cart'));
            return;
        }
        $cart = Cart::fromSession();
        $key = (string) $request->input('line_key');
        if ($key !== '') {
            $cart->update($key, (int) $request->input('quantity'));
        }
        if ($wantsJson) {
            $this->json(['ok' => true] + $this->drawerPayload($cart));
            return;
        }
        $this->redirect(url('/cart'));
    }

    public function remove(Request $request): void
    {
        $wantsJson = $request->expectsJson();
        if (!csrf_verify((string) $request->input('_csrf'))) {
            if ($wantsJson) {
                $this->json(['ok' => false, 'error' => 'Your session expired — please refresh and try again.'], 422);
                return;
            }
            $this->redirect(url('/cart'));
            return;
        }
        $cart = Cart::fromSession();
        $key = (string) $request->input('line_key');
        if ($key !== '') {
            $cart->remove($key);
        }
        if ($wantsJson) {
            $this->json(['ok' => true] + $this->drawerPayload($cart));
            return;
        }
        $this->redirect(url('/cart'));
    }

    /**
     * Lightweight cart snapshot for the mini-cart drawer (count + lines +
     * subtotal). Money is formatted for display; the authoritative totals/tax
     * still come from the full /cart page (render()) and checkout.
     *
     * @return array{count:int,subtotal:string,checkout_url:string,lines:list<array<string,mixed>>}
     */
    private function drawerPayload(Cart $cart): array
    {
        $lines = [];
        foreach ($cart->lineItems() as $line) {
            $lines[] = [
                'key'        => (string) $line['key'],
                'name'       => (string) $line['name'],
                'quantity'   => (int) $line['quantity'],
                'unit_price' => fmt_money((int) $line['unit_price_cents']),
                'line_total' => fmt_money((int) $line['line_total_cents']),
                'image'      => !empty($line['image_path'])
                    ? asset('uploads/' . ltrim((string) $line['image_path'], '/'))
                    : null,
            ];
        }

        return [
            'count'        => $cart->count(),
            'subtotal'     => fmt_money($cart->subtotalCents()),
            'checkout_url' => url('/cart'),
            'lines'        => $lines,
        ];
    }

    /**
     * Handle tip / promo / gift-card / pickup updates posted from the cart page.
     */
    private function applyMeta(Request $request, Cart $cart): void
    {
        if (!csrf_verify((string) $request->input('_csrf'))) {
            return;
        }

        $action = (string) $request->input('action');
        $flash  = null;

        switch ($action) {
            case 'tip':
                $mode = (string) $request->input('tip_mode', 'none');
                if ($mode === 'custom') {
                    $cart->setTip(cents((string) $request->input('tip_amount', '0')), 'custom');
                } elseif (str_starts_with($mode, 'preset:')) {
                    $pct = (int) substr($mode, 7);
                    $cart->setTip(bps($cart->subtotalCents(), $pct * 100), 'preset:' . $pct);
                } else {
                    $cart->setTip(0, 'none');
                }
                break;

            case 'promo':
                $code = trim((string) $request->input('promo_code', ''));
                if ($code === '') {
                    $cart->setPromoCode(null);
                    break;
                }
                $customerId = Session::customer()['id'] ?? null;
                $result = Promotion::validate($code, $cart->subtotalCents(), $customerId !== null ? (int) $customerId : null);
                if ($result['ok']) {
                    $cart->setPromoCode($code);
                    $flash = ['type' => 'success', 'message' => 'Promo code applied.'];
                } else {
                    $cart->setPromoCode(null);
                    $flash = ['type' => 'error', 'message' => $result['reason']];
                }
                break;

            case 'promo_remove':
                $cart->setPromoCode(null);
                break;

            case 'giftcard':
                $code = trim((string) $request->input('gift_card_code', ''));
                if ($code === '') {
                    $cart->setGiftCardCode(null);
                    break;
                }
                $balance = GiftCard::balanceForCode($code);
                if ($balance !== null && $balance > 0) {
                    $cart->setGiftCardCode($code);
                    $flash = ['type' => 'success', 'message' => 'Gift card applied, balance ' . fmt_money($balance) . '.'];
                } else {
                    $cart->setGiftCardCode(null);
                    $flash = ['type' => 'error', 'message' => 'That gift card is not valid or has no balance.'];
                }
                break;

            case 'giftcard_remove':
                $cart->setGiftCardCode(null);
                break;

            case 'pickup':
                $mode = (string) $request->input('pickup_mode', 'asap');
                $slotId = $request->input('pickup_slot_id') !== null && $request->input('pickup_slot_id') !== ''
                    ? (int) $request->input('pickup_slot_id') : null;
                $cart->setPickup($mode, $slotId);
                break;
        }

        if ($flash !== null) {
            $_SESSION['_flash'] = $flash;
        }
    }

    private function render(Cart $cart): void
    {
        $pricing = Pricing::forCart($cart, Session::customer()['id'] ?? null);
        $state   = $cart->state();

        // Attach reservable-stock guard info per line for messaging.
        $lines = $pricing['lines'];
        foreach ($lines as &$line) {
            if ($line['tracks_inventory']) {
                $reservable = Availability::reservableStock((int) $line['product_id']);
                $line['reservable'] = $reservable;
                $line['stock_warn'] = ($reservable <= 0)
                    ? 'Sold out'
                    : ($reservable < $line['quantity']
                        ? 'Only ' . $reservable . ' left today'
                        : ($reservable <= 3 ? 'Only ' . $reservable . ' left today' : null));
            } else {
                $line['reservable'] = null;
                $line['stock_warn'] = null;
            }
        }
        unset($line);

        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        $tipPresets = array_map('intval', array_filter(explode(',', (string) (\Amelias\Services\Settings::get('tip.presets', '15,18,20') ?? '15,18,20'))));

        $this->viewPublic('public/cart', [
            'title'        => 'Your cart',
            'bodyClass'    => 'has-grain',
            'styles'       => ['pages/cart.css'],
            'lines'        => $lines,
            'pricing'      => $pricing,
            'cart'         => $state,
            'slots'        => Availability::openPickupSlots(),
            'tipPresets'   => $tipPresets,
            'flash'        => $flash,
        ]);
    }
}
