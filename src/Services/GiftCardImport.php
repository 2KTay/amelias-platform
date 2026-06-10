<?php

declare(strict_types=1);

namespace Amelias\Services;

use RuntimeException;

/**
 * Square gift-card balance import (Task 7.2, Q#4), FEATURE-GATED STUB.
 *
 * WHY THIS EXISTS, AND WHY IT IS A STUB
 * -------------------------------------
 * Square gift cards are REAL MONEY OWED to real customers. Importing those
 * balances is a money-touching cutover step that must NOT run until:
 *   (a) the client provides the Square gift-card export, AND
 *   (b) an explicit honor / parallel / hard-cut decision is recorded (Q#4), AND
 *   (c) the import is reconciled to total EXACTLY the Square export.
 *
 * Because none of those are answered yet, this service is gated OFF behind
 * config('features.giftcard_import') (env FEATURE_GIFTCARD_IMPORT, default
 * false). Calling import() while the gate is off throws, by design, so a
 * mis-run cannot silently mint balances. The reconciliation contract is
 * documented here so whoever flips the gate implements it against a fixed spec.
 *
 * IMPORT CONTRACT (implement when Q#4 is answered)
 * ------------------------------------------------
 *  1. Parse the Square export (CSV/JSON) into rows of:
 *       { card_code|gan, balance_cents (int), currency, status }.
 *  2. Sum balance_cents across all ACTIVE cards  => $exportTotalCents.
 *  3. For each card, inside ONE transaction per card (or one wrapping txn):
 *       - INSERT a gift_cards row (status active|depleted per balance),
 *         current_balance = balance_cents, source = 'square_import'.
 *       - INSERT a gift_card_transactions ledger row, type = 'import',
 *         amount_cents = +balance_cents, note = Square GAN reference.
 *     The ledger is append-only and must sum to current_balance per card.
 *  4. After all cards: SUM(current_balance) over imported cards MUST equal
 *     $exportTotalCents. If it does NOT, ROLL BACK everything and abort, a
 *     mismatch means money was created or lost. Reconcile to the EXACT total.
 *  5. Be idempotent: re-running with the same export must not double-credit
 *     (dedupe on the Square GAN / card_code unique key).
 *
 * HONOR STRATEGY (Q#4, the recorded decision drives behavior)
 *   - honor-by-import : run this import; native platform redeems against it.
 *   - parallel        : leave Square active for a window; do NOT import; staff
 *                       manually credit on presentation (no code path here).
 *   - hard-cut        : Square off on a date; balances either imported (this
 *                       service) or comped, never silently stranded.
 *
 * This stub deliberately implements NO parsing/DB writes. It documents the
 * contract and enforces the gate.
 */
final class GiftCardImport
{
    /**
     * Import Square gift-card balances. Gated OFF until Q#4 is answered.
     *
     * @param string $exportPath Absolute path to the Square gift-card export file.
     * @param array{
     *     strategy?: 'honor-by-import'|'parallel'|'hard-cut',
     *     dry_run?: bool,
     *     expected_total_cents?: int
     * } $options
     * @return array{imported:int,total_cents:int,reconciled:bool} Summary.
     *
     * @throws RuntimeException Always while the feature gate is off, or when the
     *                          import is not yet implemented behind an open gate.
     */
    public function import(string $exportPath, array $options = []): array
    {
        if (!$this->enabled()) {
            throw new RuntimeException(
                'GiftCardImport is disabled. Square gift-card balances are real money '
                . 'owed and must not be imported until Q#4 is answered (export received '
                . '+ honor/parallel/hard-cut decision recorded). Flip '
                . 'FEATURE_GIFTCARD_IMPORT=true only as part of the documented cutover '
                . 'step in docs/runbooks/cutover.md, with reconciliation to the exact '
                . 'Square export total.'
            );
        }

        // Gate is open but the import body is intentionally unimplemented in this
        // phase. Implement against the IMPORT CONTRACT above. Failing loudly
        // prevents a half-built importer from minting or losing balances.
        throw new RuntimeException(
            'GiftCardImport::import() is a stub. Implement the documented '
            . 'reconciliation contract (parse export -> insert cards + ledger -> '
            . 'assert SUM(current_balance) === export total, else roll back) before '
            . 'running it against production data.'
        );
    }

    /** Whether the import feature gate is on (Q#4 answered + flag flipped). */
    public function enabled(): bool
    {
        return (bool) config('features.giftcard_import', false);
    }
}
