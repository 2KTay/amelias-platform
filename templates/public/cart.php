<?php
/**
 * Cart (screen 6). Edit/remove lines, ASAP/scheduled pickup picker (full slots
 * disabled), tip, promo + gift-card fields, reservable-stock guard messaging.
 *
 * @var list<array<string,mixed>> $lines    resolved + stock-annotated
 * @var array<string,mixed> $pricing
 * @var array<string,mixed> $cart           raw session state
 * @var list<array<string,mixed>> $slots     open pickup slots
 * @var list<int> $tipPresets
 * @var array{type:string,message:string}|null $flash
 */
$pickupMode = (string) ($cart['pickup_mode'] ?? 'asap');
$selectedSlot = $cart['pickup_slot_id'] ?? null;
?>
<header class="page-head">
  <div class="container">
    <h1 class="disp">Your cart</h1>
    <p>Pickup from 8240 N Hayden Rd, Ste B-105 · Scottsdale. Review your order, pick a time, and we'll have it ready at the counter.</p>
  </div>
</header>

<div class="container">
  <?php if (!empty($flash)): ?>
    <?php if ($flash['type'] === 'error'): ?>
      <div class="alert alert--danger" role="alert"><?= e($flash['message']) ?></div>
    <?php else: ?>
      <div class="alert alert--ok flash" role="status" data-autodismiss><?= e($flash['message']) ?></div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($lines === []): ?>
    <div class="cart-empty">
      <p class="disp">Your cart is empty.</p>
      <p class="muted">Add something scratch-made from the menu to get started.</p>
      <a class="btn btn--solid" href="<?= e(url('/menu')) ?>">Browse the menu</a>
    </div>
  <?php else: ?>
  <div class="cart">

    <!-- LEFT: line items -->
    <section aria-label="Items in your order">
      <a class="link-arrow cart__keep" href="<?= e(url('/menu')) ?>">
        <svg class="link-arrow__back" width="20" height="12" viewBox="0 0 20 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 6h17M13 1l5 5-5 5"/></svg>
        Keep shopping
      </a>
      <ul class="lines">
        <?php foreach ($lines as $line): ?>
          <li class="line">
            <?php if (!empty($line['image_path'])): ?>
              <img class="line__img" src="<?= e(asset('uploads/' . ltrim((string) $line['image_path'], '/'))) ?>" alt="<?= e((string) $line['name']) ?>">
            <?php else: ?>
              <span class="line__img line__img--ph" aria-hidden="true"></span>
            <?php endif; ?>
            <div>
              <div class="line__name"><?= e((string) $line['name']) ?></div>
              <?php if ($line['modifiers'] !== [] || $line['instructions'] !== ''): ?>
                <ul class="line__mods">
                  <?php foreach ($line['modifiers'] as $mod): ?>
                    <li><?= e((string) $mod['name']) ?><?= (int) $mod['price_delta_cents'] !== 0 ? ' (' . e(fmt_money((int) $mod['price_delta_cents'])) . ')' : '' ?></li>
                  <?php endforeach; ?>
                  <?php if ($line['instructions'] !== ''): ?>
                    <li><?= e((string) $line['instructions']) ?></li>
                  <?php endif; ?>
                </ul>
              <?php endif; ?>
              <?php if (!empty($line['stock_warn'])): ?>
                <div class="line__guard"><span class="badge badge--warn"><?= e((string) $line['stock_warn']) ?></span></div>
              <?php endif; ?>
              <div class="line__actions">
                <form method="post" action="<?= e(url('/cart/remove')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="line_key" value="<?= e((string) $line['key']) ?>">
                  <button type="submit" class="linkbtn is-danger">Remove</button>
                </form>
              </div>
            </div>
            <div class="line__right">
              <span class="line__price"><?= e(fmt_money((int) $line['line_total_cents'])) ?></span>
              <form class="qty" method="post" action="<?= e(url('/cart/update')) ?>" role="group" aria-label="Quantity for <?= e((string) $line['name']) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="line_key" value="<?= e((string) $line['key']) ?>">
                <button type="submit" name="quantity" value="<?= e((string) ((int) $line['quantity'] - 1)) ?>" aria-label="Decrease quantity">−</button>
                <span><?= e((string) $line['quantity']) ?></span>
                <button type="submit" name="quantity" value="<?= e((string) ((int) $line['quantity'] + 1)) ?>" aria-label="Increase quantity">+</button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="cart__back">
        <span class="muted cart__note">All items are made to order, scratch-made daily.</span>
      </div>
    </section>

    <!-- RIGHT: order summary + pickup -->
    <aside aria-label="Order summary">
      <div class="summary">
        <h2 class="disp">Order summary</h2>

        <dl class="summary__totals">
          <div class="sum-row"><dt>Subtotal · <?= e((string) count($lines)) ?> item<?= count($lines) === 1 ? '' : 's' ?></dt><dd><?= e(fmt_money((int) $pricing['subtotal_cents'])) ?></dd></div>
          <?php if ((int) $pricing['discount_cents'] > 0): ?>
            <div class="sum-row"><dt>Discount<?= !empty($pricing['promo_code']) ? ' · ' . e((string) $pricing['promo_code']) : '' ?></dt><dd>−<?= e(fmt_money((int) $pricing['discount_cents'])) ?></dd></div>
          <?php endif; ?>
          <?php foreach ($pricing['tax_lines'] as $tl): ?>
            <div class="sum-row"><dt>AZ TPT <span class="muted-note"><?= e(ucwords(str_replace('_', ' ', (string) $tl['tax_category']))) ?> · <?= e(number_format((int) $tl['rate_bps'] / 100, 2)) ?>%</span></dt><dd><?= e(fmt_money((int) $tl['tax_cents'])) ?></dd></div>
          <?php endforeach; ?>
          <div class="sum-row"><dt>Tip</dt><dd><?= e(fmt_money((int) $pricing['tip_cents'])) ?></dd></div>
        </dl>

        <!-- tip selector -->
        <form class="sum-block" method="post" action="<?= e(url('/cart')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="tip">
          <p class="sum-block__h">Add a tip <span class="muted sum-block__sub">100% to the team</span></p>
          <div class="tips" role="group" aria-label="Tip amount">
            <?php foreach ($tipPresets as $pct): $mode = 'preset:' . $pct; ?>
              <button type="submit" name="tip_mode" value="<?= e($mode) ?>" class="tip" aria-pressed="<?= ($cart['tip_mode'] ?? '') === $mode ? 'true' : 'false' ?>"><?= e((string) $pct) ?>%<small><?= e(fmt_money(bps((int) $pricing['subtotal_cents'], $pct * 100))) ?></small></button>
            <?php endforeach; ?>
            <button type="submit" name="tip_mode" value="none" class="tip" aria-pressed="<?= ($cart['tip_mode'] ?? 'none') === 'none' ? 'true' : 'false' ?>">None<small>&nbsp;</small></button>
          </div>
          <div class="tip-custom">
            <label class="visually-hidden" for="tip-custom">Custom tip in dollars</label>
            <input class="input" id="tip-custom" type="number" name="tip_amount" min="0" step="0.50" placeholder="Custom $">
            <button type="submit" name="tip_mode" value="custom" class="btn btn--sm">Set</button>
          </div>
        </form>

        <!-- promo / gift card -->
        <form class="sum-block" method="post" action="<?= e(url('/cart')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="promo">
          <label class="sum-block__h" for="promo">Promo code</label>
          <div class="promo">
            <input class="input" id="promo" type="text" name="promo_code" value="<?= e((string) ($cart['promo_code'] ?? '')) ?>" placeholder="Enter code" autocomplete="off">
            <button type="submit" class="btn">Apply</button>
          </div>
        </form>

        <form class="sum-block" method="post" action="<?= e(url('/cart')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="giftcard">
          <label class="sum-block__h" for="giftcard">Gift card</label>
          <div class="promo">
            <input class="input" id="giftcard" type="text" name="gift_card_code" value="<?= e((string) ($cart['gift_card_code'] ?? '')) ?>" placeholder="Card code" autocomplete="off">
            <button type="submit" class="btn">Apply</button>
          </div>
          <?php if ((int) $pricing['gift_card_applied_cents'] > 0): ?>
            <p class="field__hint">Gift card will cover <?= e(fmt_money((int) $pricing['gift_card_applied_cents'])) ?> at checkout.</p>
          <?php endif; ?>
        </form>

        <!-- pickup time -->
        <form class="sum-block" method="post" action="<?= e(url('/cart')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="pickup">
          <p class="sum-block__h">Pickup time</p>
          <div class="when" role="group" aria-label="When to pick up">
            <button type="submit" name="pickup_mode" value="asap" class="when__opt" aria-pressed="<?= $pickupMode === 'asap' ? 'true' : 'false' ?>">ASAP<small>Ready in ~20 min</small></button>
            <button type="submit" name="pickup_mode" value="scheduled" class="when__opt" aria-pressed="<?= $pickupMode === 'scheduled' ? 'true' : 'false' ?>">Schedule<small>Pick a slot today</small></button>
          </div>
          <?php if ($pickupMode === 'scheduled'): ?>
            <div class="slots" role="group" aria-label="Available pickup slots">
              <?php if ($slots === []): ?>
                <p class="slots-note">No pickup slots are open right now, choose ASAP or check back soon.</p>
              <?php else: foreach ($slots as $slot): ?>
                <button type="submit" name="pickup_slot_id" value="<?= e((string) $slot['id']) ?>" class="slot" aria-pressed="<?= (int) $selectedSlot === (int) $slot['id'] ? 'true' : 'false' ?>"<?= (int) $slot['remaining'] <= 0 ? ' disabled aria-disabled="true"' : '' ?>><?= e(fmt_date((string) $slot['slot_start'], 'g:ia')) ?><?php if ((int) $slot['remaining'] <= 0): ?><small>Full</small><?php endif; ?></button>
              <?php endforeach; endif; ?>
            </div>
            <p class="slots-note">Kitchen runs Mon–Thu 7a–8p. Slots release in 15-minute windows.</p>
          <?php endif; ?>
        </form>

        <dl class="summary__grand">
          <div class="sum-total"><dt>Total</dt><dd><?= e(fmt_money((int) $pricing['total_cents'])) ?></dd></div>
          <?php if ((int) $pricing['gift_card_applied_cents'] > 0): ?>
            <div class="sum-row"><dt>Gift card</dt><dd>−<?= e(fmt_money((int) $pricing['gift_card_applied_cents'])) ?></dd></div>
            <div class="sum-total"><dt>Due now</dt><dd><?= e(fmt_money((int) $pricing['amount_due_cents'])) ?></dd></div>
          <?php endif; ?>
        </dl>

        <div class="checkout-cta">
          <a class="btn btn--solid btn--lg btn--block" href="<?= e(url('/checkout')) ?>">Checkout</a>
        </div>
        <p class="reassure">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/></svg>
          Secure checkout · cards &amp; Apple Pay
        </p>
      </div>
    </aside>

  </div>
  <?php endif; ?>
</div>
