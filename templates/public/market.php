<?php
/**
 * The Market (screen 9), retail storefront ported from mockups/market.html.
 *
 * Renders products.type='market' grouped by category. Each in-stock card posts
 * to /cart/add with the same form shape the menu uses (_csrf, product_id,
 * quantity). Alcohol items are pickup-only while the DTC gate is closed.
 *
 * @var array<string,list<array<string,mixed>>> $grouped  category => items
 * @var bool        $dtcEnabled
 * @var array|null  $flash
 */
$grouped    = $grouped ?? [];
$dtcEnabled = (bool) ($dtcEnabled ?? false);
$flash      = $flash ?? null;
// Editorial copy per market section (matches the approved mockup), keyed by the
// seeded category name. Shown when the category is present; otherwise just the heading.
$catCopy = [
  'Wine'           => ['desc' => 'A wall of top-quality wine, sustainable, organic, biodynamic, and natural bottles chosen by our in-house somms. Mix and match any six bottles for 10% off.', 'note' => 'Six bottles, mix & match, 10% off'],
  'Grab & Go'      => ['desc' => 'Locally sourced prepared foods ready to take home, plus our seasonal organic scratch-made frozen soups, stews, and bone broth, a gluten-free bakery, and the cold case.', 'note' => 'Made here, ready to go'],
  'Home & Gifting' => ['desc' => 'Pantry items, glassware, and linens, custom-scented Amelia\'s candles, potted succulents, organic coffee beans, and a gift wall sourced from small businesses we love.', 'note' => 'A gift wall from makers we love'],
];
?>
<section class="page-hero">
  <img src="<?= e(asset('img/themarket.jpg')) ?>" alt="Overhead of Amelia's market goods, house granola, oils, salts, root chips and energy bites">
  <div class="page-hero__in">
    <h1 class="disp">The Market</h1>
    <p>Every saleable grocery item sold in our market is used in Amelia's kitchen. From specialty salts to homemade granola, you'll find a unique assortment of grab-and-go food, beverages, and grocery items to take home, or give as gifts.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <?php if ($flash !== null): ?>
      <div class="alert <?= ($flash['type'] ?? '') === 'error' ? 'alert--danger' : 'alert--ok' ?>" role="status"><?= e((string) ($flash['message'] ?? '')) ?></div>
    <?php endif; ?>

    <div class="market-intro">
      <span class="kicker">Source to plate, then to your pantry</span>
      <p class="lede market-intro__lede">Wander the shelves the way you'd wander our menu. Three corners to explore, the wine wall, the grab-and-go cooler, and a home-and-gift table sourced from makers we love.</p>
    </div>
  </div>
</section>

<?php if (empty($grouped)): ?>
  <section class="section"><div class="container">
    <div class="alert" role="status">Our market catalog is being finalized. <a href="<?= e(url('/location')) ?>">Visit us</a> to shop the shelves in the meantime.</div>
  </div></section>
<?php else: ?>
  <?php $sectionIndex = 0; ?>
  <?php foreach ($grouped as $category => $items): ?>
    <hr class="rule">
    <section class="cat<?= $sectionIndex % 2 === 1 ? ' section--soft' : '' ?>"><div class="container">
      <div class="cat__head">
        <div>
          <h2 class="disp"><?= e((string) $category) ?></h2>
          <?php if (!empty($catCopy[$category]['desc'])): ?>
            <p><?= e($catCopy[$category]['desc']) ?></p>
          <?php endif; ?>
        </div>
        <?php if (!empty($catCopy[$category]['note'])): ?>
          <span class="cat__note"><?= e($catCopy[$category]['note']) ?></span>
        <?php endif; ?>
      </div>
      <div class="grid grid--3">
        <?php foreach ($items as $item): ?>
          <?php
            $pid       = (int) $item['id'];
            $isAlcohol = (bool) ($item['age_gate'] ?? false);
            $pickup    = (bool) ($item['pickup_only'] ?? false);
            $soldOut   = (bool) ($item['out_of_stock'] ?? false);
            $lowStock  = (bool) ($item['low_stock'] ?? false);
            $stock     = $item['stock'];
          ?>
          <article class="card market-card<?= $soldOut ? ' market-card--soldout' : '' ?>">
            <?php if (!empty($item['image_path'])): ?>
              <div class="card__media"><img src="<?= e(asset('uploads/' . ltrim((string) $item['image_path'], '/'))) ?>" alt="<?= e((string) $item['name']) ?>"></div>
            <?php endif; ?>
            <div class="card__body">
              <div class="card__row">
                <span class="card__title"><?= e((string) $item['name']) ?></span>
                <span class="price"><?= e(fmt_money((int) $item['price_cents'])) ?></span>
              </div>
              <?php if (!empty($item['description'])): ?>
                <p><?= e((string) $item['description']) ?></p>
              <?php endif; ?>

              <p class="market-card__flags">
                <?php if ($isAlcohol): ?>
                  <span class="tag tag--age">21+</span>
                  <?php if ($pickup): ?><span class="tag tag--pickup">Pickup only</span><?php endif; ?>
                <?php endif; ?>
                <?php if ($soldOut): ?>
                  <span class="tag">Sold out</span>
                <?php elseif ($lowStock && $stock !== null): ?>
                  <span class="tag tag--low">Only <?= (int) $stock ?> left</span>
                <?php endif; ?>
              </p>

              <div class="market-card__actions">
                <?php if ($soldOut): ?>
                  <button class="btn btn--sm" type="button" disabled>Sold out</button>
                <?php else: ?>
                  <form method="post" action="<?= e(url('/cart/add')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= $pid ?>">
                    <input type="hidden" name="quantity" value="1">
                    <button class="btn btn--sm" type="submit">Add to cart</button>
                  </form>
                <?php endif; ?>
                <a class="ulink" href="<?= e(url('/market/' . $item['slug'])) ?>">Details</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div></section>
    <?php $sectionIndex++; ?>
  <?php endforeach; ?>
<?php endif; ?>

<?php if (!$dtcEnabled): ?>
  <section class="section"><div class="container">
    <p class="market-note">All wine and alcohol is sold for in-store pickup only, valid ID (21+) is required at pickup. We do not currently ship alcohol.</p>
  </div></section>
<?php endif; ?>

<section class="split">
  <div class="split__media"><img src="<?= e(asset('img/Cooler-1405x1536.jpg')) ?>" alt="Amelia's grab-and-go cooler, fully stocked"></div>
  <div class="split__body section--soft">
    <span class="kicker">Pop in any day</span>
    <h2 class="disp market-split__h">Stop by the market</h2>
    <p>The shelves, cooler, and wine wall are open all day. When you eat well, you feel good, take a little of Amelia's home with you.</p>
    <p class="btn-row mt-6">
      <a class="btn btn--solid" href="<?= e(url('/menu')) ?>">Order Pickup</a>
      <a class="btn" href="<?= e(url('/location')) ?>">Visit &amp; hours</a>
    </p>
  </div>
</section>
