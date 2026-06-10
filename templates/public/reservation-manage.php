<?php
/**
 * Tokenized reservation management (screen 12). Shows status + details and
 * offers cancel. Full refund before the cancel cutoff; forfeit after.
 */
$booking = $booking ?? [];
$canCancel = $canCancel ?? false;
$cutoffHours = (int) ($cutoffHours ?? 24);
$active = !in_array($booking['status'] ?? '', ['cancelled', 'no_show', 'completed'], true);

$statusBadge = [
    'held'      => ['badge--warn', 'Held, deposit pending'],
    'confirmed' => ['badge--ok', 'Confirmed'],
    'seated'    => ['badge--ok', 'Seated'],
    'completed' => ['badge', 'Completed'],
    'cancelled' => ['badge--danger', 'Cancelled'],
    'no_show'   => ['badge--danger', 'No-show'],
];
[$badgeClass, $badgeLabel] = $statusBadge[$booking['status'] ?? 'confirmed'] ?? ['badge', ucfirst((string) ($booking['status'] ?? ''))];
?>
<header class="page-head">
  <div class="container">
    <p class="kicker">Your reservation</p>
    <h1 class="disp">Manage your table</h1>
  </div>
</header>

<section class="section">
  <div class="container reserve-confirm">
    <?php if (!empty($notice)): ?>
      <div class="alert" role="status"><?= e($notice) ?></div>
    <?php endif; ?>

    <div class="card">
      <p><span class="badge <?= e($badgeClass) ?>"><?= e($badgeLabel) ?></span></p>
      <dl class="reserve-confirm__dl">
        <dt>When</dt><dd><?= e(fmt_date((string) $booking['slot_start'], 'l, F j · g:i A')) ?></dd>
        <dt>Party</dt><dd><?= (int) $booking['party_size'] ?> guests</dd>
        <dt>Name</dt><dd><?= e((string) $booking['contact_name']) ?></dd>
        <?php if (!empty($booking['deposit_order_id'])): ?>
          <dt>Deposit</dt><dd><?= e(($booking['deposit_status'] ?? '') === 'paid' ? 'Paid · ' . fmt_money((int) ($booking['deposit_cents'] ?? 0)) : 'Pending') ?></dd>
        <?php endif; ?>
      </dl>

      <?php if ($active): ?>
        <form method="post" action="<?= e(url('/reservation/' . $booking['public_token'])) ?>" class="reserve-manage__cancel">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="cancel">
          <?php if ($canCancel): ?>
            <p class="field__hint">Cancelling now (more than <?= $cutoffHours ?> hours out) refunds any deposit in full.</p>
          <?php else: ?>
            <p class="field__hint">This close to service the deposit is non-refundable, but you can still cancel so we can free the table.</p>
          <?php endif; ?>
          <button class="btn btn--danger" type="submit">Cancel reservation</button>
        </form>
      <?php else: ?>
        <p class="btn-row mt-6"><a class="btn btn--solid" href="<?= e(url('/reserve')) ?>">Book again</a></p>
      <?php endif; ?>
    </div>
  </div>
</section>
