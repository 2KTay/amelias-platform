<?php
/**
 * Feedback dashboard (screen 25, 5.1 admin half), Manager + Owner.
 *
 * @var int|null $rating
 * @var list<array<string,mixed>> $feedback
 * @var list<array<string,mixed>> $alerts
 * @var list<array{day:string,count:int,avg:float}> $trend
 * @var array{avg:?float,total:int,response_rate:?int,open_alerts:int} $metrics
 */
$stars = static function (int $r): string {
    $r = max(0, min(5, $r));
    return '<span class="stars" role="img" aria-label="' . $r . ' out of 5">'
        . str_repeat('★', $r) . '<span class="stars__dim">' . str_repeat('★', 5 - $r) . '</span></span>';
};
$maxTrend = 0;
foreach ($trend as $t) { $maxTrend = max($maxTrend, $t['count']); }
?>
<div class="admin-head">
  <h1 class="admin-h1 disp">Feedback</h1>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="kpi__label">Avg rating</div><div class="kpi__value"><?= $metrics['avg'] !== null ? e((string) $metrics['avg']) : '–' ?></div></div>
  <div class="kpi"><div class="kpi__label">Response rate</div><div class="kpi__value"><?= $metrics['response_rate'] !== null ? e((string) $metrics['response_rate']) . '%' : '–' ?></div></div>
  <div class="kpi"><div class="kpi__label">Reviews (30d)</div><div class="kpi__value"><?= e((string) $metrics['total']) ?></div></div>
  <div class="kpi"><div class="kpi__label">Open alerts</div><div class="kpi__value"><?= e((string) $metrics['open_alerts']) ?></div></div>
</div>

<div class="admin-card mt-6">
  <h2 class="disp">Rating trend, last 30 days</h2>
  <?php if ($trend === []): ?>
    <p class="muted">No feedback in the last 30 days.</p>
  <?php else: ?>
    <div class="trend" role="img" aria-label="Daily feedback counts over the last 30 days">
      <?php foreach ($trend as $t): $bucket = $maxTrend > 0 ? max(1, (int) ceil($t['count'] / $maxTrend * 10)) : 1; ?>
        <div class="trend__bar h<?= e((string) $bucket) ?>" title="<?= e($t['day']) ?>: <?= e((string) $t['count']) ?> reviews, avg <?= e((string) $t['avg']) ?>"></div>
      <?php endforeach; ?>
    </div>
    <p class="muted mt-2">Bars show review volume per day; hover for the daily average rating.</p>
  <?php endif; ?>
</div>

<div class="admin-card">
  <div class="admin-head"><h2 class="disp mb-0">Service-recovery alerts</h2><span class="badge badge--danger"><?= e((string) $metrics['open_alerts']) ?> open</span></div>
  <?php if ($alerts === []): ?>
    <p class="muted">No open alerts. Nicely done.</p>
  <?php else: ?>
    <div class="recovery-grid mt-2">
      <?php foreach ($alerts as $al): ?>
        <div class="rcard">
          <div class="rcard__top"><?= $stars((int) $al['rating']) ?><span class="badge badge--danger">Low score</span></div>
          <div class="rcard__meta"><?= e((string) ($al['table_code'] ?? 'Online')) ?> · <?= e(fmt_date((string) $al['created_at'], 'M j')) ?> · <?= e(ucfirst((string) $al['status'])) ?></div>
          <p class="rcard__cmt"><?= e((string) ($al['comment'] ?? '(no comment)')) ?></p>
          <form class="rcard__act" method="post" action="<?= e(url('/admin/feedback')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="resolve">
            <input type="hidden" name="alert_id" value="<?= e((string) $al['id']) ?>">
            <label class="visually-hidden" for="note-<?= e((string) $al['id']) ?>">Resolution note</label>
            <input class="input" type="text" id="note-<?= e((string) $al['id']) ?>" name="note" placeholder="Resolution note (optional)">
            <button class="btn btn--sm btn--solid" type="submit">Mark resolved</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="admin-card">
  <div class="admin-head">
    <h2 class="disp mb-0">All feedback</h2>
    <form class="admin-head__actions" method="get" action="<?= e(url('/admin/feedback')) ?>">
      <label class="visually-hidden" for="rating-filter">Filter by rating</label>
      <select class="select" id="rating-filter" name="rating" onchange="this.form.submit()">
        <option value=""<?= $rating === null ? ' selected' : '' ?>>All ratings</option>
        <?php for ($i = 5; $i >= 1; $i--): ?>
          <option value="<?= e((string) $i) ?>"<?= $rating === $i ? ' selected' : '' ?>><?= e((string) $i) ?> star<?= $i > 1 ? 's' : '' ?></option>
        <?php endfor; ?>
      </select>
    </form>
  </div>
  <table class="admin-tbl mt-2">
    <thead><tr><th scope="col">Rating</th><th scope="col">Comment</th><th scope="col">Table / Source</th><th scope="col">Date</th><th scope="col">Status</th></tr></thead>
    <tbody>
      <?php if ($feedback === []): ?><tr><td data-label="Rating" class="muted">No feedback to show.</td></tr>
      <?php else: foreach ($feedback as $f):
        $st = $f['alert_status'] ?? null;
        if ($st === 'resolved') { $badge = '<span class="badge badge--ok">Recovered</span>'; }
        elseif ($st !== null) { $badge = '<span class="badge badge--warn">In recovery</span>'; }
        else { $badge = '<span class="badge badge--ok">Published</span>'; }
      ?>
        <tr>
          <td data-label="Rating"><?= $stars((int) $f['rating']) ?></td>
          <td data-label="Comment"><?= e((string) ($f['comment'] ?? '')) ?></td>
          <td data-label="Table / Source"><?= e((string) ($f['table_code'] ?? 'Online')) ?></td>
          <td data-label="Date"><?= e(fmt_date((string) $f['created_at'], 'M j')) ?></td>
          <td data-label="Status"><?= $badge ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
