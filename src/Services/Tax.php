<?php

declare(strict_types=1);

namespace Amelias\Services;

use Amelias\Database\Database;

/**
 * Per-category AZ TPT tax (Task 2.x, C-TAX-5 / Q#8).
 *
 * Internal path: each taxable category accrues its own line, taxed at its
 * maintained basis-point rate from tax_rates. Gift-card purchases and any
 * non_taxable category accrue 0. A discount is applied pre-tax, distributed
 * across taxable categories proportionally to their share of the taxable base.
 *
 * The Stripe-Tax seam: when config('features.stripe_tax') is true, the order's
 * tax should instead come from Stripe at confirmation and be persisted with
 * source='stripe_tax'. compute() short-circuits to an empty internal result in
 * that mode so the internal numbers never compete with Stripe's; the checkout
 * flow is responsible for sourcing Stripe tax lines. Until Q#8 is confirmed the
 * internal rates are placeholders (see db/seed.php).
 *
 * Returns tax lines: [{tax_category, taxable_base_cents, rate_bps, tax_cents}].
 */
final class Tax
{
    /** @var array<string,int>|null in-request cache of category => rate_bps */
    private static ?array $rateCache = null;

    /** True when Stripe Tax should own the calculation instead of the internal table. */
    public static function usesStripeTax(): bool
    {
        return (bool) config('features.stripe_tax', false);
    }

    /**
     * Compute internal tax lines for a set of (tax_category => taxable_base_cents).
     *
     * @param array<string,int> $basesByCategory taxable base cents per category
     * @param int $discountCents pre-tax discount to subtract proportionally
     * @return list<array{tax_category:string,taxable_base_cents:int,rate_bps:int,tax_cents:int}>
     */
    public static function compute(array $basesByCategory, int $discountCents = 0): array
    {
        if (self::usesStripeTax()) {
            // Stripe Tax owns the numbers; internal path emits nothing.
            return [];
        }

        // Strip non-positive bases and the always-exempt category.
        $bases = [];
        foreach ($basesByCategory as $cat => $base) {
            $base = (int) $base;
            if ($base > 0 && $cat !== 'non_taxable') {
                $bases[$cat] = $base;
            }
        }
        if ($bases === []) {
            return [];
        }

        // Distribute the discount across categories by share of taxable base.
        $totalBase = array_sum($bases);
        $discountCents = max(0, min($discountCents, $totalBase));

        $lines = [];
        $allocated = 0;
        $cats = array_keys($bases);
        $lastCat = end($cats);
        foreach ($bases as $cat => $base) {
            if ($cat === $lastCat) {
                $catDiscount = $discountCents - $allocated; // remainder absorbs rounding
            } else {
                $catDiscount = (int) floor($discountCents * $base / $totalBase);
                $allocated += $catDiscount;
            }
            $taxable = max(0, $base - $catDiscount);
            $rateBps = self::rateBps($cat);
            $lines[] = [
                'tax_category'       => $cat,
                'taxable_base_cents' => $taxable,
                'rate_bps'           => $rateBps,
                'tax_cents'          => bps($taxable, $rateBps),
            ];
        }
        return $lines;
    }

    /** Sum of tax_cents across the provided lines. */
    public static function total(array $lines): int
    {
        $sum = 0;
        foreach ($lines as $l) {
            $sum += (int) $l['tax_cents'];
        }
        return $sum;
    }

    /** Maintained rate (basis points) for a tax category; 0 if unknown. */
    public static function rateBps(string $category): int
    {
        if (self::$rateCache === null) {
            self::$rateCache = [];
            $rows = Database::fetchAll(
                'SELECT tax_category, rate_bps FROM tax_rates WHERE is_active = 1'
            );
            foreach ($rows as $r) {
                // Newest active row per category wins (insertion order); keep last.
                self::$rateCache[(string) $r['tax_category']] = (int) $r['rate_bps'];
            }
        }
        return self::$rateCache[$category] ?? 0;
    }

    /** Reset the in-request rate cache (tests). */
    public static function resetCache(): void
    {
        self::$rateCache = null;
    }
}
