<?php
/**
 * Live order queue (screen 20). Orders by status with a new-order badge.
 * Mark-ready fires the order-ready notification; refund/comp/void are audit-
 * logged. All actions are CSRF-guarded POSTs.
 *
 * @var list<array<string,mixed>> $orders
 * @var array<string,int> $counts
 * @var string $filter
 * @var array{type:string,message:string}|null $flash
 */
$pills = [
    'live'      => 'Live',
    'paid'      => 'New',
    'preparing' => 'Preparing',
    'ready'     => 'Ready',
    'fulfilled' => 'Picked up',
    'all'       => 'All',
];
$statusBadge = static function (string $s): string {
    return match ($s) {
        'paid'      => 'badge--new',
        'preparing' => 'badge--warn',
        'ready'     => 'badge--ok',
        'fulfilled' => '',
        'refunded', 'partially_refunded' => 'badge--danger',
        'comped', 'voided', 'cancelled', 'expired' => '',
        default     => '',
    };
};
?>
<div class="admin-head">
  <h1 class="admin-h1 disp">Live Order Queue</h1>
</div>

<?php if (!empty($flash)): ?>
  <div class="alert <?= $flash['type'] === 'error' ? 'alert--danger' : 'alert--ok' ?>" role="status"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="pills">
  <?php foreach ($pills as $key => $label):
    $count = $counts[$key] ?? null; ?>
    <a class="pill <?= $filter === $key ? 'active' : '' ?>" href="<?= e(url('/admin/orders') . ($key === 'live' ? '' : '?status=' . $key)) ?>">
      <?= e($label) ?><?php if ($count !== null): ?> <span class="count"><?= e((string) $count) ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="admin-card">
  <table class="admin-tbl orders-tbl">
    <thead>
      <tr>
        <th scope="col">#</th>
        <th scope="col">Slot</th>
        <th scope="col">Customer</th>
        <th scope="col">Items</th>
        <th scope="col">Total</th>
        <th scope="col">Status</th>
        <th scope="col"><span class="visually-hidden">Actions</span></th>
      </tr>
    </thead>
    <tbody>
      <?php if ($orders === []): ?>
        <tr><td colspan="7" class="muted">No orders in this view.</td></tr>
      <?php else: foreach ($orders as $o):
        $status = (string) $o['status']; ?>
        <tr class="<?= !empty($o['is_new']) ? 'is-new' : '' ?>">
          <td data-label="#">#<?= e((string) $o['id']) ?></td>
          <td data-label="Slot"><?= $o['slot_start'] !== null ? e(fmt_date((string) $o['slot_start'], 'g:i A')) : 'ASAP' ?></td>
          <td data-label="Customer" class="cust">
            <?= e((string) ($o['contact_name'] ?: 'Guest')) ?>
            <?php if (!empty($o['is_new'])): ?><span class="new-tag">New</span><?php endif; ?>
          </td>
          <td data-label="Items" class="items-sum"><?= e((string) ($o['items_summary'] ?? '')) ?></td>
          <td data-label="Total" class="num"><?= e(fmt_money((int) $o['total_cents'])) ?></td>
          <td data-label="Status"><span class="badge <?= e($statusBadge($status)) ?>"><?= e(ucfirst(str_replace('_', ' ', $status))) ?></span></td>
          <td data-label="Actions" class="row-actions">
            <?php if ($status === 'paid'): ?>
              <form method="post" action="<?= e(url('/admin/orders/' . $o['id'] . '/status')) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="accept">
                <button class="btn btn--sm" type="submit">Accept</button>
              </form>
            <?php elseif ($status === 'preparing'): ?>
              <form method="post" action="<?= e(url('/admin/orders/' . $o['id'] . '/status')) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="ready">
                <button class="btn btn--sm btn--solid" type="submit">Mark ready</button>
              </form>
            <?php elseif ($status === 'ready'): ?>
              <form method="post" action="<?= e(url('/admin/orders/' . $o['id'] . '/status')) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="fulfilled">
                <button class="btn btn--sm btn--solid" type="submit">Picked up</button>
              </form>
            <?php endif; ?>

            <?php if (in_array($status, ['paid', 'preparing', 'ready', 'fulfilled'], true)): ?>
              <details class="row-more">
                <summary class="btn btn--sm">More</summary>
                <div class="row-more__menu">
                  <form method="post" action="<?= e(url('/admin/orders/' . $o['id'] . '/status')) ?>" onsubmit="return confirm('Refund this order?');">
                    <?= csrf_field() ?><input type="hidden" name="action" value="refund">
                    <label class="visually-hidden" for="ramt-<?= e((string) $o['id']) ?>">Partial refund amount</label>
                    <input class="input" id="ramt-<?= e((string) $o['id']) ?>" type="text" name="amount" placeholder="Full ($)">
                    <button class="btn btn--sm btn--danger" type="submit">Refund</button>
                  </form>
                  <form method="post" action="<?= e(url('/admin/orders/' . $o['id'] . '/status')) ?>" onsubmit="return confirm('Comp (zero out) this order?');">
                    <?= csrf_field() ?><input type="hidden" name="action" value="comp">
                    <button class="btn btn--sm" type="submit">Comp</button>
                  </form>
                  <form method="post" action="<?= e(url('/admin/orders/' . $o['id'] . '/status')) ?>" onsubmit="return confirm('Void this order?');">
                    <?= csrf_field() ?><input type="hidden" name="action" value="void">
                    <button class="btn btn--sm" type="submit">Void</button>
                  </form>
                </div>
              </details>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
