<?php

declare(strict_types=1);

namespace Amelias\Models;

use Amelias\Database\Database;

/**
 * Thin data helper over customer_addresses (account portal address book).
 * Shipping addresses stay dormant until the DTC wine gate clears; billing may
 * feed Stripe/AVS. Every read/write is scoped to the owning customer.
 */
final class Address
{
    /** @return list<array<string,mixed>> */
    public static function forCustomer(int $customerId): array
    {
        return Database::fetchAll(
            'SELECT id, label, line1, line2, city, state, postal, purpose, is_default
               FROM customer_addresses
              WHERE customer_id = ?
              ORDER BY is_default DESC, id DESC',
            [$customerId]
        );
    }

    /** Add an address for a customer. Returns the new id. */
    public static function add(int $customerId, array $fields): int
    {
        return Database::insert(
            'INSERT INTO customer_addresses
                 (customer_id, label, line1, line2, city, state, postal, purpose)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $customerId,
                $fields['label'] ?? null,
                $fields['line1'] ?? '',
                $fields['line2'] ?? null,
                $fields['city'] ?? '',
                strtoupper((string) ($fields['state'] ?? '')),
                $fields['postal'] ?? '',
                in_array($fields['purpose'] ?? 'shipping', ['billing', 'shipping'], true)
                    ? $fields['purpose'] : 'shipping',
            ]
        );
    }

    /** Remove an address, scoped to the owning customer. */
    public static function remove(int $customerId, int $addressId): void
    {
        Database::run(
            'DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?',
            [$addressId, $customerId]
        );
    }
}
