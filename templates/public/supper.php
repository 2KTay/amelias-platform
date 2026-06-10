<?php
/**
 * Sunday Supper (screen 15), ported from mockups/supper.html.
 *
 * Upcoming suppers (booking_slots with an event_product_id) with seats-left.
 * Each open event has a short prepay form posting to /sunday-supper/{id}/buy,
 * which claims a seat atomically and starts a Stripe one-time payment. When a
 * PaymentIntent is pending ($payment), the Stripe Payment Element is mounted to
 * confirm the charge.
 *
 * @var list<array<string,mixed>> $events
 * @var array<string,mixed>|null  $payment
 * @var array|null $flash
 */
$events  = $events ?? [];
$payment = $payment ?? null;
$flash   = $flash ?? null;
?>
<section class="page-hero">
  <img class="supper-hero__img" src="<?= e(asset('img/glasses-clinking.jpg')) ?>" alt="Hands raising glasses together in a warm, low-lit toast">
  <div class="page-hero__in">
    <span class="kicker kicker--gold">One Sunday a month</span>
    <h1 class="disp">Sunday Supper</h1>
    <p>One long table, one seasonal menu, all-Arizona sourcing. A multi-course communal dinner where strangers become neighbors over shared plates and good wine.</p>
  </div>
</section>

<?php if ($payment !== null): ?>
  <section class="section"><div class="container container--narrow">
    <div class="card supper-pay">
      <h2 class="disp">Confirm your seat</h2>
      <p class="muted">Pay <?= e(fmt_money((int) $payment['amount'])) ?> to claim your seat for <strong><?= e((string) $payment['event']) ?></strong> · <?= e((string) $payment['when']) ?>.</p>
      <form id="payment-form"
            data-stripe-pk="<?= e((string) $payment['publishable']) ?>"
            data-client-secret="<?= e((string) $payment['client_secret']) ?>"
            data-return-url="<?= e((string) $payment['return_url']) ?>">
        <div id="payment-element"></div>
        <div class="form-actions">
          <button class="btn btn--solid btn--lg" type="submit" id="pay-button">Pay <?= e(fmt_money((int) $payment['amount'])) ?></button>
        </div>
        <p class="field__hint" id="payment-message" role="status" aria-live="polite"></p>
      </form>
    </div>
  </div></section>
  <script src="https://js.stripe.com/v3/"></script>
  <script src="<?= e(asset('js/checkout.js')) ?>" defer></script>
<?php endif; ?>

<section class="section"><div class="container">
  <?php if ($flash !== null): ?>
    <div class="alert <?= ($flash['type'] ?? '') === 'error' ? 'alert--danger' : 'alert--ok' ?>" role="status"><?= e((string) ($flash['message'] ?? '')) ?></div>
  <?php endif; ?>

  <div class="head supper-idea">
    <h2 class="disp">A meal the way it's meant to be</h2>
    <p>Once a month we clear the room, set one long communal table, and cook a single menu for everyone who pulls up a chair. The courses change with the season and a little with the chef's mood, you'll know roughly what you're in for, and you'll be surprised in the best way. It's Stacey's favorite night of the month, and after one you'll see why.</p>
  </div>
  <div class="supper-how">
    <div class="supper-how__cell">
      <span class="supper-how__lead">One table</span>
      <h3 class="disp">Communal seating</h3>
      <p>Everyone shares the same long table and the same multi-course menu. Come as a pair or a party of six, you'll leave having met the rest.</p>
    </div>
    <div class="supper-how__cell">
      <span class="supper-how__lead">All Arizona</span>
      <h3 class="disp">Sourced close to home</h3>
      <p>Each menu is built around our Arizona purveyors, Noble Bread, Steadfast Farm, Crow's Dairy, Fra'Mani, Sweet Republic. Scratch made, seed-oil free, served with heart.</p>
    </div>
    <div class="supper-how__cell">
      <span class="supper-how__lead">Surprise menu</span>
      <h3 class="disp">Seasonal &amp; a secret</h3>
      <p>We share the theme and wine pairing ahead of time and keep a few courses under wraps. Tell us about allergies when you reserve and we'll take care of you.</p>
    </div>
  </div>
</div></section>

<section class="section section--soft" id="upcoming"><div class="container">
  <div class="head head--row">
    <div>
      <h2 class="disp">Upcoming suppers</h2>
      <p class="supper-upcoming__lede">Seats are limited to one table and are claimed at checkout, prepay to hold your place. Once a date sells out, it's full.</p>
    </div>
    <a class="link-arrow" href="<?= e(url('/reserve')) ?>">Reserve a table instead <svg width="20" height="12" viewBox="0 0 20 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 6h17M13 1l5 5-5 5"/></svg></a>
  </div>

  <?php if (empty($events)): ?>
    <div class="alert" role="status">No suppers are on the calendar right now, check back soon, or <a href="<?= e(url('/reserve')) ?>">reserve a regular table</a> in the meantime.</div>
  <?php else: ?>
    <?php foreach ($events as $ev): ?>
      <?php
        $start = $ev['slot_start'];
        $mo = fmt_date($start, 'M');
        $day = fmt_date($start, 'j');
        $remaining = (int) $ev['remaining'];
        $soldOut = (bool) $ev['sold_out'];
      ?>
      <article class="event<?= $soldOut ? ' event--full' : '' ?>">
        <div class="event__date"><span class="mo"><?= e($mo) ?></span><span class="day"><?= e($day) ?></span></div>
        <div class="event__main">
          <h3 class="disp"><?= e((string) $ev['name']) ?></h3>
          <p class="event__meta"><?= e(fmt_date($start, 'l, g:i A')) ?><?= $ev['description'] !== '' ? ' · ' . e((string) $ev['description']) : '' ?></p>
        </div>
        <div class="event__aside">
          <span class="event__price"><?= e(fmt_money((int) $ev['price_cents'])) ?> / seat</span>
          <?php if ($soldOut): ?>
            <span class="badge">Sold out</span>
          <?php elseif ($remaining <= 6): ?>
            <span class="badge badge--warn"><?= $remaining ?> seat<?= $remaining === 1 ? '' : 's' ?> left</span>
          <?php else: ?>
            <span class="badge badge--ok"><?= $remaining ?> seats left</span>
          <?php endif; ?>

          <?php if (!$soldOut): ?>
            <details class="supper-buy">
              <summary class="btn btn--solid">Reserve a seat</summary>
              <form class="supper-buy__form" method="post" action="<?= e(url('/sunday-supper/' . (int) $ev['slot_id'] . '/buy')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                  <label for="name-<?= (int) $ev['slot_id'] ?>">Your name <span class="req">*</span></label>
                  <input class="input" id="name-<?= (int) $ev['slot_id'] ?>" name="name" type="text" autocomplete="name" required>
                </div>
                <div class="field">
                  <label for="email-<?= (int) $ev['slot_id'] ?>">Email <span class="req">*</span></label>
                  <input class="input" id="email-<?= (int) $ev['slot_id'] ?>" name="email" type="email" autocomplete="email" required>
                </div>
                <div class="field">
                  <label for="phone-<?= (int) $ev['slot_id'] ?>">Phone</label>
                  <input class="input" id="phone-<?= (int) $ev['slot_id'] ?>" name="phone" type="tel" autocomplete="tel">
                </div>
                <div class="field">
                  <label for="notes-<?= (int) $ev['slot_id'] ?>">Allergies or notes</label>
                  <input class="input" id="notes-<?= (int) $ev['slot_id'] ?>" name="notes" type="text" maxlength="200">
                </div>
                <button class="btn btn--solid btn--block" type="submit">Prepay <?= e(fmt_money((int) $ev['price_cents'])) ?> &amp; claim seat</button>
              </form>
            </details>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</div></section>

<section class="split">
  <div class="split__media"><img src="<?= e(asset('img/5I8A7492-1024x1536.jpeg')) ?>" alt="Amelia's dining room, slatted-wood wall, woven-leather chairs, warm globe sconces"></div>
  <div class="split__body">
    <span class="kicker">Good to know</span>
    <h2 class="disp supper-detail__h">How the evening runs</h2>
    <p>Doors at 6:00, first course at 6:30. Plan for about two and a half hours at the table. Seats are claimed at checkout and capacity is limited to one room, so reserve early, we can't add chairs once a supper is full.</p>
    <p class="supper-detail__note">Prepayment holds your seat. Need to cancel? Let us know at least 72 hours ahead and we'll move you to a future supper. If we ever have to cancel a supper, every ticket is refunded in full.</p>
    <p class="btn-row mt-6">
      <a class="btn btn--solid" href="<?= e(url('/reserve')) ?>">Reserve a seat</a>
      <a class="btn" href="tel:6024995195">(602) 499-5195</a>
    </p>
  </div>
</section>

<!-- CLOSING CTA -->
<section class="section--ink">
  <div class="container supper-cta">
    <h2 class="disp supper-cta__h">Pull up a chair this Sunday</h2>
    <p class="supper-cta__p">One table, one menu, the whole neighborhood. Seats are limited and prepaid, claim yours before the date fills.</p>
    <a class="btn btn--lg btn--ghost-light" href="<?= e(url('/reserve')) ?>">Reserve a seat</a>
  </div>
</section>
