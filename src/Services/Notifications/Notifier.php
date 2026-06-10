<?php

declare(strict_types=1);

namespace Amelias\Services\Notifications;

use Amelias\Database\Database;
use Amelias\Http\Response;

/**
 * Notification orchestrator. Decides channels (customer choice + enabled
 * adapters, email as the always-available fallback), enforces send-once via
 * notification_log, renders templates, and queues for retry on failure.
 */
final class Notifier
{
    /** @var array<string,NotifierInterface> */
    private array $adapters;

    public function __construct(?array $adapters = null)
    {
        $this->adapters = $adapters ?? [
            'email' => new EmailAdapter(),
            'sms'   => new SmsAdapter(),
        ];
    }

    /**
     * Send a transactional notification exactly once per (entity, template,
     * channel). $channelPref is the customer's notify_channel (email|sms|both).
     *
     * @param array<string,mixed> $data
     */
    public function notify(
        string $entityType,
        string $entityId,
        string $template,
        array $recipients,           // ['email' => addr, 'sms' => phone]
        array $data = [],
        string $channelPref = 'email'
    ): void {
        foreach ($this->resolveChannels($channelPref) as $channel) {
            $to = $recipients[$channel] ?? null;
            if ($to === null || $to === '') {
                continue;
            }
            if ($this->alreadySent($entityType, $entityId, $template, $channel)) {
                continue;
            }
            $rendered = $this->render($template, $channel, $data);
            $adapter = $this->adapters[$channel];

            $ok = false;
            try {
                $ok = $adapter->send($to, $rendered['subject'], $rendered['text'], $rendered['html'] ?? null);
            } catch (\Throwable $e) {
                error_log('[notifier] ' . $e->getMessage());
            }

            if ($ok) {
                $this->logSent($entityType, $entityId, $template, $channel);
            } else {
                $this->enqueue($channel, $to, $template, $data);
            }
        }
    }

    /** Resolve the ordered channel list honoring preference + enabled adapters. */
    private function resolveChannels(string $pref): array
    {
        $wanted = match ($pref) {
            'sms'  => ['sms', 'email'],   // email remains the fallback
            'both' => ['email', 'sms'],
            default => ['email'],
        };
        return array_values(array_filter($wanted, fn ($c) => isset($this->adapters[$c]) && $this->adapters[$c]->isEnabled()))
            ?: ['email']; // never fully depend on SMS
    }

    private function alreadySent(string $type, string $id, string $template, string $channel): bool
    {
        return (bool) Database::fetchValue(
            'SELECT 1 FROM notification_log WHERE entity_type=? AND entity_id=? AND template=? AND channel=?',
            [$type, $id, $template, $channel]
        );
    }

    private function logSent(string $type, string $id, string $template, string $channel): void
    {
        // Unique key guarantees send-once even under a race; ignore dup errors.
        try {
            Database::run(
                'INSERT INTO notification_log (entity_type, entity_id, template, channel) VALUES (?,?,?,?)',
                [$type, $id, $template, $channel]
            );
        } catch (\Throwable) {
            // unique violation = someone else logged it; fine.
        }
    }

    private function enqueue(string $channel, string $to, string $template, array $data): void
    {
        Database::run(
            'INSERT INTO notification_queue (channel, category, recipient, template, payload_json, status) VALUES (?,?,?,?,?,?)',
            [$channel, 'transactional', $to, $template, json_encode($data, JSON_UNESCAPED_SLASHES), 'queued']
        );
    }

    /**
     * Render an email/sms template. Email templates live in templates/emails/
     * and set $subject; the text part is derived from the HTML.
     *
     * @return array{subject:string,html:?string,text:string}
     */
    public function render(string $template, string $channel, array $data): array
    {
        $response = new Response();
        $file = ROOT_PATH . '/templates/emails/' . $template . '.php';
        if (is_file($file)) {
            $html = $response->capture('emails/layout', $data + [
                'innerTemplate' => 'emails/' . $template,
            ]);
        } else {
            $html = '<p>' . e((string) ($data['message'] ?? '')) . '</p>';
        }
        $subject = (string) ($data['subject'] ?? (config('name') . ' notification'));
        $text = trim(html_entity_decode(strip_tags(preg_replace('/<\/(p|div|h\d|li|tr)>/i', "\n", $html) ?? ''), ENT_QUOTES));

        return ['subject' => (string) $subject, 'html' => $channel === 'email' ? $html : null, 'text' => $text];
    }

    /** Drain the retry queue (called by cron/drain_notifications.php). */
    public function drain(int $limit = 50): int
    {
        $rows = Database::fetchAll(
            "SELECT * FROM notification_queue WHERE status IN ('queued','failed') AND attempts < 5 AND available_at <= UTC_TIMESTAMP() ORDER BY id LIMIT {$limit}"
        );
        $sent = 0;
        foreach ($rows as $row) {
            $channel = $row['channel'];
            if (!isset($this->adapters[$channel]) || !$this->adapters[$channel]->isEnabled()) {
                continue;
            }
            $data = json_decode((string) $row['payload_json'], true) ?: [];
            $rendered = $this->render($row['template'], $channel, $data);
            $ok = false;
            try {
                $ok = $this->adapters[$channel]->send($row['recipient'], $rendered['subject'], $rendered['text'], $rendered['html'] ?? null);
            } catch (\Throwable $e) {
                error_log('[notifier:drain] ' . $e->getMessage());
            }
            if ($ok) {
                Database::run("UPDATE notification_queue SET status='sent', sent_at=UTC_TIMESTAMP() WHERE id=?", [$row['id']]);
                $sent++;
            } else {
                Database::run("UPDATE notification_queue SET status='failed', attempts=attempts+1, available_at=UTC_TIMESTAMP()+INTERVAL POW(2,attempts) MINUTE WHERE id=?", [$row['id']]);
            }
        }
        return $sent;
    }
}
