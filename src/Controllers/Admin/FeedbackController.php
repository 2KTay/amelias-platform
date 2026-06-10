<?php

declare(strict_types=1);

namespace Amelias\Controllers\Admin;

use Amelias\Database\Database;
use Amelias\Http\Request;

/**
 * Feedback dashboard (screen 25, 5.1 admin half), Manager + Owner.
 *
 * Feedback list with rating filter, a 30-day rating trend (avg + count by day),
 * a response-rate metric, the service-recovery alert queue (open feedback_alerts),
 * mark-resolved, and a respond note. Mutations are CSRF-guarded POSTs (PRG).
 */
final class FeedbackController extends AdminController
{
    public function index(Request $request): void
    {
        $user = $this->requireRole('owner', 'manager');

        if ($request->isPost()) {
            if (!csrf_verify((string) $request->input('_csrf'))) {
                $this->redirect(url('/admin/feedback'));
                return;
            }
            $action  = (string) $request->input('action');
            $alertId = (int) $request->input('alert_id');
            if ($alertId > 0) {
                match ($action) {
                    'resolve' => $this->resolve($alertId, (string) $request->input('note'), (int) $user['id']),
                    'ack'     => $this->acknowledge($alertId, (int) $user['id']),
                    default   => null,
                };
            }
            $this->redirect(url('/admin/feedback'));
            return;
        }

        $ratingFilter = $request->query('rating');
        $rating = $ratingFilter !== null && $ratingFilter !== '' ? (int) $ratingFilter : null;

        $this->viewAdmin('admin/feedback', [
            'title'        => 'Feedback',
            'styles'       => ['pages/admin-depth.css'],
            'rating'       => $rating,
            'feedback'     => $this->list($rating),
            'alerts'       => $this->openAlerts(),
            'trend'        => $this->trend(),
            'metrics'      => $this->metrics(),
        ]);
    }

    /**
     * Feedback list, optionally filtered by exact rating.
     *
     * @return list<array<string,mixed>>
     */
    private function list(?int $rating): array
    {
        if ($rating !== null) {
            return Database::fetchAll(
                "SELECT f.id, f.rating, f.comment, f.table_code, f.created_at,
                        al.status AS alert_status
                   FROM feedback f
                   LEFT JOIN feedback_alerts al ON al.feedback_id = f.id
                  WHERE f.rating = ?
                  ORDER BY f.created_at DESC LIMIT 100",
                [$rating]
            );
        }
        return Database::fetchAll(
            "SELECT f.id, f.rating, f.comment, f.table_code, f.created_at,
                    al.status AS alert_status
               FROM feedback f
               LEFT JOIN feedback_alerts al ON al.feedback_id = f.id
              ORDER BY f.created_at DESC LIMIT 100"
        );
    }

    /**
     * Open service-recovery alerts joined to their feedback.
     *
     * @return list<array<string,mixed>>
     */
    private function openAlerts(): array
    {
        return Database::fetchAll(
            "SELECT al.id, al.status, al.created_at,
                    f.rating, f.comment, f.table_code
               FROM feedback_alerts al
               JOIN feedback f ON f.id = al.feedback_id
              WHERE al.status IN ('open','acknowledged')
              ORDER BY al.created_at ASC"
        );
    }

    /**
     * 30-day rating trend: count + average by Phoenix day.
     *
     * @return list<array{day:string,count:int,avg:float}>
     */
    private function trend(): array
    {
        $rows = Database::fetchAll(
            "SELECT DATE(CONVERT_TZ(created_at, '+00:00', '-07:00')) AS day,
                    COUNT(*) AS cnt, AVG(rating) AS avg_rating
               FROM feedback
              WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY
              GROUP BY day ORDER BY day ASC"
        );
        return array_map(static fn (array $r): array => [
            'day'   => (string) $r['day'],
            'count' => (int) $r['cnt'],
            'avg'   => round((float) $r['avg_rating'], 1),
        ], $rows);
    }

    /**
     * Headline metrics: 30-day avg rating, total reviews, response rate
     * (share of alerts resolved), and open-alert count.
     *
     * @return array{avg:?float,total:int,response_rate:?int,open_alerts:int}
     */
    private function metrics(): array
    {
        $avg = Database::fetchValue(
            'SELECT ROUND(AVG(rating),1) FROM feedback WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY'
        );
        $total = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM feedback WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY'
        );
        $alertsTotal = (int) Database::fetchValue('SELECT COUNT(*) FROM feedback_alerts');
        $alertsResolved = (int) Database::fetchValue("SELECT COUNT(*) FROM feedback_alerts WHERE status = 'resolved'");
        $openAlerts = (int) Database::fetchValue("SELECT COUNT(*) FROM feedback_alerts WHERE status IN ('open','acknowledged')");

        return [
            'avg'           => $avg !== null ? (float) $avg : null,
            'total'         => $total,
            'response_rate' => $alertsTotal > 0 ? (int) round($alertsResolved / $alertsTotal * 100) : null,
            'open_alerts'   => $openAlerts,
        ];
    }

    private function resolve(int $alertId, string $note, int $userId): void
    {
        Database::run(
            "UPDATE feedback_alerts
                SET status = 'resolved', assigned_to = ?, resolved_at = UTC_TIMESTAMP(), resolution_note = ?
              WHERE id = ?",
            [$userId, $note !== '' ? mb_substr($note, 0, 500) : null, $alertId]
        );
        Database::run(
            'INSERT INTO audit_log (actor_type, actor_id, action, entity_type, entity_id)
             VALUES (?, ?, ?, ?, ?)',
            ['user', $userId, 'feedback.resolve', 'feedback_alert', (string) $alertId]
        );
    }

    private function acknowledge(int $alertId, int $userId): void
    {
        Database::run(
            "UPDATE feedback_alerts SET status = 'acknowledged', assigned_to = ? WHERE id = ? AND status = 'open'",
            [$userId, $alertId]
        );
    }
}
