<?php
/** Item detail (screen 5): variants, modifiers, special instructions, add-to-cart.
 * Faithful port of mockups/item.html, two-column layout (sticky media | detail),
 * breadcrumb, styled modifier option rows, sticky purchase bar. Real catalog data. */
$img = $product['image_path'] ?? null;
// Optional gallery: a list of extra image paths beyond the hero. No catalog
// column exists for this yet, so this stays empty until seed data is added
// (see report). Hero remains image_path.
$gallery = $product['images'] ?? [];
$dietaryTags = $dietaryTags ?? [];
?>
<main class="container item-wrap">

  <nav class="crumb" aria-label="Breadcrumb">
    <a href="<?= e(url('/menu')) ?>">Menu</a>
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
    <span><?= e($product['name']) ?></span>
  </nav>

  <div class="item">

    <!-- LEFT: photography -->
    <div class="item__media">
      <div class="item__photo">
        <?php if ($img): ?>
          <img src="<?= e(asset($img)) ?>" alt="<?= e($product['name']) ?>" fetchpriority="high">
        <?php else: ?>
          <div class="item__photo-ph" role="img" aria-label="<?= e($product['name']) ?>"></div>
        <?php endif; ?>
      </div>
      <?php if (count($gallery) > 1): ?>
        <div class="item__thumbs">
          <?php foreach ($gallery as $i => $thumb): ?>
            <div class="item__thumb">
              <img src="<?= e(asset($thumb)) ?>" alt="<?= e($product['name']) ?>, view <?= (int) $i + 1 ?>">
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- RIGHT: details + modifiers -->
    <div class="item__detail">
      <div class="item__head">
        <h1 class="item__name"><?= e($product['name']) ?></h1>
        <span class="item__price"><?= e(fmt_money((int) $product['price_cents'])) ?></span>
      </div>

      <?php if (!empty($product['description'])): ?>
        <p class="item__desc"><?= e($product['description']) ?></p>
      <?php endif; ?>

      <?php if (!empty($dietaryTags)): ?>
        <div class="item__tags">
          <?php foreach ($dietaryTags as $tag): ?>
            <span class="tag"><?= e($tag['label']) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($product['is_86'])): ?>
        <div class="alert">This item is currently sold out. Check back soon.</div>
        <p class="btn-row mt-4"><a class="btn" href="<?= e(url('/menu')) ?>">Back to menu</a></p>
      <?php else: ?>
        <form method="post" action="<?= e(url('/cart/add')) ?>" class="item__form" id="itemForm" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

          <div class="mods">
            <?php if (!empty($variants)): ?>
              <fieldset class="modgroup" id="grp-size" data-required="1" data-group-label="Size" data-field="variant_id">
                <div class="modgroup__head">
                  <legend class="modgroup__title">Size</legend>
                  <span class="modgroup__req">Required · choose 1</span>
                </div>
                <?php foreach ($variants as $i => $v): ?>
                  <label class="opt">
                    <input type="radio" name="variant_id" value="<?= (int) $v['id'] ?>" data-price="<?= (int) $v['price_cents'] ?>" <?= $i === 0 ? 'checked' : '' ?> required>
                    <span class="opt__label"><?= e($v['name']) ?>
                      <?php if (!empty($v['note'])): ?><span class="opt__note"><?= e($v['note']) ?></span><?php endif; ?>
                    </span>
                    <span class="opt__price"><?= e(fmt_money((int) $v['price_cents'])) ?></span>
                  </label>
                <?php endforeach; ?>
              </fieldset>
            <?php endif; ?>

            <?php foreach ($groups as $g): ?>
              <?php $multi = (int) $g['max_select'] > 1; $required = (int) $g['min_select'] >= 1; ?>
              <fieldset class="modgroup" id="grp-<?= (int) $g['id'] ?>"
                <?= $required ? 'data-required="1" data-group-label="' . e($g['name']) . '"' : '' ?>
                data-multi="<?= $multi ? '1' : '0' ?>">

                <div class="modgroup__head">
                  <legend class="modgroup__title"><?= e($g['name']) ?></legend>
                  <?php if ($required): ?>
                    <span class="modgroup__req">Required · choose <?= $multi ? 'at least one' : '1' ?></span>
                  <?php else: ?>
                    <span class="modgroup__opt">Optional · choose <?= $multi ? 'any' : '1' ?></span>
                  <?php endif; ?>
                </div>
                <?php foreach ($g['modifiers'] as $m): ?>
                  <label class="opt">
                    <input type="<?= $multi ? 'checkbox' : 'radio' ?>" name="modifiers[]" value="<?= (int) $m['id'] ?>"
                      data-price="<?= (int) $m['price_delta_cents'] ?>"
                      <?= ($required && !$multi) ? 'required' : '' ?>>
                    <span class="opt__label"><?= e($m['name']) ?>
                      <?php if (!empty($m['note'])): ?><span class="opt__note"><?= e($m['note']) ?></span><?php endif; ?>
                    </span>
                    <?php if ((int) $m['price_delta_cents'] !== 0): ?>
                      <span class="opt__price">+ <?= e(fmt_money((int) $m['price_delta_cents'])) ?></span>
                    <?php endif; ?>
                  </label>
                <?php endforeach; ?>
              </fieldset>
            <?php endforeach; ?>

            <fieldset class="modgroup" id="grp-notes">
              <div class="modgroup__head">
                <legend class="modgroup__title">Special instructions</legend>
                <span class="modgroup__opt">Optional</span>
              </div>
              <div class="field item__notes">
                <label for="instructions" class="visually-hidden">Special instructions</label>
                <textarea class="textarea" id="instructions" name="instructions" rows="3" maxlength="300" placeholder="Allergies, no pickles, sauce on the side… (we'll do our best)"></textarea>
                <p class="field__hint">For severe allergies, please call (602) 499-5195.</p>
              </div>
            </fieldset>
          </div>

          <!-- PURCHASE BAR -->
          <?php $basePrice = !empty($variants) ? (int) $variants[0]['price_cents'] : (int) $product['price_cents']; ?>
          <div class="buy" data-base-price="<?= $basePrice ?>" data-has-variants="<?= !empty($variants) ? '1' : '0' ?>">
            <div class="buy__row">
              <div class="stepper" role="group" aria-label="Quantity">
                <button type="button" class="stepper__btn" aria-label="Decrease quantity" data-step="-1" id="qtyMinus" disabled>&minus;</button>
                <input type="text" inputmode="numeric" id="quantity" name="quantity" value="1" aria-label="Quantity" readonly>
                <button type="button" class="stepper__btn" aria-label="Increase quantity" data-step="1" id="qtyPlus">+</button>
              </div>
              <button type="submit" class="btn btn--solid btn--lg buy__add">
                Add to order&nbsp;·&nbsp;<span id="addPrice"><?= e(fmt_money($basePrice)) ?></span>
              </button>
            </div>
            <p class="buy__total">Live total <b id="liveTotal"><?= e(fmt_money($basePrice)) ?></b> <span class="muted" id="liveBreak"></span></p>
            <p class="form-error" id="formError" role="alert"></p>
          </div>
        </form>
      <?php endif; ?>

    </div>
  </div>
</main>

<?php if (empty($product['is_86'])): ?>
<script>
/* Item purchase UX: quantity stepper, live total, required-group validation.
 * Self-contained, does not touch shared js/app.js. Prices are computed in
 * cents from data-price attributes (server-rendered) and formatted as USD,
 * matching fmt_money(). Server-side `required` + validation still apply. */
(function () {
  var form = document.getElementById('itemForm') || document.querySelector('.item__form');
  if (!form) { return; }
  var buy = form.querySelector('.buy');
  if (!buy) { return; }

  var MINQTY = 1, MAXQTY = 20;
  var basePrice = parseInt(buy.getAttribute('data-base-price'), 10) || 0; // cents
  var hasVariants = buy.getAttribute('data-has-variants') === '1';
  var qtyInput = document.getElementById('quantity');
  var qty = parseInt(qtyInput.value, 10) || MINQTY;

  function moneyCents(c) { return '$' + (c / 100).toFixed(2); }

  function unitCents() {
    var unit = basePrice;
    if (hasVariants) {
      var v = form.querySelector('input[name="variant_id"]:checked');
      if (v) { unit = parseInt(v.getAttribute('data-price'), 10) || basePrice; }
    }
    form.querySelectorAll('input[name="modifiers[]"]:checked').forEach(function (m) {
      unit += parseInt(m.getAttribute('data-price'), 10) || 0;
    });
    return unit;
  }

  function recalc() {
    var unit = unitCents();
    var total = unit * qty;
    var add = document.getElementById('addPrice');
    var live = document.getElementById('liveTotal');
    var brk = document.getElementById('liveBreak');
    if (add) { add.textContent = moneyCents(total); }
    if (live) { live.textContent = moneyCents(total); }
    if (brk) { brk.textContent = qty > 1 ? '(' + moneyCents(unit) + ' each × ' + qty + ')' : ''; }
  }

  function setQty(n) {
    qty = Math.min(MAXQTY, Math.max(MINQTY, n));
    qtyInput.value = qty;
    var minus = document.getElementById('qtyMinus');
    var plus = document.getElementById('qtyPlus');
    if (minus) { minus.disabled = (qty <= MINQTY); }
    if (plus) { plus.disabled = (qty >= MAXQTY); }
    recalc();
  }

  buy.querySelectorAll('.stepper__btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setQty(qty + (parseInt(btn.getAttribute('data-step'), 10) || 0));
    });
  });

  form.addEventListener('change', function (e) {
    if (e.target && (e.target.name === 'variant_id' || e.target.name === 'modifiers[]')) {
      recalc();
    }
    if (e.target && e.target.closest('[data-required]')) {
      clearError();
    }
  });

  var err = document.getElementById('formError');
  function clearError() { if (err) { err.classList.remove('is-shown'); err.textContent = ''; } }
  function showError(group, msg) {
    if (err) { err.textContent = msg; err.classList.add('is-shown'); }
    if (group) {
      group.scrollIntoView({ behavior: 'smooth', block: 'center' });
      var first = group.querySelector('input');
      if (first) { first.focus(); }
    }
  }

  form.addEventListener('submit', function (e) {
    var groups = form.querySelectorAll('[data-required]');
    for (var i = 0; i < groups.length; i++) {
      var g = groups[i];
      if (!g.querySelector('input:checked')) {
        e.preventDefault();
        var label = g.getAttribute('data-group-label') || 'an option';
        showError(g, 'Please choose ' + label + ' before adding this to your order.');
        return false;
      }
    }
    clearError();
  });

  setQty(qty);
})();
</script>
<?php endif; ?>
