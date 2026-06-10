<?php
/**
 * Deposit step (screen 12, large party / event). The slot is held; the table
 * is confirmed once the Stripe deposit succeeds. Stripe.js mounts the Payment
 * Element when a client secret is available; otherwise we show a graceful
 * "deposit not yet wired" notice (keys not configured locally).
 */
$depositCents  = (int) ($depositCents ?? 0);
$party         = (int) ($party ?? 1);
$clientSecret  = $clientSecret ?? null;
$publishableKey = (string) ($publishableKey ?? '');
$bookingToken  = (string) ($bookingToken ?? '');
$stripeReady   = (bool) ($stripeReady ?? false);
?>
<header class="page-head">
  <div class="container">
    <p class="kicker">Almost there</p>
    <h1 class="disp">Secure your table</h1>
    <p>Parties of this size are held with a small per-guest deposit, credited to your final check. Your table is held while you complete payment.</p>
  </div>
</header>

<section class="section">
  <div class="container reserve-deposit">
    <div class="card">
      <dl class="reserve-confirm__dl">
        <dt>Party</dt><dd><?= $party ?> guests</dd>
        <dt>Deposit</dt><dd><?= e(fmt_money($depositCents)) ?> <span class="muted">(credited to your check)</span></dd>
      </dl>

      <?php if ($stripeReady): ?>
        <form id="deposit-form"
              data-stripe-pk="<?= e($publishableKey) ?>"
              data-client-secret="<?= e((string) $clientSecret) ?>"
              data-return-url="<?= e(rtrim((string) config('url'), '/') . url('/reservation/' . $bookingToken)) ?>">
          <div id="payment-element"></div>
          <div class="form-actions">
            <button class="btn btn--solid btn--lg" type="submit" id="deposit-submit">Pay <?= e(fmt_money($depositCents)) ?> deposit</button>
          </div>
          <p class="field__hint" id="deposit-msg" role="status" aria-live="polite"></p>
        </form>
        <script src="https://js.stripe.com/v3/"></script>
        <script src="<?= e(asset('js/reservation-deposit.js')) ?>" defer></script>
      <?php else: ?>
        <div class="alert" role="status">
          Your table is held. Online deposit payment isn't enabled on this environment yet, our team will follow up to confirm.
        </div>
        <p class="btn-row mt-6">
          <a class="btn" href="<?= e(url('/reservation/' . $bookingToken)) ?>">View reservation</a>
        </p>
      <?php endif; ?>
    </div>
  </div>
</section>
