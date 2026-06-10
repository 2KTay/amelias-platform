<?php
/**
 * Admin dashboard (screen 19). Role-filtered KPIs + live operations overview.
 * Financial figures are owner/manager only ($canSeeMoney).
 *
 * @var bool $canSeeMoney
 * @var int $ordersToday
 * @var int $revenueToday
 * @var int $openOrders
 * @var int $covers
 * @var int|string|null $avgFeedback
 * @var int $feedbackCount
 * @var list<array{stream:string,revenue_cents:int,pct:int}> $revenueStreams
 * @var list<array<string,mixed>> $queue
 * @var list<array<string,mixed>> $reservations
 * @var list<array<string,mixed>> $lowStock
 * @var list<array<string,mixed>> $recentFeedback
 */
$statusBadge = static function (string $s): string {
    return match ($s) {
        'paid'      => 'badge--new',
        'preparing' => 'badge--warn',
        'ready'     => 'badge--ok',
        default     => '',
    };
};
?>
<div class="admin-head">
  <div class="admin-head__title">
    <h1 class="admin-h1 disp">Dashboard</h1>
    <p class="muted dash-sub">Today at a glance.</p>
  </div>
</div>

<div class="kpi-grid">
  <?php if ($canSeeMoney): ?>
    <div class="kpi"><div class="kpi__label">Revenue today</div><div class="kpi__value"><?= e(fmt_money((int) $revenueToday)) ?></div></div>
  <?php else: ?>
    <div class="kpi"><div class="kpi__label">Open orders</div><div class="kpi__value"><?= e((string) $openOrders) ?></div></div>
  <?php endif; ?>
  <div class="kpi"><div class="kpi__label">Orders today</div><div class="kpi__value"><?= e((string) $ordersToday) ?></div></div>
  <div class="kpi"><div class="kpi__label">Covers</div><div class="kpi__value"><?= e((string) $covers) ?></div></div>
  <div class="kpi"><div class="kpi__label">Avg rating (30d)</div><div class="kpi__value"><?= $avgFeedback !== null ? e((string) $avgFeedback) . '★' : '–' ?><span class="kpi__sub"><?= e((string) $feedbackCount) ?> reviews</span></div></div>
</div>

<?php if ($canSeeMoney): ?>
<!-- REVENUE BY STREAM -->
<div class="admin-card mt-6">
  <div class="admin-head">
    <h2 class="disp mb-0">Revenue by stream</h2>
    <a class="dash-link" href="<?= e(url('/admin/reports')) ?>">Full report &rarr;</a>
  </div>
  <?php if ($revenueStreams === []): ?>
    <p class="muted mt-2">No revenue recorded yet today.</p>
  <?php else: ?>
    <ul class="dash-streams mt-2">
      <?php foreach ($revenueStreams as $s): $pct = max(0, min(100, (int) $s['pct'])); $bucket = (int) (round($pct / 5) * 5); ?>
        <li class="dash-stream">
          <div class="dash-stream__head">
            <span class="dash-stream__label"><?= e((string) $s['stream']) ?></span>
            <span class="dash-stream__val num"><?= e(fmt_money((int) $s['revenue_cents'])) ?> · <?= e((string) $pct) ?>%</span>
          </div>
          <div class="dash-stream__track" role="img" aria-label="<?= e((string) $s['stream']) ?>: <?= e((string) $pct) ?>% of today's revenue">
            <span class="dash-stream__bar dash-stream__bar--w<?= e((string) $bucket) ?>"></span>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="dash-cols mt-6">
  <!-- LIVE ORDER QUEUE -->
  <div class="admin-card mb-0">
    <div class="admin-head">
      <h2 class="disp mb-0">Live order queue</h2>
      <a class="dash-link" href="<?= e(url('/admin/orders')) ?>">View all &rarr;</a>
    </div>
    <table class="admin-tbl mt-2">
      <thead>
        <tr><th scope="col">#</th><th scope="col">Slot</th><th scope="col">Customer</th><?php if ($canSeeMoney): ?><th scope="col">Total</th><?php endif; ?><th scope="col">Status</th></tr>
      </thead>
      <tbody>
        <?php if ($queue === []): ?>
          <tr><td data-label="#" class="muted">No active orders.</td></tr>
        <?php else: foreach ($queue as $o): $st = (string) $o['status']; ?>
          <tr>
            <td data-label="#">#<?= e((string) $o['id']) ?></td>
            <td data-label="Slot" class="num"><?= $o['slot_start'] !== null ? e(fmt_date((string) $o['slot_start'], 'g:i A')) : 'ASAP' ?></td>
            <td data-label="Customer"><?= e((string) ($o['contact_name'] ?: 'Guest')) ?></td>
            <?php if ($canSeeMoney): ?><td data-label="Total" class="num"><?= e(fmt_money((int) $o['total_cents'])) ?></td><?php endif; ?>
            <td data-label="Status"><span class="badge <?= e($statusBadge($st)) ?>"><?= e(ucfirst(str_replace('_', ' ', $st))) ?></span></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- UPCOMING RESERVATIONS -->
  <div class="admin-card mb-0">
    <div class="admin-head">
      <h2 class="disp mb-0">Upcoming reservations</h2>
      <a class="dash-link" href="<?= e(url('/admin/reservations')) ?>">Open book &rarr;</a>
    </div>
    <?php if ($reservations === []): ?>
      <p class="muted mt-2">No reservations left today.</p>
    <?php else: ?>
      <ul class="dash-rez mt-2">
        <?php foreach ($reservations as $r): ?>
          <li>
            <span class="dash-rez__time num"><?= e(fmt_date((string) $r['slot_start'], 'g:i A')) ?></span>
            <span class="dash-rez__name"><?= e((string) ($r['contact_name'] ?? 'Guest')) ?> · <span class="muted">party of <?= (int) $r['party_size'] ?></span></span>
            <span class="badge"><?= $r['table_label'] !== null ? e((string) $r['table_label']) : e(ucfirst((string) $r['status'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<!-- LOW STOCK ALERTS -->
<?php if ($lowStock !== []): ?>
  <div class="dash-alert mt-6">
    <span class="badge badge--warn">Low stock</span>
    <span class="dash-alert__lead"><?= e((string) count($lowStock)) ?> item<?= count($lowStock) === 1 ? '' : 's' ?> need attention:</span>
    <?php foreach ($lowStock as $i => $ls): ?>
      <?php if ($i > 0): ?><span aria-hidden="true">·</span><?php endif; ?>
      <span><?= e((string) $ls['name']) ?> (<?= e((string) (int) $ls['reservable']) ?> left)</span>
    <?php endforeach; ?>
    <a class="dash-link dash-alert__cta" href="<?= e(url('/admin/inventory')) ?>">Manage inventory &rarr;</a>
  </div>
<?php endif; ?>

<!-- RECENT FEEDBACK -->
<div class="admin-card mt-6">
  <div class="admin-head">
    <h2 class="disp mb-0">Recent feedback</h2>
    <a class="dash-link" href="<?= e(url('/admin/feedback')) ?>">All feedback &rarr;</a>
  </div>
  <?php if ($recentFeedback === []): ?>
    <p class="muted mt-2">No feedback yet.</p>
  <?php else: ?>
    <ul class="dash-fb mt-2">
      <?php foreach ($recentFeedback as $f): $rt = max(0, min(5, (int) $f['rating'])); ?>
        <?php
        $first = trim((string) ($f['first_name'] ?? ''));
        $last  = trim((string) ($f['last_name'] ?? ''));
        $name  = $first !== '' ? $first . ($last !== '' ? ' ' . mb_substr($last, 0, 1) . '.' : '') : 'Anonymous';
        ?>
        <li>
          <div class="dash-fb__top">
            <span class="dash-fb__name"><?= e($name) ?></span>
            <span class="stars" role="img" aria-label="<?= e((string) $rt) ?> out of 5"><?= str_repeat('★', $rt) ?><span class="stars__dim"><?= str_repeat('★', 5 - $rt) ?></span></span>
          </div>
          <div class="dash-fb__meta muted"><?= e(fmt_date((string) $f['created_at'], 'M j')) ?></div>
          <?php if (!empty($f['comment'])): ?><p class="dash-fb__txt"><?= e((string) $f['comment']) ?></p><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
