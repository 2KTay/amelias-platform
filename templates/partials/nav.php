<?php
/** Public site navigation. IA per spec v3: ≤6 primary items + Reserve/Order CTAs. */
$cartCount = \Amelias\Services\Cart::fromSession()->count();
$current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (base_path() !== '' && str_starts_with($current, base_path())) {
    $current = substr($current, strlen(base_path())) ?: '/';
}
$links = [
    '/menu'           => 'Menu',
    '/market'         => 'Market',
    '/wine-club'      => 'Wine Club',
    '/sunday-supper'  => 'Sunday Supper',
    '/catering'       => 'Catering',
    '/story'          => 'Our Story',
];
?>
<header class="nav">
  <div class="nav__in">
    <a href="<?= e(url('/')) ?>" aria-label="<?= e(config('name')) ?>, home">
      <img class="nav__logo" src="<?= e(asset('brand/AmeliasbyEatLogo-Final-Color.svg')) ?>" alt="<?= e(config('name')) ?>">
    </a>

    <nav id="primary-nav" class="nav__links" aria-label="Primary">
      <?php foreach ($links as $href => $label): ?>
        <a class="lnk" href="<?= e(url($href)) ?>"<?= str_starts_with($current, $href) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <span class="nav__cta">
        <a class="btn btn--sm" href="<?= e(url('/reserve')) ?>">Reserve</a>
        <a class="btn btn--solid btn--sm" href="<?= e(url('/menu')) ?>">Order</a>
      </span>
    </nav>

    <div class="nav__right">
      <a class="nav__cart" href="<?= e(url('/cart')) ?>" aria-label="View cart" data-cart-toggle>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3h2.2l2.2 11.2a1.6 1.6 0 0 0 1.6 1.3h7.8a1.6 1.6 0 0 0 1.6-1.3L20.5 7H6"/></svg>
        <span class="nav__cart-count" data-cart-count<?= $cartCount > 0 ? '' : ' hidden' ?>><?= e((string) $cartCount) ?></span>
      </a>
      <button class="nav__burger" type="button" aria-expanded="false" aria-controls="primary-nav" aria-label="Open menu" data-nav-toggle>
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
    </div>
  </div>
</header>
