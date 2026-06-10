<?php

declare(strict_types=1);

namespace Amelias\Models;

use Amelias\Database\Database;

/**
 * Thin data helper over customer_favorites (account portal quick-add / reorder).
 * All access is keyed by customer_id so a customer can only touch their own rows.
 */
final class Favorite
{
    /**
     * Saved favorites for a customer, joined to the live product for current
     * price / 86 state (so the portal can flag changed items).
     *
     * @return list<array<string,mixed>>
     */
    public static function forCustomer(int $customerId): array
    {
        return Database::fetchAll(
            'SELECT f.id, f.product_id, p.name, p.slug, p.price_cents, p.is_86, p.type, p.image_path
               FROM customer_favorites f
               JOIN products p ON p.id = f.product_id
              WHERE f.customer_id = ?
              ORDER BY f.created_at DESC',
            [$customerId]
        );
    }

    /** Add a favorite (idempotent, unique key on customer_id+product_id). */
    public static function add(int $customerId, int $productId): void
    {
        Database::run(
            'INSERT IGNORE INTO customer_favorites (customer_id, product_id) VALUES (?, ?)',
            [$customerId, $productId]
        );
    }

    /** Remove a favorite, scoped to the owning customer. */
    public static function remove(int $customerId, int $favoriteId): void
    {
        Database::run(
            'DELETE FROM customer_favorites WHERE id = ? AND customer_id = ?',
            [$favoriteId, $customerId]
        );
    }
}
