<?php
/**
 * Order confirmation + live status (screen 8). Renders from the pending->paid
 * row so it exists even if the webhook lags. When $payment is set, mounts the
 * Stripe Payment Element to confirm the card charge client-side. Tokenized
 * guest cancel is offered while paid (and not yet preparing).
 *
 * @var array<string,mixed> $order
 * @var list<array<string,mixed>> $items
 * @var list<array<string,mixed>> $taxLines
 * @var array<string,mixed>|null $slot
 * @var string|null $step          received|preparing|ready
 * @var array<string,mixed>|null $payment
 * @var bool $canCancel
 * @var array{type:string,message:string}|null $flash
 */
$status   = (string) $order['status'];
$paid     = in_array($status, ['paid', 'preparing', 'ready', 'fulfilled'], true);
$stepRank = ['received' => 0, 'preparing' => 1, 'ready' => 2];
$current  = $stepRank[$step ?? ''] ?? 0;
$firstName = trim((string) ($order['contact_name'] ?? ''));
$firstName = $firstName !== '' ? explode(' ', $firstName)[0] : '';
?>
<main class="confirm">
  <?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'error' ? 'alert--danger' : 'alert--ok' ?>" role="status"><?= e($flash['message']) ?></div>
  <?php endif; ?>

  <?php if ($payment !== null): ?>
    <!-- Awaiting card confirmation: mount the Stripe Payment Element. -->
    <span class="badge badge--warn confirm__badge">Confirm payment</span>
    <h1 class="disp">Almost there</h1>
    <p class="confirm__sub">Your order #<?= e((string) $order['id']) ?> is reserved. Confirm payment to lock it in, your spot is held for 15 minutes.</p>

    <section class="pay-panel" aria-label="Payment">
      <div id="payment-element" data-stripe-pk="<?= e((string) $payment['publishable']) ?>" data-client-secret="<?= e((string) $payment['client_secret']) ?>" data-return-url="<?= e((string) $payment['return_url']) ?>"></div>
      <div id="payment-message" class="pay-message" role="status" hidden></div>
      <button id="pay-button" class="btn btn--solid btn--lg btn--block">Pay <?= e(fmt_money((int) $payment['amount_due'])) ?></button>
      <p class="summary__terms">Card processed securely by Stripe · Apple Pay / Google Pay supported.</p>
    </section>
  <?php else: ?>
    <div class="confirm__seal" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
    </div>
    <span class="badge <?= $paid ? 'badge--ok' : 'badge--warn' ?> confirm__badge"><?= $paid ? 'Order received' : 'Awaiting payment' ?></span>
    <h1 class="disp"><?= $paid ? 'Order confirmed' : 'Order received' ?></h1>
    <p class="confirm__sub">
      <?php if ($paid): ?>
        Thank you<?= $firstName !== '' ? ', ' . e($firstName) : '' ?>, we're warming up the kitchen. We'll have everything fresh and ready for you at the counter.
      <?php else: ?>
        We've reserved your order. If payment hasn't completed, your card was not charged and the reservation will release automatically.
      <?php endif; ?>
    </p>

    <div class="confirm__meta">
      <div class="meta-pill"><span class="k">Order</span><span class="v">#<?= e((string) $order['id']) ?></span></div>
      <?php if ($slot !== null): ?>
        <div class="meta-pill"><span class="k">Pickup</span><span class="v gold"><?= e(fmt_date((string) $slot['slot_start'], 'g:i A')) ?></span></div>
      <?php else: ?>
        <div class="meta-pill"><span class="k">Pickup</span><span class="v gold">ASAP</span></div>
      <?php endif; ?>
      <div class="meta-pill"><span class="k">Pickup at</span><span class="v">The counter</span></div>
    </div>

    <?php if ($paid): ?>
      <!-- STATUS STEPPER (live-polled) -->
      <div class="stepper" role="list" aria-label="Order status" data-order-status data-token="<?= e((string) $order['public_token']) ?>" data-poll-url="<?= e(url('/order/' . $order['public_token']) . '?format=json') ?>">
        <div class="stepper__line" aria-hidden="true"><span class="stepper__fill stepper__fill--<?= e((string) min(2, max(0, $current))) ?>"></span></div>
        <?php
          $steps = [['received', 'Received'], ['preparing', 'Preparing'], ['ready', 'Ready']];
          $stepIcons = [
            'received'  => '<path d="M20 6 9 17l-5-5"/>',
            'preparing' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'ready'     => '<path d="M5 12h14M12 5l7 7-7 7"/>',
          ];
          foreach ($steps as $i => [$key, $label]):
            $cls = $i < $current ? 'step--done' : ($i === $current ? 'step--active' : '');
            $stepTime = '';
            if ($key === 'preparing' && $i === $current) {
                $stepTime = 'In the kitchen now';
            } elseif ($key === 'ready' && $slot !== null) {
                $stepTime = '~ ' . fmt_date((string) $slot['slot_start'], 'g:i A');
            }
        ?>
          <div class="step <?= $cls ?>" role="listitem"<?= $i === $current ? ' aria-current="step"' : '' ?> data-step="<?= e($key) ?>">
            <div class="step__dot<?= $i === $current ? ' step__dot--pulse' : '' ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $stepIcons[$key] ?></svg>
            </div>
            <div class="step__label"><?= e($label) ?></div>
            <?php if ($stepTime !== ''): ?>
              <div class="step__time"><?= e($stepTime) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- RECEIPT -->
    <section class="receipt" aria-label="Order receipt">
      <div class="receipt__head">
        <h2 class="disp">Your order</h2>
        <?php if ($slot !== null): ?>
          <span class="when">Pickup · <?= e(fmt_date((string) $slot['slot_start'], 'M j, g:i A')) ?></span>
        <?php endif; ?>
      </div>
      <div class="receipt__body">
        <table class="tbl">
          <thead><tr><th scope="col">Item</th><th scope="col" class="amt">Amount</th></tr></thead>
          <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td>
                  <span class="qty"><?= e((string) $it['quantity']) ?>×</span>
                  <span class="line-name"><?= e((string) $it['name_snapshot']) ?></span>
                  <?php foreach ($it['modifiers'] as $mod): ?>
                    <span class="line-note"><?= e((string) $mod['name_snapshot']) ?></span>
                  <?php endforeach; ?>
                </td>
                <td class="amt"><?= e(fmt_money((int) $it['line_total_cents'], false)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="totals">
        <div class="totals__row"><span>Subtotal</span><span><?= e(fmt_money((int) $order['subtotal_cents'], false)) ?></span></div>
        <?php if ((int) $order['discount_cents'] > 0): ?>
          <div class="totals__row"><span>Discount</span><span>−<?= e(fmt_money((int) $order['discount_cents'], false)) ?></span></div>
        <?php endif; ?>
        <?php foreach ($taxLines as $tl): ?>
          <div class="totals__row"><span>Sales tax, <?= e(ucwords(str_replace('_', ' ', (string) $tl['tax_category']))) ?> (<?= e(number_format((int) $tl['rate_bps'] / 100, 2)) ?>%)</span><span><?= e(fmt_money((int) $tl['tax_cents'], false)) ?></span></div>
        <?php endforeach; ?>
        <?php if ((int) $order['tip_cents'] > 0): ?>
          <div class="totals__row"><span>Kitchen tip</span><span><?= e(fmt_money((int) $order['tip_cents'], false)) ?></span></div>
        <?php endif; ?>
        <?php if ((int) $order['giftcard_applied_cents'] > 0): ?>
          <div class="totals__row"><span>Gift card</span><span>−<?= e(fmt_money((int) $order['giftcard_applied_cents'], false)) ?></span></div>
        <?php endif; ?>
        <div class="totals__row grand"><span><?= $paid ? 'Total paid' : 'Total' ?></span><span><?= e(fmt_money((int) $order['total_cents'])) ?></span></div>
      </div>
    </section>

    <!-- ACTIONS -->
    <?php
      // Add-to-calendar: no ICS endpoint exists yet, so fall back gracefully to a
      // Google Calendar event link built from the pickup time (or now + 20 min).
      $calStart = $slot !== null
          ? strtotime((string) $slot['slot_start'])
          : time() + 20 * 60;
      $calStart = $calStart !== false ? $calStart : time() + 20 * 60;
      $calDates = gmdate('Ymd\THis\Z', $calStart) . '/' . gmdate('Ymd\THis\Z', $calStart + 30 * 60);
      $calUrl   = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
          . '&text=' . rawurlencode("Amelia's by EAT, order #" . (string) $order['id'] . ' pickup')
          . '&dates=' . rawurlencode($calDates)
          . '&location=' . rawurlencode('8240 N Hayden Rd, Ste B-105, Scottsdale, AZ 85258')
          . '&details=' . rawurlencode("Pick up your order at the counter.");
    ?>
    <div class="actions">
      <a class="btn btn--solid" href="<?= e($calUrl) ?>" target="_blank" rel="noopener">Add to calendar</a>
      <a class="btn" href="https://maps.google.com/?q=8240+N+Hayden+Rd+B-105+Scottsdale+AZ+85258" target="_blank" rel="noopener">Get directions</a>
      <a class="btn" href="tel:6024995195">Call the restaurant</a>
      <?php if ($canCancel): ?>
        <form method="post" action="<?= e(url('/order/' . $order['public_token'] . '/cancel')) ?>" onsubmit="return confirm('Cancel this order and refund your payment?');">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn--ghost">Cancel order</button>
        </form>
      <?php endif; ?>
    </div>

    <p class="help">
      <span class="serif-it">Something not right?</span>
      Give us a ring at <a href="tel:6024995195">(602) 499-5195</a> or email
      <a href="mailto:info@ameliasaz.com">info@ameliasaz.com</a> and we'll sort it out.
      A confirmation is on its way to your inbox.
    </p>
  <?php endif; ?>
</main>

<?php if ($payment !== null): ?>
<script src="https://js.stripe.com/v3/"></script>
<script src="<?= e(asset('js/checkout.js')) ?>" defer></script>
<?php endif; ?>
