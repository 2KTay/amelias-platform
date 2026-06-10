<?php

declare(strict_types=1);

namespace Amelias\Services\Notifications;

use Amelias\Services\Settings;

/**
 * SMS channel (Twilio). Gated behind the FEATURE_SMS flag + A2P 10DLC clearance
 * (Q#9). When disabled, isEnabled() is false so the Notifier silently routes to
 * email, no code change is needed when SMS flips on.
 */
final class SmsAdapter implements NotifierInterface
{
    public function channel(): string
    {
        return 'sms';
    }

    public function isEnabled(): bool
    {
        if (!(bool) config('features.sms_enabled', false)) {
            return false; // A2P not cleared yet (Q#9)
        }
        return Settings::isConfigured('twilio.sid')
            && Settings::isConfigured('twilio.token')
            && (Settings::get('twilio.from') ?? '') !== '';
    }

    public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        $sid = (string) Settings::get('twilio.sid');
        $token = (string) Settings::get('twilio.token');
        $from = (string) Settings::get('twilio.from');

        $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => "{$sid}:{$token}",
            CURLOPT_POSTFIELDS => http_build_query(['To' => $to, 'From' => $from, 'Body' => $textBody]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            return true;
        }
        error_log('[sms] Twilio failed: HTTP ' . $code . ' ' . (is_string($resp) ? $resp : ''));
        return false;
    }
}
