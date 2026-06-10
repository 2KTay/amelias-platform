<?php
/**
 * Mini-cart drawer (progressive enhancement).
 *
 * Hidden by default and only revealed by app.js after a fetch-based add or when
 * the cart icon / mobile bar is tapped. Renders as a right-side slide-in panel
 * on desktop and a bottom sheet on mobile. With JS off it never appears and the
 * cart icon falls back to a normal link to /cart, which stays fully functional.
 *
 * Line rows + subtotal are injected client-side from the CartController JSON
 * payload; this file is only the shell + states.
 */
?>
<div class="cart-drawer" data-cart-drawer hidden>
  <div class="cart-drawer__backdrop" data-cart-close></div>
  <aside class="cart-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="cart-drawer-title" tabindex="-1">
    <header class="cart-drawer__head">
      <h2 class="cart-drawer__title" id="cart-drawer-title">Your cart</h2>
      <button type="button" class="cart-drawer__close" data-cart-close aria-label="Close cart">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </header>

    <div class="cart-drawer__body" data-cart-body aria-live="polite">
      <!-- lines injected by app.js -->
    </div>

    <footer class="cart-drawer__foot" data-cart-foot hidden>
      <div class="cart-drawer__subtotal">
        <span>Subtotal</span>
        <span data-cart-subtotal>$0.00</span>
      </div>
      <p class="cart-drawer__note">Taxes &amp; pickup time are set at checkout.</p>
      <a class="btn btn--solid btn--lg btn--block" href="<?= e(url('/cart')) ?>" data-cart-checkout>View cart &amp; checkout</a>
    </footer>
  </aside>
</div>

<button class="cart-bar" type="button" data-cart-toggle hidden>
  <span class="cart-bar__count" data-cart-count>0</span>
  <span class="cart-bar__label">View cart</span>
  <span class="cart-bar__total" data-cart-subtotal></span>
</button>
