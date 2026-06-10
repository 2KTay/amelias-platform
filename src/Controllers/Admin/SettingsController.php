<?php

declare(strict_types=1);

namespace Amelias\Controllers\Admin;

use Amelias\Database\Database;
use Amelias\Services\Settings;
use Amelias\Support\Session;
use Amelias\Http\Request;

/**
 * Self-service Settings & Integrations (screen 28), Owner only.
 *
 * Secrets are stored encrypted (Settings/Crypto), masked in the UI, and a blank
 * field on save means "keep the current value". Changes are written to audit_log.
 */
final class SettingsController extends AdminController
{
    /** Editable secret fields (encrypted at rest). */
    private const SECRETS = [
        'stripe.secret_key' => 'Stripe secret key',
        'stripe.publishable_key' => 'Stripe publishable key',
        'stripe.webhook_secret' => 'Stripe webhook signing secret',
        'mail.sendgrid_key' => 'SendGrid API key',
        'twilio.sid' => 'Twilio Account SID',
        'twilio.token' => 'Twilio Auth Token',
        'google.places_key' => 'Google Places API key',
        'recaptcha.secret' => 'reCAPTCHA secret',
    ];

    /** Editable plain (non-secret) business config. */
    private const PLAIN = [
        // Payments (non-secret toggle).
        'stripe.live_mode' => 'Stripe live mode',
        // Contact / from.
        'business.phone' => 'Phone',
        'business.email' => 'Contact email',
        'mail.from' => 'From email (notifications)',
        'twilio.from' => 'SMS from number',
        'google.ga4_id' => 'GA4 measurement ID',
        // Structured hours grid (one key per day/edge).
        'business.hours.mon_open' => 'Monday open',
        'business.hours.mon_close' => 'Monday close',
        'business.hours.tue_open' => 'Tuesday open',
        'business.hours.tue_close' => 'Tuesday close',
        'business.hours.wed_open' => 'Wednesday open',
        'business.hours.wed_close' => 'Wednesday close',
        'business.hours.thu_open' => 'Thursday open',
        'business.hours.thu_close' => 'Thursday close',
        'business.hours.fri_open' => 'Friday open',
        'business.hours.fri_close' => 'Friday close',
        'business.hours.sat_open' => 'Saturday open',
        'business.hours.sat_close' => 'Saturday close',
        'business.hours.sun_open' => 'Sunday open',
        'business.hours.sun_close' => 'Sunday close',
        // Legacy free-text hours (still saved if present, no longer surfaced as an input).
        'business.hours' => 'Hours (display)',
        // Tax, AZ TPT rates by category.
        'tax.prepared_food_pct' => 'Prepared food tax %',
        'tax.retail_pct' => 'Retail tax %',
        // Ordering & service.
        'pickup.slot_capacity' => 'Pickup slot capacity',
        'pickup.lead_minutes' => 'Pickup prep lead (minutes)',
        'ordering.service_fee_pct' => 'Service fee %',
        'reservation.deposit_threshold' => 'Reservation deposit party-size threshold',
        'reservation.deposit_cents' => 'Reservation deposit (cents)',
        'reservation.cancel_cutoff_hours' => 'Reservation cancel cutoff (hours)',
        'business.closures' => 'Closures / holiday dates',
        // Feedback / tips.
        'feedback.review_url' => 'Google review URL',
        'feedback.low_score_threshold' => 'Low-score alert threshold',
        'tip.presets' => 'Tip presets (comma %)',
    ];

    public function index(Request $request): void
    {
        $user = $this->requireRole('owner');

        $saved = false;
        if ($request->isPost() && csrf_verify((string) $request->input('_csrf'))) {
            foreach (self::PLAIN as $key => $_label) {
                // Checkbox: unchecked boxes don't POST, so coerce presence to 1/0.
                if ($key === 'stripe.live_mode') {
                    Settings::set($key, $request->input($key) !== null ? '1' : '0', false, (int) $user['id']);
                    continue;
                }
                $val = $request->input($key);
                if ($val !== null) {
                    Settings::set($key, trim((string) $val), false, (int) $user['id']);
                }
            }
            foreach (self::SECRETS as $key => $_label) {
                $val = (string) $request->input($key);
                // Blank = keep existing (don't overwrite with the mask).
                if ($val !== '' && !str_contains($val, '•')) {
                    Settings::set($key, $val, true, (int) $user['id']);
                }
            }
            Database::run(
                'INSERT INTO audit_log (actor_type, actor_id, action, entity_type) VALUES (?,?,?,?)',
                ['user', $user['id'], 'settings.update', 'settings']
            );
            $saved = true;
        }

        $secretState = [];
        foreach (self::SECRETS as $key => $label) {
            $secretState[$key] = ['label' => $label, 'configured' => Settings::isConfigured($key), 'masked' => Settings::masked($key)];
        }
        $plainState = [];
        foreach (self::PLAIN as $key => $label) {
            $plainState[$key] = ['label' => $label, 'value' => Settings::get($key, '')];
        }

        $this->viewAdmin('admin/settings', [
            'title'   => 'Settings & Integrations',
            'styles'  => ['pages/admin-settings.css'],
            'saved'   => $saved,
            'secrets' => $secretState,
            'plain'   => $plainState,
        ]);
    }

    /** Server-side connection test (no secret exposed to the client). */
    public function test(Request $request): void
    {
        $this->requireRole('owner');
        if (!csrf_verify((string) $request->input('_csrf'))) {
            $this->json(['ok' => false, 'message' => 'Session expired.'], 419);
            return;
        }
        $integration = (string) $request->input('integration');
        $result = match ($integration) {
            'stripe' => $this->testStripe(),
            default  => ['ok' => false, 'message' => 'Unknown integration.'],
        };
        $this->json($result);
    }

    private function testStripe(): array
    {
        $key = Settings::get('stripe.secret_key');
        if ($key === null || $key === '') {
            return ['ok' => false, 'message' => 'No Stripe secret key configured.'];
        }
        if (!class_exists(\Stripe\StripeClient::class)) {
            return ['ok' => false, 'message' => 'Stripe SDK not installed yet (composer install on deploy).'];
        }
        try {
            $client = new \Stripe\StripeClient($key);
            $client->balance->retrieve();
            return ['ok' => true, 'message' => 'Connected to Stripe.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Stripe error: ' . $e->getMessage()];
        }
    }
}
