<?php
/**
 * Checkout (screen 7). Contact/guest fields, Stripe Payment Element (mounted on
 * the confirmation page after the order + PaymentIntent are created), order
 * summary with per-category tax. SAQ-A: card data never touches our server.
 *
 * @var array<string,mixed> $pricing
 * @var array<string,mixed> $cart
 * @var array{id:int,email:string,name:string}|null $customer
 * @var bool $stripeReady
 * @var string $publishableKey
 * @var array{type:string,message:string}|null $flash
 */
$firstName = '';
$lastName  = '';
if ($customer !== null && ($customer['name'] ?? '') !== '') {
    $parts = explode(' ', trim((string) $customer['name']), 2);
    $firstName = $parts[0] ?? '';
    $lastName  = $parts[1] ?? '';
}
?>
<header class="page-head">
  <div class="container">
    <a class="link-arrow link-arrow__back checkout-back" href="<?= e(url('/cart')) ?>">
      <svg class="link-arrow__back-icon" width="20" height="12" viewBox="0 0 20 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 6h17M13 1l5 5-5 5"/></svg>
      Back to cart
    </a>
    <h1 class="disp">Checkout</h1>
    <p>Pickup at 8240 N Hayden Road, Ste B-105, Scottsdale. We'll text you the moment your order is ready at the counter.</p>
  </div>
</header>

<section class="section">
  <div class="container checkout">

    <!-- LEFT: contact + payment -->
    <form id="checkout-form" method="post" action="<?= e(url('/checkout')) ?>">
      <?= csrf_field() ?>

      <?php if (!empty($flash)): ?>
        <div class="alert <?= $flash['type'] === 'error' ? 'alert--danger' : 'alert--ok' ?>" role="status"><?= e($flash['message']) ?></div>
      <?php endif; ?>

      <?php if ($customer === null): ?>
        <div class="login-row">
          <p>Have an account? Sign in to autofill your details and reuse a saved card.</p>
          <a class="btn" href="<?= e(url('/login')) ?>">Log in</a>
        </div>
      <?php endif; ?>

      <div class="co-block">
        <h2 class="disp">Your details</h2>
        <p class="co-block__note">So we can reach you about this pickup order.</p>
        <div class="field-row">
          <div class="field">
            <label for="fname">First name <span class="req">*</span></label>
            <input class="input" id="fname" name="first_name" autocomplete="given-name" value="<?= e($firstName) ?>" required>
          </div>
          <div class="field">
            <label for="lname">Last name <span class="req">*</span></label>
            <input class="input" id="lname" name="last_name" autocomplete="family-name" value="<?= e($lastName) ?>" required>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="phone">Mobile phone <span class="req">*</span></label>
            <input class="input" id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="(602) 555-0148" required>
            <p class="field__hint">For your order-ready text. We won't spam you.</p>
          </div>
          <div class="field">
            <label for="email">Email <span class="req">*</span></label>
            <input class="input" id="email" name="email" type="email" inputmode="email" autocomplete="email" value="<?= e($customer['email'] ?? '') ?>" placeholder="you@email.com" required>
            <p class="field__hint">Your receipt lands here.</p>
          </div>
        </div>
      </div>

      <div class="co-block">
        <h2 class="disp">Payment</h2>
        <?php if (!$stripeReady): ?>
          <p class="co-block__note">Pay at pickup. Online card payment is coming soon.</p>
        <?php else: ?>
          <p class="co-block__note">Card details are entered in secure fields hosted by Stripe, so they never touch Amelia's servers.</p>
          <div class="stripe-el">
            <div class="stripe-el__head">
              <span class="stripe-el__brand">Secure card fields</span>
              <span class="badge badge--ok">PCI · SAQ-A</span>
            </div>
            <p class="co-block__note">Apple&nbsp;Pay / Google&nbsp;Pay and card fields appear on the next step once your order is reserved.</p>
          </div>
        <?php endif; ?>
      </div>

    </form>

    <!-- RIGHT: order summary -->
    <aside class="summary" aria-label="Order summary">
      <h2 class="disp">Order summary</h2>
      <?php if (($cart['pickup_mode'] ?? 'asap') === 'asap'): ?>
        <p class="summary__pickup">Pickup today · ready ~20 min</p>
      <?php elseif (!empty($cart['pickup_slot_id'])): ?>
        <p class="summary__pickup">Pickup today · scheduled slot reserved</p>
      <?php endif; ?>

      <?php foreach ($pricing['lines'] as $line): ?>
        <div class="sum-item">
          <span class="sum-item__name"><span class="sum-item__qty"><?= e((string) $line['quantity']) ?>×</span> <?= e((string) $line['name']) ?></span>
          <span class="sum-item__price"><?= e(fmt_money((int) $line['line_total_cents'], false)) ?></span>
          <?php foreach ($line['modifiers'] as $mod): ?>
            <span class="sum-item__mod"><?= e((string) $mod['name']) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

      <div class="totals">
        <div class="totals__row"><span>Subtotal</span><span><?= e(fmt_money((int) $pricing['subtotal_cents'], false)) ?></span></div>
        <?php if ((int) $pricing['discount_cents'] > 0): ?>
          <div class="totals__row"><span>Discount<?= !empty($pricing['promo_code']) ? ' · ' . e((string) $pricing['promo_code']) : '' ?></span><span>−<?= e(fmt_money((int) $pricing['discount_cents'], false)) ?></span></div>
        <?php endif; ?>
        <?php foreach ($pricing['tax_lines'] as $tl): ?>
          <div class="totals__row"><span>AZ TPT, <?= e(ucwords(str_replace('_', ' ', (string) $tl['tax_category']))) ?> (<?= e(number_format((int) $tl['rate_bps'] / 100, 2)) ?>%)</span><span><?= e(fmt_money((int) $tl['tax_cents'], false)) ?></span></div>
          <div class="totals__row totals__sub"><span>on <?= e(fmt_money((int) $tl['taxable_base_cents'])) ?></span><span></span></div>
        <?php endforeach; ?>
        <?php if ((int) $pricing['tip_cents'] > 0): ?>
          <div class="totals__row"><span>Tip</span><span><?= e(fmt_money((int) $pricing['tip_cents'], false)) ?></span></div>
        <?php endif; ?>

        <div class="totals__grand">
          <span class="lbl">Total</span>
          <span class="amt"><?= e(fmt_money((int) $pricing['total_cents'])) ?></span>
        </div>
        <?php if ((int) $pricing['gift_card_applied_cents'] > 0): ?>
          <div class="totals__row"><span>Gift card</span><span>−<?= e(fmt_money((int) $pricing['gift_card_applied_cents'], false)) ?></span></div>
          <div class="totals__grand"><span class="lbl">Due now</span><span class="amt"><?= e(fmt_money((int) $pricing['amount_due_cents'])) ?></span></div>
        <?php endif; ?>
      </div>

      <button type="submit" form="checkout-form" class="btn btn--solid btn--lg btn--block">Place order · <?= e(fmt_money((int) $pricing['amount_due_cents'])) ?></button>
      <p class="summary__terms">By placing your order you agree to Amelia's pickup terms. Card processed securely by Stripe.</p>
    </aside>

  </div>
</section>
