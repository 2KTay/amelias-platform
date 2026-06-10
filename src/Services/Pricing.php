<?php

declare(strict_types=1);

namespace Amelias\Services;

/**
 * Pure pricing assembler shared by the cart + checkout (Task 2.4 / 2.5).
 *
 * Turns a Cart into a fully-resolved money breakdown using LIVE catalog prices
 * for DISPLAY and as the basis for the snapshot taken at order creation. All
 * integer cents; tax in basis points via Tax. The gift-card amount shown here
 * is an *estimate* of how much a card could cover, the authoritative, race-safe
 * redemption happens inside the checkout transaction (GiftCard::redeem).
 *
 * No mutation, no DB writes.
 */
final class Pricing
{
    /**
     * @return array{
     *   lines: list<array<string,mixed>>,
     *   subtotal_cents: int,
     *   promotion: array<string,mixed>|null,
     *   promo_code: ?string,
     *   discount_cents: int,
     *   tax_lines: list<array<string,mixed>>,
     *   tax_cents: int,
     *   tip_cents: int,
     *   total_cents: int,
     *   gift_card: array<string,mixed>|null,
     *   gift_card_applied_cents: int,
     *   amount_due_cents: int,
     *   bases_by_category: array<string,int>
     * }
     */
    public static function forCart(Cart $cart, ?int $customerId = null): array
    {
        $state = $cart->state();
        $lines = $cart->lineItems();

        $subtotal = 0;
        $basesByCategory = [];
        foreach ($lines as $line) {
            $subtotal += (int) $line['line_total_cents'];
            $cat = (string) $line['tax_category'];
            $basesByCategory[$cat] = ($basesByCategory[$cat] ?? 0) + (int) $line['line_total_cents'];
        }

        // Promotion (pre-tax discount).
        $promotion = null;
        $discount  = 0;
        $promoCode = $state['promo_code'] ?? null;
        if ($promoCode !== null && $subtotal > 0) {
            $result = Promotion::validate($promoCode, $subtotal, $customerId);
            if ($result['ok']) {
                $promotion = $result['promotion'];
                $discount  = (int) $result['discount_cents'];
            } else {
                $promoCode = null; // silently drop an invalid stored code from totals
            }
        }

        // Tax lines (post-discount, per category). Empty in Stripe-Tax mode.
        $taxLines = Tax::compute($basesByCategory, $discount);
        $taxCents = Tax::total($taxLines);

        $tipCents = max(0, (int) ($state['tip_cents'] ?? 0));

        $total = max(0, $subtotal - $discount) + $taxCents + $tipCents;

        // Gift card (estimate of coverage).
        $giftCard = null;
        $giftApplied = 0;
        $gcCode = $state['gift_card_code'] ?? null;
        if ($gcCode !== null) {
            $card = GiftCard::findByCode($gcCode);
            if ($card !== null && (string) $card['status'] === 'active' && (int) $card['current_balance'] > 0) {
                $giftCard = $card;
                $giftApplied = min((int) $card['current_balance'], $total);
            }
        }

        $amountDue = max(0, $total - $giftApplied);

        return [
            'lines'                   => $lines,
            'subtotal_cents'          => $subtotal,
            'promotion'               => $promotion,
            'promo_code'              => $promoCode,
            'discount_cents'          => $discount,
            'tax_lines'               => $taxLines,
            'tax_cents'               => $taxCents,
            'tip_cents'               => $tipCents,
            'total_cents'             => $total,
            'gift_card'               => $giftCard,
            'gift_card_applied_cents' => $giftApplied,
            'amount_due_cents'        => $amountDue,
            'bases_by_category'       => $basesByCategory,
        ];
    }
}
