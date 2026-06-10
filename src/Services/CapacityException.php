<?php

declare(strict_types=1);

namespace Amelias\Services;

use RuntimeException;

/**
 * Thrown by HoldManager when a guarded atomic claim (pickup slot capacity,
 * finite stock, or promo redemption cap) cannot be satisfied. Carries a
 * machine-readable $kind and a customer-safe message so the checkout flow can
 * re-prompt without leaking internals. Always rolls back the transaction.
 */
final class CapacityException extends RuntimeException
{
    public function __construct(
        public readonly string $kind,
        string $message
    ) {
        parent::__construct($message);
    }
}
