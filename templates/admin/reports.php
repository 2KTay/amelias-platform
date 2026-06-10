<?php
/**
 * Reports & reconciliation (screen 26, 6.3), Owner ONLY.
 *
 * @var string $fromDate
 * @var string $toDate
 * @var array{gross_cents:int,refund_cents:int,net_cents:int,tax_cents:int} $totals
 * @var list<array{stream:string,revenue_cents:int,pct:float}> $streams
 * @var int $streamTotal
 * @var list<array{tax_category:string,taxable_base_cents:int,tax_cents:int}> $taxes
 * @var int $taxTotal
 * @var list<array<string,mixed>> $payouts
 * @var int $ledgerNet
 * @var string|null $enteredPayout
 * @var int|null $variance
 * @var list<array<string,mixed>> $refunds
 */
$base = '&from=' . rawurlencode($fromDate) . '&to=' . rawurlencode($toDate);
?>
<div class="admin-head">
  <div class="flex">
    <h1 class="admin-h1 disp mb-0">Reports</h1>
    <span class="badge badge--warn">Owner only</span>
  </div>
  <div class="admin-head__actions">
    <a class="btn btn--sm" href="<?= e(url('/admin/reports?export=csv&report=streams' . $base)) ?>">Export CSV</a>
  </div>
</div>

<div class="admin-card">
  <form class="filter-bar" method="get" action="<?= e(url('/admin/reports')) ?>">
    <div class="field mb-0"><label for="rep-from">From</label><input class="input" type="date" id="rep-from" name="from" value="<?= e($fromDate) ?>"></div>
    <div class="field mb-0"><label for="rep-to">To</label><input class="input" type="date" id="rep-to" name="to" value="<?= e($toDate) ?>"></div>
    <div class="field mb-0">
      <label for="rep-range">Range</label>
      <select class="select" id="rep-range" data-range-preset>
        <option value="">Custom</option>
        <option value="this-month">This month</option>
        <option value="last-month">Last month</option>
        <option value="quarter">Quarter</option>
      </select>
    </div>
    <button class="btn btn--sm btn--solid" type="submit">Apply</button>
  </form>
</div>
<script>
(function () {
  var sel = document.querySelector('[data-range-preset]');
  if (!sel) { return; }
  var from = document.getElementById('rep-from');
  var to = document.getElementById('rep-to');
  function iso(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  sel.addEventListener('change', function () {
    var now = new Date(), f, t;
    switch (sel.value) {
      case 'this-month': f = new Date(now.getFullYear(), now.getMonth(), 1); t = now; break;
      case 'last-month': f = new Date(now.getFullYear(), now.getMonth() - 1, 1); t = new Date(now.getFullYear(), now.getMonth(), 0); break;
      case 'quarter': f = new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1); t = now; break;
      default: return;
    }
    if (from) { from.value = iso(f); }
    if (to) { to.value = iso(t); }
  });
})();
</script>

<div class="kpi-grid">
  <div class="kpi"><div class="kpi__label">Gross</div><div class="kpi__value"><?= e(fmt_money($totals['gross_cents'])) ?></div></div>
  <div class="kpi"><div class="kpi__label">Net</div><div class="kpi__value"><?= e(fmt_money($totals['net_cents'])) ?></div></div>
  <div class="kpi"><div class="kpi__label">Refunds</div><div class="kpi__value"><?= e(fmt_money($totals['refund_cents'])) ?></div></div>
  <div class="kpi"><div class="kpi__label">Tax collected</div><div class="kpi__value"><?= e(fmt_money($totals['tax_cents'])) ?></div></div>
</div>

<div class="admin-card mt-6">
  <div class="admin-head"><h2 class="disp mb-0">Sales by stream</h2><a class="btn btn--sm" href="<?= e(url('/admin/reports?export=csv&report=streams' . $base)) ?>">Export CSV</a></div>
  <table class="admin-tbl mt-2">
    <thead><tr><th scope="col">Stream</th><th scope="col">Revenue</th><th scope="col">% of gross</th></tr></thead>
    <tbody>
      <?php if ($streams === []): ?><tr><td data-label="Stream" class="muted">No sales in this period.</td></tr>
      <?php else: foreach ($streams as $s): ?>
        <tr><td data-label="Stream"><?= e($s['stream']) ?></td><td data-label="Revenue"><?= e(fmt_money($s['revenue_cents'])) ?></td><td data-label="% of gross"><?= e((string) $s['pct']) ?>%</td></tr>
      <?php endforeach; endif; ?>
      <tr><td data-label="Stream"><strong>Total</strong></td><td data-label="Revenue"><strong><?= e(fmt_money($streamTotal)) ?></strong></td><td data-label="% of gross"></td></tr>
    </tbody>
  </table>
</div>

<div class="admin-card">
  <div class="admin-head"><h2 class="disp mb-0">Tax report, AZ TPT by category</h2><a class="btn btn--sm" href="<?= e(url('/admin/reports?export=csv&report=tax' . $base)) ?>">Export CSV</a></div>
  <table class="admin-tbl mt-2">
    <thead><tr><th scope="col">Tax category</th><th scope="col">Taxable base</th><th scope="col">Tax collected</th></tr></thead>
    <tbody>
      <?php if ($taxes === []): ?><tr><td data-label="Tax category" class="muted">No tax lines in this period.</td></tr>
      <?php else: foreach ($taxes as $t): ?>
        <tr><td data-label="Tax category"><?= e($t['tax_category']) ?></td><td data-label="Taxable base"><?= e(fmt_money($t['taxable_base_cents'])) ?></td><td data-label="Tax collected"><?= e(fmt_money($t['tax_cents'])) ?></td></tr>
      <?php endforeach; endif; ?>
      <tr><td data-label="Tax category"><strong>Total</strong></td><td data-label="Taxable base"></td><td data-label="Tax collected"><strong><?= e(fmt_money($taxTotal)) ?></strong></td></tr>
    </tbody>
  </table>
  <p class="muted mt-2">TPT is reported per category (prepared food vs. retail Market goods are taxed at different combined rates). Gift cards are recorded at redemption, not sale. Export the CSV for the monthly TPT filing.</p>
</div>

<div class="admin-card">
  <div class="admin-head"><h2 class="disp mb-0">Stripe payout reconciliation</h2><a class="btn btn--sm" href="<?= e(url('/admin/reports?export=csv&report=payouts' . $base)) ?>">Export CSV</a></div>
  <table class="admin-tbl mt-2">
    <thead><tr><th scope="col">Payout ID</th><th scope="col">Gross</th><th scope="col">Refunds</th><th scope="col">Net</th><th scope="col">Txns</th></tr></thead>
    <tbody>
      <?php if ($payouts === []): ?><tr><td data-label="Payout ID" class="muted">No payouts recorded in this period.</td></tr>
      <?php else: foreach ($payouts as $p): ?>
        <tr><td data-label="Payout ID"><?= e((string) $p['payout_id']) ?></td><td data-label="Gross"><?= e(fmt_money((int) $p['gross_cents'])) ?></td><td data-label="Refunds"><?= e(fmt_money((int) $p['refund_cents'])) ?></td><td data-label="Net"><?= e(fmt_money((int) $p['net_cents'])) ?></td><td data-label="Txns"><?= e((string) $p['txn_count']) ?></td></tr>
      <?php endforeach; endif; ?>
      <tr><td data-label="Payout ID"><strong>Ledger net</strong></td><td data-label="Gross"></td><td data-label="Refunds"></td><td data-label="Net"><strong><?= e(fmt_money($ledgerNet)) ?></strong></td><td data-label="Txns"></td></tr>
    </tbody>
  </table>

  <form class="admin-search mt-2 mb-0" method="get" action="<?= e(url('/admin/reports')) ?>">
    <input type="hidden" name="from" value="<?= e($fromDate) ?>">
    <input type="hidden" name="to" value="<?= e($toDate) ?>">
    <div class="field mb-0"><label for="payout-total">Entered payout total ($)</label><input class="input" type="text" id="payout-total" name="payout_total" value="<?= e((string) ($enteredPayout ?? '')) ?>" placeholder="e.g. 12108.00"></div>
    <button class="btn btn--sm btn--solid" type="submit">Reconcile</button>
  </form>
  <?php if ($variance !== null): ?>
    <div class="alert <?= $variance === 0 ? 'alert--ok' : 'alert--danger' ?> mt-2" role="status">
      <?php if ($variance === 0): ?>Reconciled, entered total matches the ledger net.
      <?php else: ?>Variance of <?= e(fmt_money($variance)) ?> between the entered payout total and the ledger net.<?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div class="admin-card">
  <div class="admin-head"><h2 class="disp mb-0">Refunds</h2><a class="btn btn--sm" href="<?= e(url('/admin/reports?export=csv&report=refunds' . $base)) ?>">Export CSV</a></div>
  <table class="admin-tbl mt-2">
    <thead><tr><th scope="col">Payment</th><th scope="col">Order</th><th scope="col">Amount</th><th scope="col">Status</th><th scope="col">Date</th></tr></thead>
    <tbody>
      <?php if ($refunds === []): ?><tr><td data-label="Payment" class="muted">No refunds in this period.</td></tr>
      <?php else: foreach ($refunds as $r): ?>
        <tr><td data-label="Payment">#<?= e((string) $r['id']) ?></td><td data-label="Order"><?= e((string) ($r['public_token'] ?? '–')) ?></td><td data-label="Amount"><?= e(fmt_money((int) $r['amount_cents'])) ?></td><td data-label="Status"><?= e((string) $r['status']) ?></td><td data-label="Date"><?= e(fmt_date((string) $r['created_at'], 'M j, Y g:i A')) ?></td></tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
