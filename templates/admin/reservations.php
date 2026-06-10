<?php
/**
 * Host reservations dashboard (screen 21), day view of the service book with
 * seat / clear / turn floor actions, waitlist, deposit flags, and walk-ins.
 * Seating assigns a physical table from the floor (table_id NULL until seated).
 *
 * @var string $date
 * @var string $dayLabel
 * @var list<array<string,mixed>> $bookings
 * @var list<array<string,mixed>> $openTables
 * @var list<array<string,mixed>> $allTables
 * @var list<array<string,mixed>> $waitlist
 * @var int $covers
 */
$statusBadge = static function (string $s): string {
    return match ($s) {
        'confirmed' => '<span class="badge badge--warn">Confirmed</span>',
        'seated'    => '<span class="badge badge--ok">Seated</span>',
        'completed' => '<span class="badge">Completed</span>',
        'held'      => '<span class="badge">Held</span>',
        'no_show'   => '<span class="badge badge--danger">No-show</span>',
        default     => '<span class="badge">' . e(ucfirst($s)) . '</span>',
    };
};
$depositBadge = static function (?string $orderId, ?string $status): string {
    if ($orderId === null) {
        return '<span class="badge">None</span>';
    }
    return $status === 'paid'
        ? '<span class="badge badge--ok">Paid</span>'
        : '<span class="badge badge--warn">Pending</span>';
};
?>
<div class="admin-head">
  <h1 class="admin-h1 disp">Reservations</h1>
  <form method="get" action="<?= e(url('/admin/reservations')) ?>" class="resv-datepick">
    <input class="input" type="date" name="date" value="<?= e($date) ?>" aria-label="Service date" onchange="this.form.submit()">
  </form>
</div>

<div class="resv-dayhead">
  <a class="btn btn--sm" href="<?= e(url('/admin/reservations?date=' . rawurlencode($prevDate))) ?>" aria-label="Previous day">&lsaquo;</a>
  <h2 class="disp resv-dayhead__title"><?= e($dayLabel) ?></h2>
  <a class="btn btn--sm" href="<?= e(url('/admin/reservations?date=' . rawurlencode($nextDate))) ?>" aria-label="Next day">&rsaquo;</a>
  <span class="muted resv-dayhead__count"><?= (int) $covers ?> covers booked</span>
</div>

<div class="resv-layout">
  <div class="admin-card">
    <h2 class="disp">Service book</h2>
    <table class="admin-tbl mt-2">
      <thead>
        <tr>
          <th scope="col">Time</th><th scope="col">Party</th><th scope="col">Name</th>
          <th scope="col">Table</th><th scope="col">Status</th><th scope="col">Deposit</th>
          <th scope="col">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($bookings === []): ?>
          <tr><td data-label="Time" class="muted">No reservations for this day.</td></tr>
        <?php else: foreach ($bookings as $b): ?>
          <tr>
            <td data-label="Time" class="num"><?= e(fmt_date((string) $b['slot_start'], 'g:ia')) ?></td>
            <td data-label="Party" class="num"><?= (int) $b['party_size'] ?></td>
            <td data-label="Name">
              <?= e((string) ($b['contact_name'] ?? '–')) ?>
              <?php if (!empty($b['notes'])): ?><span class="muted resv-note"><?= e((string) $b['notes']) ?></span><?php endif; ?>
            </td>
            <td data-label="Table"><?= $b['table_label'] !== null ? '<span class="badge">' . e((string) $b['table_label']) . '</span>' : '<span class="muted">–</span>' ?></td>
            <td data-label="Status"><?= $statusBadge((string) $b['status']) ?></td>
            <td data-label="Deposit"><?= $depositBadge($b['deposit_order_id'] !== null ? (string) $b['deposit_order_id'] : null, $b['deposit_status'] !== null ? (string) $b['deposit_status'] : null) ?></td>
            <td data-label="Actions" class="resv-actions">
              <?php if (in_array($b['status'], ['confirmed', 'held'], true)): ?>
                <?php if ($b['status'] === 'held'): ?>
                  <form method="post" action="<?= e(url('/admin/reservations')) ?>" class="resv-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="date" value="<?= e($date) ?>">
                    <input type="hidden" name="action" value="confirm">
                    <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                    <button class="btn btn--sm" type="submit">Confirm</button>
                  </form>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/admin/reservations')) ?>" class="resv-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="date" value="<?= e($date) ?>">
                  <input type="hidden" name="action" value="seat">
                  <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                  <label class="visually-hidden" for="seat-<?= (int) $b['id'] ?>">Table for <?= e((string) $b['contact_name']) ?></label>
                  <select class="select select--sm" id="seat-<?= (int) $b['id'] ?>" name="table_id" required>
                    <option value="">Table…</option>
                    <?php foreach ($openTables as $t): ?>
                      <option value="<?= (int) $t['id'] ?>"><?= e((string) $t['label']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn--sm btn--solid" type="submit">Seat</button>
                </form>
              <?php elseif ($b['status'] === 'seated'): ?>
                <form method="post" action="<?= e(url('/admin/reservations')) ?>" class="resv-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="date" value="<?= e($date) ?>">
                  <input type="hidden" name="action" value="complete">
                  <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                  <button class="btn btn--sm" type="submit">Turn</button>
                </form>
              <?php endif; ?>
              <?php if (!in_array($b['status'], ['completed', 'no_show'], true)): ?>
                <form method="post" action="<?= e(url('/admin/reservations')) ?>" class="resv-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="date" value="<?= e($date) ?>">
                  <input type="hidden" name="action" value="no_show">
                  <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                  <button class="btn btn--sm btn--danger" type="submit">No-show</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="resv-side">
    <div class="admin-card">
      <h2 class="disp">Waitlist <span class="badge badge--warn"><?= count($waitlist) ?> waiting</span></h2>
      <?php if ($waitlist === []): ?>
        <p class="muted">No one waiting.</p>
      <?php else: ?>
        <ul class="resv-wait">
          <?php foreach ($waitlist as $w): ?>
            <li>
              <span>
                <strong><?= e((string) ($w['contact_name'] ?? 'Guest')) ?></strong>
                <span class="muted">party of <?= (int) $w['party_size'] ?> · <?= e($w['status'] === 'offered' ? 'offered' : 'waiting') ?></span>
              </span>
              <form method="post" action="<?= e(url('/admin/reservations')) ?>" class="resv-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="date" value="<?= e($date) ?>">
                <input type="hidden" name="action" value="waitlist_seat">
                <input type="hidden" name="waitlist_id" value="<?= (int) $w['id'] ?>">
                <button class="btn btn--sm" type="submit">Seated</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <form method="post" action="<?= e(url('/admin/reservations')) ?>" class="resv-addwait mt-2">
        <?= csrf_field() ?>
        <input type="hidden" name="date" value="<?= e($date) ?>">
        <input type="hidden" name="action" value="waitlist_add">
        <input class="input input--sm" type="text" name="name" placeholder="Name" aria-label="Waitlist name">
        <input class="input input--sm" type="number" name="party" value="2" min="1" max="20" aria-label="Party size">
        <button class="btn btn--sm" type="submit">+ Add</button>
      </form>
    </div>

    <div class="admin-card">
      <h2 class="disp">Walk-in</h2>
      <p class="muted resv-walkin__note">Seat an unbooked party at an open table.</p>
      <form method="post" action="<?= e(url('/admin/reservations')) ?>" class="resv-walkin">
        <?= csrf_field() ?>
        <input type="hidden" name="date" value="<?= e($date) ?>">
        <input type="hidden" name="action" value="walkin">
        <input class="input input--sm" type="text" name="name" placeholder="Name (optional)" aria-label="Walk-in name">
        <input class="input input--sm" type="number" name="party" value="2" min="1" max="20" aria-label="Party size">
        <label class="visually-hidden" for="walkin-table">Table</label>
        <select class="select select--sm" id="walkin-table" name="table_id" required>
          <option value="">Table…</option>
          <?php foreach ($openTables as $t): ?>
            <option value="<?= (int) $t['id'] ?>"><?= e((string) $t['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn--sm btn--accent" type="submit">+ Seat walk-in</button>
      </form>
      <?php if ($openTables !== []): ?>
        <p class="muted resv-open-tables">Open now:
          <?= e(implode(', ', array_map(static fn ($t) => (string) $t['label'], $openTables))) ?>
        </p>
      <?php endif; ?>
    </div>
  </div>
</div>
