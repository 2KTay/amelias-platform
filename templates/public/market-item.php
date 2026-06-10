<?php
/**
 * Market product detail (screen 9 detail). On-brand two-column product layout
 * adapted from the menu item detail (item.php) for groceries & wine: sticky
 * product photo on the left, name/price/description and a styled size selection
 * on the right, plus a purchase area and the alcohol/pickup compliance note.
 *
 * Reuses the menu item add-to-cart pattern (_csrf, product_id, optional
 * variant_id, quantity). Alcohol items are pickup-only while the DTC gate is
 * closed; a 21+ note is shown. No doneness/modifier groups, market goods only
 * carry size variants.
 *
 * @var array<string,mixed> $product
 * @var list<array<string,mixed>> $variants
 * @var int|null $stock
 * @var bool $outOfStock
 * @var bool $lowStock
 * @var bool $pickupOnly
 * @var bool $ageGate
 * @var list<string> $allowedStates
 */
$variants   = $variants ?? [];
$outOfStock = (bool) ($outOfStock ?? false);
$lowStock   = (bool) ($lowStock ?? false);
$pickupOnly = (bool) ($pickupOnly ?? false);
$ageGate    = (bool) ($ageGate ?? false);
$img        = $product['image_path'] ?? null;
?>
<main class="container item-wrap market-item">

  <nav class="crumb" aria-label="Breadcrumb">
    <a href="<?= e(url('/market')) ?>">The Market</a>
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
    <span><?= e((string) $product['name']) ?></span>
  </nav>

  <div class="item">

    <!-- LEFT: photography -->
    <div class="item__media">
      <div class="item__photo">
        <?php if ($img): ?>
          <img src="<?= e(asset('uploads/' . ltrim((string) $img, '/'))) ?>" alt="<?= e((string) $product['name']) ?>" fetchpriority="high">
        <?php else: ?>
          <div class="item__photo-ph" role="img" aria-label="<?= e((string) $product['name']) ?>"></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- RIGHT: details + purchase -->
    <div class="item__detail">
      <div class="item__head">
        <h1 class="item__name"><?= e((string) $product['name']) ?></h1>
        <span class="item__price"><?= e(fmt_money((int) $product['price_cents'])) ?></span>
      </div>

      <?php if (!empty($product['description'])): ?>
        <p class="item__desc"><?= e((string) $product['description']) ?></p>
      <?php endif; ?>

      <?php if ($ageGate || $lowStock): ?>
        <div class="item__tags">
          <?php if ($ageGate): ?>
            <span class="tag tag--age">21+</span>
            <?php if ($pickupOnly): ?><span class="tag tag--pickup">Pickup only</span><?php endif; ?>
          <?php endif; ?>
          <?php if ($lowStock && !$outOfStock && $stock !== null): ?>
            <span class="tag tag--low">Only <?= (int) $stock ?> left</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($ageGate): ?>
        <p class="market-note">
          <strong>21+ only.</strong> Valid ID is required at pickup; we cannot release alcohol to anyone under 21.
          <?php if ($pickupOnly): ?> This item is available for <strong>in-store pickup only</strong>, we do not ship alcohol.<?php endif; ?>
        </p>
      <?php endif; ?>

      <?php if ($outOfStock): ?>
        <div class="alert" role="status">This item is currently sold out. Check back soon.</div>
        <p class="btn-row mt-4"><a class="btn" href="<?= e(url('/market')) ?>">Back to the market</a></p>
      <?php else: ?>
        <form method="post" action="<?= e(url('/cart/add')) ?>" class="item__form">
          <?= csrf_field() ?>
          <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

          <div class="mods">
            <?php if (!empty($variants)): ?>
              <fieldset class="modgroup" id="grp-size">
                <div class="modgroup__head">
                  <legend class="modgroup__title">Size</legend>
                  <span class="modgroup__req">Required · choose 1</span>
                </div>
                <?php foreach ($variants as $i => $v): ?>
                  <label class="opt">
                    <input type="radio" name="variant_id" value="<?= (int) $v['id'] ?>" <?= $i === 0 ? 'checked' : '' ?> required>
                    <span class="opt__label"><?= e((string) $v['name']) ?></span>
                    <span class="opt__price"><?= e(fmt_money((int) $v['price_cents'])) ?></span>
                  </label>
                <?php endforeach; ?>
              </fieldset>
            <?php endif; ?>
          </div>

          <!-- PURCHASE BAR -->
          <div class="buy">
            <div class="buy__row">
              <div class="field buy__qty">
                <label for="quantity" class="visually-hidden">Quantity</label>
                <input class="input" type="number" id="quantity" name="quantity" value="1" min="1" max="20">
              </div>
              <button type="submit" class="btn btn--solid btn--lg buy__add">
                Add to cart · <?= e(fmt_money((int) $product['price_cents'])) ?>
              </button>
            </div>
          </div>
        </form>
      <?php endif; ?>

    </div>
  </div>
</main>
