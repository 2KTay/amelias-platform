<?php

declare(strict_types=1);

namespace Amelias\Controllers;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Services\Payments\PaymentStateMachine;
use Amelias\Services\Settings;

/**
 * Stripe webhook endpoint, the source of truth for payment state.
 *
 * Flow:
 *   1. Read the raw body + Stripe-Signature header.
 *   2. Verify the signature (SDK \Stripe\Webhook::constructEvent when present,
 *      else a manual HMAC-SHA256 fallback). Bad signature => 400.
 *   3. Idempotency: INSERT IGNORE into webhook_events keyed on the unique
 *      event_id. If the row already existed, return 200 immediately (no-op).
 *   4. Dispatch by event type to the PaymentStateMachine.
 *   5. Mark the event processed and return 200. Retryable internal errors
 *      return 500 so Stripe retries; unknown event types are accepted (200).
 *
 * Secrets are never echoed.
 */
final class StripeWebhookController extends Controller
{
    /** Tolerance (seconds) for the manual signature timestamp check. */
    private const SIGNATURE_TOLERANCE = 300;

    public function handle(Request $request): void
    {
        $payload   = (string) file_get_contents('php://input');
        $sigHeader = (string) ($request->header('Stripe-Signature') ?? '');
        $secret    = (string) (Settings::get('stripe.webhook_secret') ?? '');

        if ($secret === '') {
            // Cannot verify anything without the signing secret, refuse.
            http_response_code(400);
            return;
        }

        $event = $this->verify($payload, $sigHeader, $secret);
        if ($event === null) {
            http_response_code(400);
            return;
        }

        $eventId = (string) ($event['id'] ?? '');
        $type    = (string) ($event['type'] ?? '');
        if ($eventId === '' || $type === '') {
            http_response_code(400);
            return;
        }

        // Idempotency gate: first writer wins; replays are no-ops.
        $inserted = Database::affected(
            'INSERT IGNORE INTO webhook_events (event_id, type, status, payload)
             VALUES (?, ?, ?, ?)',
            [$eventId, $type, 'received', $payload]
        );
        if ($inserted === 0) {
            http_response_code(200);
            return;
        }

        try {
            $this->dispatch($type, $event);
        } catch (\Throwable $e) {
            // Retryable: leave the event for Stripe to redeliver. Record why.
            Database::run(
                'UPDATE webhook_events SET status = ?, error_message = ? WHERE event_id = ?',
                ['failed', substr($e->getMessage(), 0, 500), $eventId]
            );
            http_response_code(500);
            return;
        }

        Database::run(
            'UPDATE webhook_events SET status = ?, processed_at = UTC_TIMESTAMP() WHERE event_id = ?',
            ['processed', $eventId]
        );
        http_response_code(200);
    }

    /**
     * Verify the signature and return the decoded event array, or null on
     * failure. Uses the Stripe SDK when available, else a manual HMAC check.
     *
     * @return array<string,mixed>|null
     */
    private function verify(string $payload, string $sigHeader, string $secret): ?array
    {
        if (class_exists('\\Stripe\\Webhook')) {
            try {
                $webhookClass = '\\Stripe\\Webhook';
                $event = $webhookClass::constructEvent($payload, $sigHeader, $secret);
                /** @var array<string,mixed> $decoded */
                $decoded = json_decode(json_encode($event, JSON_THROW_ON_ERROR) ?: '{}', true);
                return $decoded;
            } catch (\Throwable) {
                return null;
            }
        }

        if (!$this->verifyManual($payload, $sigHeader, $secret)) {
            return null;
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Manual Stripe signature verification (used when the SDK is absent).
     * Computes HMAC-SHA256 of "{timestamp}.{payload}" with the signing secret
     * and constant-time compares against each v1 signature in the header.
     */
    private function verifyManual(string $payload, string $sigHeader, string $secret): bool
    {
        if ($sigHeader === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $sigHeader) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) {
                continue;
            }
            [$k, $v] = $kv;
            if ($k === 't') {
                $timestamp = $v;
            } elseif ($k === 'v1') {
                $signatures[] = $v;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        // Reject stale timestamps outside the tolerance window (replay guard).
        if (abs(time() - (int) $timestamp) > self::SIGNATURE_TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Dispatch a verified event to the state machine.
     *
     * @param array<string,mixed> $event
     */
    private function dispatch(string $type, array $event): void
    {
        $object = $this->eventObject($event);
        $machine = new PaymentStateMachine();

        switch ($type) {
            case 'payment_intent.succeeded':
                $intentId = (string) ($object['id'] ?? '');
                $orderId  = $this->resolveOrder($machine, $intentId, $object);
                if ($orderId !== null) {
                    $machine->markPaid($orderId, $intentId, (int) ($object['amount'] ?? 0));
                }
                break;

            case 'payment_intent.payment_failed':
                $intentId = (string) ($object['id'] ?? '');
                $orderId  = $this->resolveOrder($machine, $intentId, $object);
                if ($orderId !== null) {
                    $machine->markFailedOrExpired($orderId);
                }
                break;

            case 'charge.dispute.created':
                // Dispute object carries the payment_intent reference.
                $intentId = (string) ($object['payment_intent'] ?? '');
                $orderId  = $this->resolveOrder($machine, $intentId, $object);
                if ($orderId !== null) {
                    $machine->markDisputed($orderId);
                }
                break;

            case 'charge.refunded':
                $intentId  = (string) ($object['payment_intent'] ?? '');
                $orderId   = $this->resolveOrder($machine, $intentId, $object);
                if ($orderId !== null) {
                    $refunded = (int) ($object['amount_refunded'] ?? 0);
                    $captured = (int) ($object['amount_captured'] ?? ($object['amount'] ?? 0));
                    $partial  = $captured > 0 && $refunded < $captured;
                    $machine->markRefunded($orderId, $refunded, $partial);
                }
                break;

            default:
                // Unknown / unhandled event type, accept and ignore (no retry).
                break;
        }
    }

    /**
     * Extract the event's primary object (data.object).
     *
     * @param array<string,mixed> $event
     * @return array<string,mixed>
     */
    private function eventObject(array $event): array
    {
        $data = $event['data'] ?? [];
        if (is_array($data) && isset($data['object']) && is_array($data['object'])) {
            return $data['object'];
        }
        return [];
    }

    /**
     * Resolve the order id from the PaymentIntent id or metadata.order_id.
     *
     * @param array<string,mixed> $object
     */
    private function resolveOrder(PaymentStateMachine $machine, string $intentId, array $object): ?int
    {
        $metadataOrderId = null;
        $metadata = $object['metadata'] ?? null;
        if (is_array($metadata) && isset($metadata['order_id']) && is_numeric($metadata['order_id'])) {
            $metadataOrderId = (int) $metadata['order_id'];
        }
        return $machine->resolveOrderId($intentId !== '' ? $intentId : null, $metadataOrderId);
    }
}
