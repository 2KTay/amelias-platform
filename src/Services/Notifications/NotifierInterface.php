<?php

declare(strict_types=1);

namespace Amelias\Services\Notifications;

/**
 * One channel adapter (email or SMS). Adapters never decide policy (send-once,
 * channel choice), that's the Notifier's job; they just deliver.
 */
interface NotifierInterface
{
    /** Deliver a message. Returns true on success. Throws/queues handled by caller. */
    public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): bool;

    /** True when this channel is configured + enabled. */
    public function isEnabled(): bool;

    public function channel(): string;
}
