<?php
/**
 * Product editor (admin), Owner + Manager. New product (/admin/menu/new) or
 * edit an existing one (/admin/menu/{id}). One save form with CSRF, an image
 * upload, dietary-tag checkboxes, and a soft-delete action.
 *
 * @var array<string,mixed>|null $product       null = new
 * @var list<array{id:int,name:string}> $categories
 * @var list<array{id:int,slug:string,label:string}> $dietaryTags
 * @var array<string,string> $dayParts
 * @var int $selectedCat
 * @var list<int> $selectedTags
 * @var array{type:string,message:string}|null $flash
 */
$isNew      = $product === null;
$name       = (string) ($product['name'] ?? '');
$desc       = (string) ($product['description'] ?? '');
$priceCents = (int) ($product['price_cents'] ?? 0);
$dayPart    = (string) ($product['day_part'] ?? 'all_day');
$image      = (string) ($product['image_path'] ?? '');
$is86       = (int) ($product['is_86'] ?? 0) === 1;
$isActive   = $isNew ? true : (int) ($product['is_active'] ?? 1) === 1;
$pid        = (int) ($product['id'] ?? 0);
$action     = $isNew ? url('/admin/menu/new') : url('/admin/menu/' . $pid);
?>
<div class="admin-head">
  <div>
    <a class="btn btn--sm menu-back" href="<?= e(url('/admin/menu')) ?>">&lsaquo; Back to menu</a>
    <h1 class="admin-h1 disp mb-0"><?= $isNew ? 'Add menu item' : 'Edit: ' . e($name) ?></h1>
  </div>
  <span class="badge"><?= $isNew ? 'New' : 'Editing' ?></span>
</div>

<?php if (!empty($flash)): ?>
  <div class="alert <?= $flash['type'] === 'error' ? 'alert--danger' : 'alert--ok' ?>" role="status"><?= e($flash['message']) ?></div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="menu-edit">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">

  <div class="menu-edit__cols">
    <div class="menu-edit__main admin-card">
      <div class="field">
        <label for="mi-name">Item name <span class="req">*</span></label>
        <input class="input" type="text" id="mi-name" name="name" value="<?= e($name) ?>" required maxlength="200" placeholder="e.g. Burrata &amp; Heirloom Tomato">
      </div>

      <div class="menu-edit__row">
        <div class="field">
          <label for="mi-price">Price</label>
          <input class="input num" type="text" id="mi-price" name="price" inputmode="decimal" value="<?= e(fmt_money($priceCents, false)) ?>" placeholder="0.00">
        </div>
        <div class="field">
          <label for="mi-cat">Category</label>
          <select class="select" id="mi-cat" name="category_id">
            <option value="0">Uncategorized</option>
            <?php foreach ($categories as $c): $cid = (int) $c['id']; ?>
              <option value="<?= e((string) $cid) ?>"<?= $cid === $selectedCat ? ' selected' : '' ?>><?= e((string) $c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="mi-daypart">Day-part</label>
          <select class="select" id="mi-daypart" name="day_part">
            <?php foreach ($dayParts as $code => $label): ?>
              <option value="<?= e($code) ?>"<?= $code === $dayPart ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="field">
        <label for="mi-desc">Description</label>
        <textarea class="textarea" id="mi-desc" name="description" rows="4" placeholder="Shown on the public menu and item page."><?= e($desc) ?></textarea>
      </div>

      <fieldset class="field menu-edit__tags">
        <legend class="field__label">Dietary tags</legend>
        <?php if ($dietaryTags === []): ?>
          <p class="muted">No dietary tags defined.</p>
        <?php else: ?>
          <div class="tag-grid">
            <?php foreach ($dietaryTags as $t):
              $tid = (int) $t['id'];
              $on  = in_array($tid, $selectedTags, true); ?>
              <label class="tag-check">
                <input type="checkbox" name="tags[]" value="<?= e((string) $tid) ?>"<?= $on ? ' checked' : '' ?>>
                <span><?= e((string) $t['label']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </fieldset>
    </div>

    <aside class="menu-edit__side admin-card">
      <div class="field">
        <span class="field__label">Image</span>
        <?php if ($image !== ''): ?>
          <div class="menu-edit__img">
            <img src="<?= e(asset('uploads/' . ltrim($image, '/'))) ?>" alt="Current image for <?= e($name) ?>" width="160" height="160" loading="lazy">
          </div>
        <?php else: ?>
          <div class="menu-edit__img menu-edit__img--empty" aria-hidden="true">No image</div>
        <?php endif; ?>
        <label class="menu-edit__upload" for="mi-image">Upload new image</label>
        <input class="input" type="file" id="mi-image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
        <p class="field__hint">JPEG, PNG, WebP or GIF, up to 10&nbsp;MB. Replaces the current image.</p>
      </div>

      <div class="field field--check">
        <label><input type="checkbox" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>> Active (visible on menu)</label>
      </div>
      <div class="field field--check">
        <label><input type="checkbox" name="is_86" value="1"<?= $is86 ? ' checked' : '' ?>> 86'd (sold out, hidden from public)</label>
      </div>
    </aside>
  </div>

  <div class="sticky-actions">
    <?php if (!$isNew): ?>
      <button class="btn btn--danger" type="submit" name="action" value="delete"
              onclick="return confirm('Remove this item from the menu?');">Delete</button>
    <?php endif; ?>
    <a class="btn" href="<?= e(url('/admin/menu')) ?>">Cancel</a>
    <button class="btn btn--solid" type="submit"><?= $isNew ? 'Add item' : 'Save changes' ?></button>
  </div>
</form>
