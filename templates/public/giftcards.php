<?php
/**
 * Gift cards (screen 10). Purchase (emailed code, tax-exempt) + balance check.
 * When $payment is set, mounts the Stripe Payment Element to confirm the charge.
 *
 * @var list<int> $presets       preset amounts in cents
 * @var int $minCents
 * @var int $maxCents
 * @var bool $stripeReady
 * @var string $publishableKey
 * @var array{ok:bool,message:string}|null $balanceResult
 * @var array<string,mixed>|null $payment
 * @var array{type:string,message:string}|null $flash
 */
?>
<header class="page-head">
  <div class="container">
    <span class="kicker">Give a little good</span>
    <h1 class="disp">Gift Cards</h1>
    <p>A gift card to Amelia's is breakfast with friends, a bottle off the wine wall, or granola for the pantry, whatever the day calls for. Delivered by email, redeemable in-store and online.</p>
  </div>
</header>

<section class="section">
  <div class="container">
    <?php if (!empty($flash)): ?>
      <div class="alert <?= $flash['type'] === 'error' ? 'alert--danger' : 'alert--ok' ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <?php if ($payment !== null): ?>
      <!-- Confirm the gift-card charge. -->
      <div class="gc-pay">
        <h2 class="disp">Confirm payment</h2>
        <p class="muted">Pay <?= e(fmt_money((int) $payment['amount'])) ?> to send your gift card.</p>
        <div id="payment-element" data-stripe-pk="<?= e((string) $payment['publishable']) ?>" data-client-secret="<?= e((string) $payment['client_secret']) ?>" data-return-url="<?= e((string) $payment['return_url']) ?>"></div>
        <div id="payment-message" class="pay-message" role="status" hidden></div>
        <button id="pay-button" class="btn btn--solid btn--lg btn--block">Pay <?= e(fmt_money((int) $payment['amount'])) ?></button>
      </div>
      <script src="https://js.stripe.com/v3/"></script>
      <script src="<?= e(asset('js/checkout.js')) ?>" defer></script>
    <?php else: ?>

    <div class="gift">
      <!-- LEFT: purchase form -->
      <div>
        <form class="gc-form" method="post" action="<?= e(url('/gift-cards')) ?>" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="purchase">

          <fieldset class="fieldset">
            <legend class="fieldset__legend">Choose an amount</legend>
            <div class="chips" role="group" aria-label="Gift card amount">
              <?php foreach ($presets as $i => $cents): ?>
                <label class="chip"><input type="radio" name="amount" value="<?= e((string) $cents) ?>"<?= $i === 1 ? ' checked' : '' ?>><span><?= e(fmt_money($cents)) ?></span></label>
              <?php endforeach; ?>
              <label class="chip"><input type="radio" name="amount" value="custom"><span>Custom</span></label>
            </div>
            <div class="custom-amt">
              <label class="visually-hidden" for="custom-amount">Custom amount in dollars</label>
              <div class="custom-wrap">
                <input class="input" id="custom-amount" name="custom_amount" type="number" min="<?= e((string) ((int) ($minCents / 100))) ?>" max="<?= e((string) ((int) ($maxCents / 100))) ?>" step="5" inputmode="numeric" placeholder="Other amount">
              </div>
              <p class="field__hint">Any amount from <?= e(fmt_money($minCents)) ?> to <?= e(fmt_money($maxCents)) ?>.</p>
            </div>
          </fieldset>

          <fieldset class="fieldset">
            <legend class="fieldset__legend">Who's it for?</legend>
            <div class="field-row">
              <div class="field">
                <label for="r-name">Recipient name</label>
                <input class="input" id="r-name" name="recipient_name" type="text" autocomplete="name">
              </div>
              <div class="field">
                <label for="r-email">Recipient email <span class="req">*</span></label>
                <input class="input" id="r-email" name="recipient_email" type="email" autocomplete="email" required>
              </div>
            </div>
            <div class="field">
              <label for="g-message">Gift message</label>
              <textarea class="textarea" id="g-message" name="gift_message" rows="3" maxlength="250" placeholder="Happy birthday, lunch is on me. See you at Amelia's."></textarea>
              <p class="field__hint">Printed in the email we send to your recipient. Up to 250 characters.</p>
            </div>
            <div class="field gc-senddate">
              <label for="send-date">Send date</label>
              <input class="input" id="send-date" name="send_date" type="date">
              <p class="field__hint">Leave blank to send right away.</p>
            </div>
          </fieldset>

          <fieldset class="fieldset">
            <legend class="fieldset__legend">Your details</legend>
            <div class="field-row">
              <div class="field">
                <label for="p-name">Your name</label>
                <input class="input" id="p-name" name="purchaser_name" type="text" autocomplete="name">
              </div>
              <div class="field">
                <label for="p-email">Your email <span class="req">*</span></label>
                <input class="input" id="p-email" name="purchaser_email" type="email" autocomplete="email" required>
                <p class="field__hint">We'll send your receipt here.</p>
              </div>
            </div>
          </fieldset>

          <?php $defaultCents = $presets[1] ?? ($presets[0] ?? 0); ?>
          <div class="gc-summary">
            <span class="gc-summary__label">Card total · no tax</span>
            <span class="gc-summary__amt" id="gc-summary-amt"><?= e(fmt_money((int) $defaultCents)) ?></span>
          </div>
          <div class="form-actions">
            <button class="btn btn--solid btn--lg" type="submit"<?= $stripeReady ? '' : ' disabled' ?>>Buy gift card</button>
          </div>
          <?php if (!$stripeReady): ?>
            <p class="field__hint">Online payment is being finalized, check back soon.</p>
          <?php endif; ?>
        </form>

        <!-- Balance check -->
        <form class="gc-balance" method="post" action="<?= e(url('/gift-cards')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="balance">
          <label class="fieldset__legend" for="gc-code">Check a balance</label>
          <div class="promo">
            <input class="input" id="gc-code" name="code" type="text" placeholder="Card code" autocomplete="off">
            <button class="btn" type="submit">Check</button>
          </div>
          <?php if ($balanceResult !== null): ?>
            <p class="<?= $balanceResult['ok'] ? 'gc-balance__ok' : 'gc-balance__err' ?>"><?= e((string) $balanceResult['message']) ?></p>
          <?php endif; ?>
        </form>
      </div>

      <!-- RIGHT: image + reassurance -->
      <aside class="gift__aside">
        <div class="gift__card">
          <img src="<?= e(asset('img/banner-alternate-1536x1024.jpg')) ?>" alt="Bright, sunlit spread of Amelia's food on cream linen">
          <span class="gift__cardcap">When you eat well, you feel good.</span>
        </div>
        <div class="gift__notes">
          <div class="gift__note">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 8l9 5 9-5"/></svg>
            <span>Emailed straight to your recipient on the date you choose.</span>
          </div>
          <div class="gift__note">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 13l4 4 10-10"/></svg>
            <span>Redeemable in-store and online, for the kitchen, the market, and the wine wall.</span>
          </div>
          <div class="gift__note">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            <span>No tax charged at purchase, and the card never expires.</span>
          </div>
        </div>
      </aside>
    </div>

    <script>
    // Live gift-card total: reflect the selected preset (cents) or the custom
    // dollar amount into the summary. Display only; server re-validates on submit.
    (function () {
      var form = document.querySelector('.gc-form');
      if (!form) return;
      var out = document.getElementById('gc-summary-amt');
      var custom = form.querySelector('#custom-amount');
      if (!out) return;
      function money(cents) {
        return '$' + (Math.max(0, cents) / 100).toFixed(2);
      }
      function update() {
        var picked = form.querySelector('input[name="amount"]:checked');
        if (picked && picked.value === 'custom') {
          var dollars = parseFloat(custom && custom.value ? custom.value : '0');
          out.textContent = money(isNaN(dollars) ? 0 : Math.round(dollars * 100));
        } else if (picked) {
          var cents = parseInt(picked.value, 10);
          out.textContent = money(isNaN(cents) ? 0 : cents);
        }
      }
      form.querySelectorAll('input[name="amount"]').forEach(function (r) {
        r.addEventListener('change', update);
      });
      if (custom) {
        custom.addEventListener('input', function () {
          var customRadio = form.querySelector('input[name="amount"][value="custom"]');
          if (customRadio) customRadio.checked = true;
          update();
        });
      }
      update();
    })();
    </script>

    <?php endif; ?>
  </div>
</section>
