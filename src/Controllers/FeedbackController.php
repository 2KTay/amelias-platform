<?php

declare(strict_types=1);

namespace Amelias\Controllers;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Services\Auth;
use Amelias\Services\Settings;

/**
 * Public QR feedback (screen 16).
 *
 * A tabletop QR carries ?table=<code>; the reflected code is always escaped on
 * render (XSS guard). The form takes a 1–5 star rating + optional comment and
 * contact. COMPLIANCE: the Google review link is shown to EVERYONE on submit,
 * regardless of rating, never gated. A low score (<= threshold) additionally
 * opens a feedback_alerts row for staff service recovery. The POST is
 * rate-limited per IP via Auth::rateLimit.
 */
final class FeedbackController extends Controller
{
    /** Max feedback submissions per IP per window. */
    private const RATE_MAX = 5;
    private const RATE_WINDOW = 600; // 10 minutes

    public function index(Request $request): void
    {
        if ($request->isPost()) {
            $this->submit($request);
            return;
        }

        // Reflected table code is escaped at render time in the template via e().
        $table = $this->cleanTable((string) ($request->query('table', '') ?? ''));

        $this->viewPublic('public/feedback', [
            'title'     => 'How was your visit?',
            'styles'    => ['pages/feedback.css'],
            'table'     => $table,
            'reviewUrl' => $this->reviewUrl(),
            'submitted' => false,
            'error'     => null,
        ]);
    }

    private function submit(Request $request): void
    {
        $table = $this->cleanTable((string) ($request->input('table', '') ?? ''));

        if (!csrf_verify((string) $request->input('_csrf'))) {
            $this->renderForm($table, 'Your session expired. Please try again.');
            return;
        }

        // Per-IP throttle so the public endpoint can't be spammed.
        if (!Auth::rateLimit('feedback:' . $request->ip(), self::RATE_MAX, self::RATE_WINDOW)) {
            $this->renderForm($table, 'Thanks, we have your feedback. Please try again later.');
            return;
        }

        $rating = (int) $request->input('rating', 0);
        if ($rating < 1 || $rating > 5) {
            $this->renderForm($table, 'Please tap a star to rate before sending.');
            return;
        }

        $comment = trim((string) $request->input('comment', ''));
        $comment = $comment !== '' ? mb_substr($comment, 0, 2000) : null;
        $email = trim((string) $request->input('email', ''));

        $customerId = null;
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $name = trim((string) $request->input('name', ''));
            $customer = Auth::findOrCreateCustomer($email, [
                'first_name' => $name !== '' ? $name : null,
            ]);
            $customerId = (int) ($customer['id'] ?? 0) ?: null;
        }

        $feedbackId = Database::insert(
            'INSERT INTO feedback (rating, comment, table_code, customer_id) VALUES (?, ?, ?, ?)',
            [$rating, $comment, $table !== '' ? $table : null, $customerId]
        );

        // Low score -> open a service-recovery alert for staff.
        if ($rating <= $this->lowScoreThreshold()) {
            Database::run(
                "INSERT INTO feedback_alerts (feedback_id, status) VALUES (?, 'open')",
                [$feedbackId]
            );
        }

        // Thank-you state, the Google review link shows to EVERYONE.
        $this->viewPublic('public/feedback', [
            'title'     => 'Thank you',
            'styles'    => ['pages/feedback.css'],
            'table'     => $table,
            'reviewUrl' => $this->reviewUrl(),
            'submitted' => true,
            'rating'    => $rating,
            'error'     => null,
        ]);
    }

    private function renderForm(string $table, string $error): void
    {
        $this->viewPublic('public/feedback', [
            'title'     => 'How was your visit?',
            'styles'    => ['pages/feedback.css'],
            'table'     => $table,
            'reviewUrl' => $this->reviewUrl(),
            'submitted' => false,
            'error'     => $error,
        ]);
    }

    /** Normalize the QR table code: trim + cap length (escaped again on render). */
    private function cleanTable(string $raw): string
    {
        return mb_substr(trim($raw), 0, 40);
    }

    private function reviewUrl(): string
    {
        return (string) (Settings::get('feedback.review_url', '') ?? '');
    }

    private function lowScoreThreshold(): int
    {
        return max(1, (int) (Settings::get('feedback.low_score_threshold', '3') ?? '3'));
    }
}
