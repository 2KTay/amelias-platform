<?php
/**
 * Inventory (screen 23, 6.4), Manager + Owner.
 * Stock levels, low-stock highlight, adjust (POST), 86 toggle, reservable col.
 *
 * @var list<array<string,mixed>> $items
 * @var array{out:int,low:int,tracked:int,holds:int} $stats
 */
?>
<div class="admin-head">
  <h1 class="admin-h1 disp">Inventory</h1>
  <div class="admin-head__actions">
    <button class="btn btn--sm" type="button" disabled title="CSV export is not available yet">Export</button>
    <a class="btn btn--sm btn--solid" href="<?= e(url('/admin/menu')) ?>">+ Add product</a>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="kpi__label">Out / 86'd</div><div class="kpi__value"><?= e((string) $stats['out']) ?></div></div>
  <div class="kpi"><div class="kpi__label">Low stock</div><div class="kpi__value"><?= e((string) $stats['low']) ?></div></div>
  <div class="kpi"><div class="kpi__label">Tracked items</div><div class="kpi__value"><?= e((string) $stats['tracked']) ?></div></div>
  <div class="kpi"><div class="kpi__label">Active holds</div><div class="kpi__value"><?= e((string) $stats['holds']) ?></div></div>
</div>

<div class="admin-card mt-6">
  <div class="admin-head">
    <h2 class="disp mb-0">Finite goods, Market &amp; limited menu</h2>
    <span class="panel-stamp">Updated <?= e(fmt_date(gmdate('Y-m-d H:i:s'), 'M j, g:i A')) ?></span>
  </div>
  <div class="table-wrap mt-2" role="region" aria-labelledby="inv-caption" tabindex="0">
  <table class="admin-tbl">
    <caption id="inv-caption" class="visually-hidden">Finite goods, Market and limited menu stock levels</caption>
    <thead>
      <tr>
        <th scope="col" class="col-primary">Item</th><th scope="col" class="col-num">On-hand</th><th scope="col" class="col-num col-priority-low">Reserved</th>
        <th scope="col" class="col-num">Reservable</th><th scope="col" class="col-num col-priority-low">Threshold</th><th scope="col">Status</th>
        <th scope="col" class="col-actions">Adjust</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($items === []): ?>
        <tr><td data-label="Item" class="muted" colspan="7">No inventory-tracked products.</td></tr>
      <?php else: foreach ($items as $it):
        if ($it['is_86'] || $it['reservable'] <= 0) { $badge = '<span class="badge badge--danger">Out / 86\'d</span>'; }
        elseif ($it['reservable'] <= $it['low_stock_threshold']) { $badge = '<span class="badge badge--warn">Low</span>'; }
        else { $badge = '<span class="badge badge--ok">In stock</span>'; }
      ?>
        <tr>
          <td data-label="Item" class="col-primary"><?= e($it['name']) ?></td>
          <td data-label="On-hand" class="col-num"><?= e((string) $it['stock']) ?></td>
          <td data-label="Reserved" class="col-num col-priority-low"><?= e((string) $it['reserved']) ?></td>
          <td data-label="Reservable" class="col-num"><?= e((string) $it['reservable']) ?></td>
          <td data-label="Threshold" class="col-num col-priority-low"><?= e((string) $it['low_stock_threshold']) ?></td>
          <td data-label="Status"><?= $badge ?></td>
          <td data-label="Adjust" class="col-actions">
            <span class="adj">
              <form method="post" action="<?= e(url('/admin/inventory')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="adjust">
                <input type="hidden" name="product_id" value="<?= e((string) $it['id']) ?>">
                <input type="hidden" name="delta" value="-1">
                <button class="btn btn--sm" type="submit" aria-label="Decrease <?= e($it['name']) ?> on-hand">−</button>
              </form>
              <form method="post" action="<?= e(url('/admin/inventory')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="adjust">
                <input type="hidden" name="product_id" value="<?= e((string) $it['id']) ?>">
                <input type="hidden" name="delta" value="1">
                <button class="btn btn--sm" type="submit" aria-label="Increase <?= e($it['name']) ?> on-hand">+</button>
              </form>
              <form method="post" action="<?= e(url('/admin/inventory')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="set">
                <input type="hidden" name="product_id" value="<?= e((string) $it['id']) ?>">
                <label class="visually-hidden" for="stock-<?= e((string) $it['id']) ?>">Set stock for <?= e($it['name']) ?></label>
                <input class="input" type="number" min="0" id="stock-<?= e((string) $it['id']) ?>" name="stock" value="<?= e((string) $it['stock']) ?>">
                <label class="visually-hidden" for="thr-<?= e((string) $it['id']) ?>">Set threshold for <?= e($it['name']) ?></label>
                <input class="input" type="number" min="0" id="thr-<?= e((string) $it['id']) ?>" name="threshold" value="<?= e((string) $it['low_stock_threshold']) ?>">
                <button class="btn btn--sm" type="submit">Set</button>
              </form>
              <form method="post" action="<?= e(url('/admin/inventory')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle86">
                <input type="hidden" name="product_id" value="<?= e((string) $it['id']) ?>">
                <button class="btn btn--sm" type="submit"><?= $it['is_86'] ? 'Un-86' : '86' ?></button>
              </form>
            </span>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
  <p class="muted mt-2"><strong>Reservable = on-hand − active holds.</strong> An item shows <em>Low</em> at or below its threshold and <em>86'd</em> when reservable hits zero or it's manually 86'd. The 86 toggle propagates to the public menu.</p>
</div>
