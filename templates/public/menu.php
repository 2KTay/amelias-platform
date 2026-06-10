<?php
/** Menu (screen 4), day-part tabs + sections, rendered from the catalog. */
$dayParts = $dayParts ?? [];
$grouped  = $grouped ?? [];
// Only show tabs that actually have items seeded.
$activeTabs = array_filter($dayParts, static fn ($label, $dp) => !empty($grouped[$dp]), ARRAY_FILTER_USE_BOTH);
$firstDp = array_key_first($activeTabs);
// Per-day-part subtitle + print PDF, matching the approved mockup.
$dpMeta = [
  'day'        => ['meta' => 'EAT classics, scratch made, locally sourced, seed-oil free.', 'pdf' => 'Amelias-Day-Menu-1-1.pdf'],
  'pm'         => ['meta' => 'Table service, build-your-own boards, scratch cocktails &amp; local wine.', 'pdf' => 'Pm-Menu-.pdf'],
  'happy_hour' => ['meta' => '4–6 pm daily, at the bar.', 'pdf' => 'Happy-Hour-Menu.pdf'],
  'catering'   => ['meta' => 'Platters that serve 12 or 24, prices shown as 12 | 24.', 'pdf' => 'Catering-Menu-.pdf'],
];
?>
<header class="page-head">
  <div class="container">
    <h1 class="disp">Menus</h1>
    <p>Scratch made, locally sourced, seed-oil free. Counter service through the day, table service and build-your-own boards in the evening, plus happy hour at the bar and platters to cater your table.</p>
  </div>
</header>

<section class="section section--tight-top">
  <div class="container">
    <?php if (empty($activeTabs)): ?>
      <div class="alert">Our online menu is being finalized. <a href="<?= e(url('/location')) ?>">Call or visit us</a> in the meantime.</div>
    <?php else: ?>
      <div class="menu-tabs menu-tabs--sticky" role="tablist" aria-label="Menu day parts" data-menu-tabs>
        <?php foreach ($activeTabs as $dp => $label): ?>
          <button class="menu-tab" role="tab" id="tab-<?= e($dp) ?>"
                  aria-controls="panel-<?= e($dp) ?>"
                  aria-selected="<?= $dp === $firstDp ? 'true' : 'false' ?>"><?= e($label) ?></button>
        <?php endforeach; ?>
      </div>

      <?php foreach ($activeTabs as $dp => $label): ?>
        <div id="panel-<?= e($dp) ?>" class="menu-panel" role="tabpanel" aria-labelledby="tab-<?= e($dp) ?>"<?= $dp === $firstDp ? '' : ' hidden' ?>>
          <div class="head head--row menu-panel__head">
            <div>
              <h2 class="disp"><?= e($label) ?></h2>
              <?php if (!empty($dpMeta[$dp]['meta'])): ?>
                <p class="menu-meta"><?= $dpMeta[$dp]['meta'] ?></p>
              <?php endif; ?>
            </div>
            <?php if (!empty($dpMeta[$dp]['pdf'])): ?>
              <a class="menu-pdf" href="<?= e(asset($dpMeta[$dp]['pdf'])) ?>">View / print PDF</a>
            <?php endif; ?>
          </div>

          <?php if ($dp === 'catering'): ?>
            <p class="menu-section__note">Order party platters at least 48 hours ahead. We suggest 3–4 platters to serve a group of 12 generously.</p>
          <?php endif; ?>

          <div class="menu-cols">
            <?php foreach ($grouped[$dp] as $category => $items): ?>
              <div class="menu-section">
                <h3><?= e($category) ?></h3>
                <?php if ($dp === 'catering'): ?>
                  <div class="cater-head"><span>Item</span><span>12 | 24</span></div>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                  <div class="menu-item<?= $item['is_86'] ? ' menu-item--soldout' : '' ?><?= empty($item['image_path']) ? ' menu-item--noimg' : '' ?>">
                    <?php if (!empty($item['image_path'])): ?>
                      <img class="menu-item__thumb" src="<?= e(asset('uploads/' . ltrim((string) $item['image_path'], '/'))) ?>" alt="<?= e((string) $item['name']) ?>" loading="lazy" width="64" height="64">
                    <?php endif; ?>
                    <span class="menu-item__name">
                      <?= e($item['name']) ?>
                      <?php if ($item['is_86']): ?><span class="tag">Sold out</span><?php endif; ?>
                    </span>
                    <span class="menu-item__price">
                      <?php if (!empty($item['variants'])): ?>
                        <?php foreach ($item['variants'] as $i => $v): ?><?= $i ? ' / ' : '' ?><?= e(fmt_money((int) $v['price_cents'])) ?><?php endforeach; ?>
                      <?php else: ?>
                        <?= e(fmt_money((int) $item['price_cents'])) ?>
                      <?php endif; ?>
                    </span>
                    <?php if (!empty($item['description'])): ?>
                      <p class="menu-item__desc"><?= e($item['description']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($item['dietary_tags'])): ?>
                      <div class="menu-item__tags">
                        <?php foreach ($item['dietary_tags'] as $tag): ?>
                          <span class="tag" title="<?= e($tag['label']) ?>"><?= e(strtoupper($tag['slug'])) ?></span>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                    <?php if (!$item['is_86']): ?>
                      <div class="menu-item__actions">
                        <?php if (!empty($item['variants'])): ?>
                          <a class="btn btn--sm" href="<?= e(url('/menu/' . $item['slug'])) ?>">Choose size</a>
                        <?php else: ?>
                          <form method="post" action="<?= e(url('/cart/add')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button class="btn btn--sm" type="submit">Add</button>
                          </form>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <?php /* Run-on add-on / footnote line (mockup .addons: BYOB, "Add proteins", a-la-carte).
                           Renders only when seed data carries an addon/footnote string for the item.
                           No catalog column exists for this yet, see report (seed data required). */ ?>
                  <?php if (!empty($item['addons'])): ?>
                    <div class="addons"><?= $item['addons'] ?></div>
                  <?php endif; ?>
                <?php endforeach; ?>
                <?php /* Section-level add-on line (e.g. "Smaller Appetites", "A La Carte" run-on rows).
                         Renders only when a section carries addon text; no data field yet (see report). */ ?>
                <?php if (!empty($sectionAddons[$dp][$category])): ?>
                  <div class="addons"><?= $sectionAddons[$dp][$category] ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if ($dp === 'catering'): ?>
            <p class="catering-cta">
              <a class="btn btn--solid" href="<?= e(url('/catering')) ?>">Cater your next event</a>
            </p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <!-- COMPLIANCE / FOOTER NOTES -->
      <div class="menu-foot">
        <p><span class="star">Gluten-free.</span> Enjoy peace-of-mind dining at Amelia's, if you are gluten-free, nearly all of our menu items can be prepared gluten-free by request.</p>
        <p><span class="star">*</span> Consuming raw or undercooked meats, poultry, seafood or eggs may increase your risk of foodborne illness, especially if you have certain medical conditions.</p>
        <p class="muted">Eat + drink local, scratch made, locally sourced, seed-oil free. Find us at ameliasaz.com · (602) 499-5195. Cater your next event with us!</p>
      </div>

      <p class="btn-row mt-6">
        <a class="btn btn--solid" href="<?= e(url('/reserve')) ?>">Reserve a table</a>
        <a class="btn" href="<?= e(url('/catering')) ?>">Catering &amp; platters</a>
      </p>
    <?php endif; ?>
  </div>
</section>
