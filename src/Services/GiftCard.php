<?php

declare(strict_types=1);

namespace Amelias\Services;

use Amelias\Database\Database;
use RuntimeException;

/**
 * Gift cards (Task 2.x, C-V7-1).
 *
 * The append-only gift_card_transactions ledger is the source of truth;
 * gift_cards.current_balance is a cached value reconciled to the ledger.
 *
 *   - purchase(): issue a new card (tax-exempt) + an 'issue' ledger row.
 *   - redeem(): guarded atomic balance decrement + 'redeem' ledger row, run
 *     INSIDE the checkout transaction so a balance can never go negative or be
 *     double-spent under concurrency.
 *   - refundCredit(): inverse credit when an order using a gift card is refunded.
 *   - reconcile(): recompute cached balance from the ledger.
 */
final class GiftCard
{
    /** Generate a human-readable, unambiguous redemption code. */
    public static function generateCode(): string
    {
        // Crockford-ish alphabet (no I/O/0/1) in 4-4-4 groups.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $raw = '';
            for ($i = 0; $i < 12; $i++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4);
            $exists = Database::fetchValue('SELECT 1 FROM gift_cards WHERE code = ?', [$code]);
        } while ($exists);
        return $code;
    }

    /**
     * Issue a new gift card with the given balance (integer cents). Tax-exempt.
     * Writes the card + an 'issue' ledger row in one transaction. Returns the
     * card row (including code + id).
     *
     * @return array<string,mixed>
     */
    public static function purchase(
        int $amountCents,
        ?string $purchaserEmail,
        ?string $recipientEmail,
        ?int $orderId = null
    ): array {
        if ($amountCents <= 0) {
            throw new RuntimeException('Gift card amount must be positive cents.');
        }
        $code = self::generateCode();

        return Database::transaction(static function () use ($amountCents, $purchaserEmail, $recipientEmail, $orderId, $code): array {
            $id = Database::insert(
                'INSERT INTO gift_cards (code, initial_cents, current_balance, status, purchaser_email, recipient_email, source)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$code, $amountCents, $amountCents, 'active', $purchaserEmail, $recipientEmail, 'native']
            );
            Database::run(
                'INSERT INTO gift_card_transactions (gift_card_id, order_id, type, amount_cents, balance_after, note)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$id, $orderId, 'issue', $amountCents, $amountCents, 'Card issued']
            );
            return Database::fetch('SELECT * FROM gift_cards WHERE id = ?', [$id]) ?? ['id' => $id, 'code' => $code];
        });
    }

    /** Look up a card by code (any status). */
    public static function findByCode(string $code): ?array
    {
        return Database::fetch('SELECT * FROM gift_cards WHERE code = ?', [strtoupper(trim($code))]);
    }

    /** Public balance check for a code (active card => balance; else null). */
    public static function balanceForCode(string $code): ?int
    {
        $card = self::findByCode($code);
        if ($card === null || (string) $card['status'] === 'void') {
            return null;
        }
        return (int) $card['current_balance'];
    }

    /**
     * Redeem up to $requestCents from a card. Guarded atomic decrement plus a
     * 'redeem' ledger row. Returns the actual amount applied (0 if none). MUST
     * run inside the surrounding checkout transaction.
     *
     * The UPDATE only succeeds while the card is active and has >= the amount
     * being deducted, so the balance can never go negative even under a race.
     */
    public static function redeem(int $giftCardId, int $requestCents, ?int $orderId): int
    {
        if ($requestCents <= 0) {
            return 0;
        }

        $card = Database::fetch(
            'SELECT id, current_balance, status FROM gift_cards WHERE id = ?',
            [$giftCardId]
        );
        if ($card === null || (string) $card['status'] !== 'active') {
            return 0;
        }

        $apply = min($requestCents, (int) $card['current_balance']);
        if ($apply <= 0) {
            return 0;
        }

        $affected = Database::affected(
            "UPDATE gift_cards
                SET current_balance = current_balance - :amt,
                    status = CASE WHEN current_balance - :amt2 <= 0 THEN 'depleted' ELSE 'active' END
              WHERE id = :id AND current_balance >= :amt3 AND status = 'active'",
            ['amt' => $apply, 'amt2' => $apply, 'id' => $giftCardId, 'amt3' => $apply]
        );
        if ($affected !== 1) {
            return 0; // lost the race; caller treats as no redemption
        }

        $balanceAfter = (int) Database::fetchValue('SELECT current_balance FROM gift_cards WHERE id = ?', [$giftCardId]);
        Database::run(
            'INSERT INTO gift_card_transactions (gift_card_id, order_id, type, amount_cents, balance_after, note)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$giftCardId, $orderId, 'redeem', -$apply, $balanceAfter, 'Applied to order']
        );
        return $apply;
    }

    /**
     * Inverse credit: restore $amountCents to a card (e.g. when its order is
     * refunded). Reactivates a depleted card. Writes a 'refund_credit' row.
     */
    public static function refundCredit(int $giftCardId, int $amountCents, ?int $orderId): void
    {
        if ($amountCents <= 0) {
            return;
        }
        Database::transaction(static function () use ($giftCardId, $amountCents, $orderId): void {
            $card = Database::fetch('SELECT id, status FROM gift_cards WHERE id = ? FOR UPDATE', [$giftCardId]);
            if ($card === null || (string) $card['status'] === 'void') {
                return;
            }
            Database::run(
                "UPDATE gift_cards
                    SET current_balance = current_balance + ?,
                        status = CASE WHEN status = 'depleted' THEN 'active' ELSE status END
                  WHERE id = ?",
                [$amountCents, $giftCardId]
            );
            $balanceAfter = (int) Database::fetchValue('SELECT current_balance FROM gift_cards WHERE id = ?', [$giftCardId]);
            Database::run(
                'INSERT INTO gift_card_transactions (gift_card_id, order_id, type, amount_cents, balance_after, note)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$giftCardId, $orderId, 'refund_credit', $amountCents, $balanceAfter, 'Refund credit']
            );
        });
    }

    /**
     * Reconcile the cached balance to the ledger sum. Returns the corrected
     * balance. Useful after imports / as a periodic integrity check.
     */
    public static function reconcile(int $giftCardId): int
    {
        return Database::transaction(static function () use ($giftCardId): int {
            $ledger = (int) Database::fetchValue(
                'SELECT COALESCE(SUM(amount_cents), 0) FROM gift_card_transactions WHERE gift_card_id = ?',
                [$giftCardId]
            );
            $ledger = max(0, $ledger);
            Database::run(
                "UPDATE gift_cards
                    SET current_balance = ?,
                        status = CASE
                                   WHEN status = 'void' THEN 'void'
                                   WHEN ? <= 0 THEN 'depleted'
                                   ELSE 'active'
                                 END
                  WHERE id = ?",
                [$ledger, $ledger, $giftCardId]
            );
            return $ledger;
        });
    }
}
