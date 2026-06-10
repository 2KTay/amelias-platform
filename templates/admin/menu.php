<?php
/**
 * Menu & Pricing (admin), Owner + Manager.
 *
 * Data-table of the catalog: a category <select> filter, inline price edit with
 * a per-row Save, an 86 (sold-out) toggle, and a row-delete trash button.
 * Clicking a row (or its name) opens the full product editor. The "Unsaved
 * changes" savebar stays hidden until a price field is edited.
 *
 * @var list<array{id:int,name:string}> $categories
 * @var array<int,list<array<string,mixed>>> $byCategory  category id => items
 * @var list<array<string,mixed>> $visible  rows for the current filter
 * @var int $activeCat  0 = All
 * @var array<string,string> $dayParts
 * @var array{type:string,message:string}|null $flash
 */
?>
<div class="admin-head">
  <h1 class="admin-h1 disp">Menu &amp; Pricing</h1>
  <div class="admin-head__actions">
    <button class="btn btn--sm" type="button" id="bulkEdit" aria-pressed="false">Bulk edit prices</button>
    <a class="btn btn--sm btn--solid" href="<?= e(url('/admin/menu/new')) ?>">+ Add item</a>
  </div>
</div>

<?php if (!empty($flash)): ?>
  <div class="alert <?= $flash['type'] === 'error' ? 'alert--danger' : 'alert--ok' ?>" role="status"><?= e($flash['message']) ?></div>
<?php endif; ?>

<!-- STICKY SAVE BAR, hidden at rest; revealed only after a price is edited. -->
<div class="savebar" id="savebar" hidden>
  <div class="savebar__left">
    <span class="unsaved" id="unsavedCount">● 0 unsaved changes</span>
    <span class="muted savebar__hint">Each price saves its own row.</span>
  </div>
  <div class="savebar__actions">
    <button class="btn btn--sm" type="button" id="discardAll">Discard</button>
    <button class="btn btn--sm btn--solid" type="button" id="saveAll">Save changes</button>
  </div>
</div>

<!-- CATEGORY FILTER -->
<form class="menu-filter" method="get" action="<?= e(url('/admin/menu')) ?>">
  <label class="field menu-filter__field">
    <span class="field__label">Category</span>
    <select class="select" name="cat" id="catFilter" onchange="this.form.submit()" aria-label="Filter by category">
      <option value=""<?= $activeCat === 0 ? ' selected' : '' ?>>All categories</option>
      <?php foreach ($categories as $c):
        $cid   = (int) $c['id'];
        $count = count($byCategory[$cid] ?? []); ?>
        <option value="<?= e((string) $cid) ?>"<?= $cid === $activeCat ? ' selected' : '' ?>>
          <?= e((string) $c['name']) ?> (<?= e((string) $count) ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <noscript><button class="btn btn--sm" type="submit">Filter</button></noscript>
</form>

<!-- ITEMS TABLE -->
<div class="admin-card">
  <table class="admin-tbl menu-tbl">
    <thead>
      <tr>
        <th scope="col">Item</th>
        <th scope="col" class="col-low">Category</th>
        <th scope="col" class="col-num">Price</th>
        <th scope="col" class="col-low">Day-part</th>
        <th scope="col" class="col-low">Dietary</th>
        <th scope="col">86</th>
        <th scope="col" class="col-actions"><span class="visually-hidden">Actions</span></th>
      </tr>
    </thead>
    <tbody>
      <?php if ($visible === []): ?>
        <tr><td colspan="7" class="muted">No items in this view yet.</td></tr>
      <?php else: foreach ($visible as $it):
        $id       = (int) $it['id'];
        $is86     = (bool) $it['is_86'];
        $dayLabel = $dayParts[(string) $it['day_part']] ?? (string) $it['day_part'];
        $editUrl  = url('/admin/menu/' . $id); ?>
        <tr class="menu-row <?= $is86 ? 'is-86' : '' ?>" data-href="<?= e($editUrl) ?>">
          <td class="itemname" data-label="Item">
            <a class="menu-row__link" href="<?= e($editUrl) ?>"><?= e((string) $it['name']) ?></a>
          </td>
          <td class="col-low" data-label="Category">
            <?php foreach (($it['category_names'] ?? []) as $cn): ?>
              <span class="badge"><?= e((string) $cn) ?></span>
            <?php endforeach; ?>
          </td>
          <td class="col-num keep" data-label="Price">
            <form class="price-form" method="post" action="<?= e(url('/admin/menu')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="price">
              <input type="hidden" name="product_id" value="<?= e((string) $id) ?>">
              <span class="price-input">
                <span aria-hidden="true">$</span>
                <label class="visually-hidden" for="price-<?= e((string) $id) ?>">Price for <?= e((string) $it['name']) ?></label>
                <input class="num price-field" id="price-<?= e((string) $id) ?>" name="price"
                       inputmode="decimal" value="<?= e(fmt_money((int) $it['price_cents'], false)) ?>">
              </span>
              <button class="btn btn--sm price-save" type="submit">Save</button>
            </form>
          </td>
          <td class="daypart col-low" data-label="Day-part"><?= e($dayLabel) ?></td>
          <td class="col-low" data-label="Dietary">
            <div class="dietary">
              <?php foreach (($it['dietary_tags'] ?? []) as $tag): ?>
                <span class="badge"><?= e((string) $tag['label']) ?></span>
              <?php endforeach; ?>
            </div>
          </td>
          <td class="keep" data-label="86">
            <form class="eightysix-form" method="post" action="<?= e(url('/admin/menu')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle86">
              <input type="hidden" name="product_id" value="<?= e((string) $id) ?>">
              <label class="sw">
                <input type="checkbox" <?= $is86 ? 'checked' : '' ?>
                       onchange="this.form.submit()"
                       aria-label="Mark <?= e((string) $it['name']) ?> as 86'd">
                <span class="sw__track"></span>
                <span class="sw__knob"></span>
              </label>
              <noscript><button class="btn btn--sm" type="submit"><?= $is86 ? 'Un-86' : '86' ?></button></noscript>
            </form>
            <?php if ($is86): ?><div class="mt-1"><span class="badge badge--danger">86'd</span></div><?php endif; ?>
          </td>
          <td class="row-actions keep col-actions" data-label="Actions">
            <form method="post" action="<?= e(url('/admin/menu')) ?>" onsubmit="return confirm('Remove this item from the menu?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="product_id" value="<?= e((string) $id) ?>">
              <input type="hidden" name="category_id" value="<?= e((string) $activeCat) ?>">
              <button class="btn btn--sm btn--danger btn--icon" type="submit" aria-label="Delete <?= e((string) $it['name']) ?>" title="Delete">
                <span aria-hidden="true">&#128465;</span>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<script>
(function () {
  // Row click opens the editor, but never when interacting with the inline
  // controls (price, 86 toggle, delete, links, buttons) inside .keep cells.
  var rows = Array.prototype.slice.call(document.querySelectorAll('.menu-row'));
  rows.forEach(function (row) {
    row.addEventListener('click', function (ev) {
      if (ev.target.closest('.keep') || ev.target.closest('a, button, input, label, form')) { return; }
      var href = row.getAttribute('data-href');
      if (href) { window.location.href = href; }
    });
  });

  // Bulk edit + savebar: track dirty price fields. The savebar is hidden by
  // default and only appears once at least one price differs from its original.
  var savebar = document.getElementById('savebar');
  var unsaved = document.getElementById('unsavedCount');
  var bulkEdit = document.getElementById('bulkEdit');
  var fields = Array.prototype.slice.call(document.querySelectorAll('.price-field'));
  var dirty = {};

  function refresh() {
    var n = Object.keys(dirty).length;
    if (unsaved) { unsaved.textContent = '● ' + n + ' unsaved change' + (n === 1 ? '' : 's'); }
    // Reveal only when there are real edits to save.
    if (savebar) { savebar.hidden = n === 0; }
  }

  fields.forEach(function (input) {
    input.dataset.original = input.value;
    input.addEventListener('input', function () {
      if (input.value !== input.dataset.original) { dirty[input.id] = true; }
      else { delete dirty[input.id]; }
      var row = input.closest('tr');
      if (row) { row.classList.toggle('is-dirty', input.value !== input.dataset.original); }
      refresh();
    });
  });

  if (bulkEdit) {
    bulkEdit.addEventListener('click', function () {
      var on = bulkEdit.getAttribute('aria-pressed') !== 'true';
      bulkEdit.setAttribute('aria-pressed', on ? 'true' : 'false');
      var tbl = document.querySelector('.menu-tbl');
      if (tbl) { tbl.classList.toggle('bulk-mode', on); }
    });
  }

  // Save changes: submit the first dirty row's price form (each saves its row).
  var saveAll = document.getElementById('saveAll');
  if (saveAll) {
    saveAll.addEventListener('click', function () {
      var forms = [];
      fields.forEach(function (input) {
        if (dirty[input.id]) {
          var form = input.closest('form');
          if (form && forms.indexOf(form) === -1) { forms.push(form); }
        }
      });
      if (forms.length) { forms[0].submit(); }
    });
  }

  // Discard: revert every dirty field to its original value and hide the savebar.
  var discardAll = document.getElementById('discardAll');
  if (discardAll) {
    discardAll.addEventListener('click', function () {
      fields.forEach(function (input) {
        input.value = input.dataset.original;
        var row = input.closest('tr');
        if (row) { row.classList.remove('is-dirty'); }
      });
      dirty = {};
      refresh();
    });
  }
})();
</script>
