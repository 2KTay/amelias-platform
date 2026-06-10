<?php
/**
 * Reserve a table (screen 12). Party + date + real-time slot chips, guest
 * details, deposit/waitlist notes. Full slots render disabled. Slots refresh
 * via /reserve/availability as the date/party change (progressive enhancement).
 */
$slots   = $slots ?? [];
$date    = $date ?? '';
$party   = (int) ($party ?? 2);
$minDate = $minDate ?? $date;
$depositThreshold = (int) ($depositThreshold ?? 8);
?>
<header class="page-head">
  <div class="container">
    <h1 class="disp">Reserve a table</h1>
    <p>Evening table service in our McCormick Ranch dining room. Book a few minutes ahead and we'll have a seat waiting. Counter service runs all day, no reservation needed.</p>
  </div>
</header>

<section class="section">
  <div class="container reserve">
    <form class="book" method="post" action="<?= e(url('/reserve')) ?>" data-reserve-form novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reserve">
      <input type="hidden" name="slot_id" value="" data-slot-input>

      <h2 class="book__legend">Book your table</h2>

      <?php if (!empty($error)): ?>
        <div class="alert alert--danger" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <p class="book__step">Party, date &amp; time</p>
      <div class="grid2">
        <div class="field">
          <label for="party">Party size <span class="req">*</span></label>
          <select class="select" id="party" name="party" data-reserve-party required>
            <?php for ($n = 1; $n <= 12; $n++): ?>
              <option value="<?= $n ?>"<?= $n === $party ? ' selected' : '' ?>>
                <?= $n ?> <?= $n === 1 ? 'guest' : 'guests' ?><?= $n >= $depositThreshold ? ' (deposit)' : '' ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="field">
          <label for="date">Date <span class="req">*</span></label>
          <input class="input" type="date" id="date" name="date" value="<?= e($date) ?>" min="<?= e($minDate) ?>" data-reserve-date required>
        </div>
      </div>

      <div class="field">
        <label>Time <span class="req">*</span></label>
        <div class="slots" role="group" aria-label="Available reservation times" data-slots>
          <?php if (empty($slots)): ?>
            <p class="field__hint" data-slots-empty>No times available for this date. Try another date or join the waitlist below.</p>
          <?php else: ?>
            <?php foreach ($slots as $slot): ?>
              <?php $full = $slot['remaining'] <= 0; ?>
              <button type="button" class="slot" data-slot-id="<?= (int) $slot['id'] ?>" aria-pressed="false"<?= $full ? ' disabled aria-disabled="true"' : '' ?>>
                <?= e(fmt_date($slot['slot_start'], 'g:ia')) ?><?php if ($full): ?><small>Full</small><?php endif; ?>
              </button>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <p class="field__hint">Booked out for your time? Add yourself to the waitlist below and we'll text if a table opens.</p>
      </div>

      <hr class="rule reserve__rule">

      <p class="book__step">Guest details</p>
      <div class="field">
        <label for="name">Name <span class="req">*</span></label>
        <input class="input" type="text" id="name" name="name" autocomplete="name" placeholder="First and last name" required>
      </div>
      <div class="grid2">
        <div class="field">
          <label for="email">Email <span class="req">*</span></label>
          <input class="input" type="email" id="email" name="email" autocomplete="email" placeholder="you@example.com" required>
          <p class="field__hint">We'll send your confirmation here.</p>
        </div>
        <div class="field">
          <label for="phone">Mobile</label>
          <input class="input" type="tel" id="phone" name="phone" autocomplete="tel" placeholder="(602) 555-0142">
          <p class="field__hint">For day-of texts and waitlist alerts.</p>
        </div>
      </div>
      <div class="field">
        <label for="requests">Special requests</label>
        <textarea class="textarea" id="requests" name="requests" rows="3" placeholder="Allergies, a high chair, a birthday. Tell us how to take care of you."></textarea>
        <p class="field__hint">Optional.</p>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn--solid btn--lg">Confirm reservation</button>
        <button type="submit" class="btn" name="action" value="waitlist" formnovalidate>Join waitlist instead</button>
      </div>

      <p class="fineprint">Parties of <?= (int) $depositThreshold ?>+ and ticketed events are held with a small per-guest deposit via Stripe, credited to your final check. Every confirmation email includes a secure link to modify or cancel.</p>
    </form>

    <aside class="aside" aria-label="Reservation details">
      <section class="aside__block">
        <h2 class="aside__title">Good to know</h2>
        <ul class="notes">
          <li class="note">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 8v5l3 2"/><circle cx="12" cy="12" r="9"/></svg>
            <span>We hold tables for 15 minutes past your time. Running late? Reply to your confirmation text.</span>
          </li>
          <li class="note">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 7h18M3 12h18M3 17h12"/></svg>
            <span>Counter service is first-come during the day. Reservations are for evening table service.</span>
          </li>
          <li class="note">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 21s-7-4.35-7-10a7 7 0 0 1 14 0c0 5.65-7 10-7 10z"/><circle cx="12" cy="11" r="2.5"/></svg>
            <span>8240 N Hayden Rd, Ste B-105, Scottsdale. Free lot parking out front.</span>
          </li>
        </ul>
      </section>

      <section class="aside__block">
        <h2 class="aside__title">Deposits &amp; waitlist</h2>
        <p class="aside__note"><span class="aside__lead">Deposits.</span> Large parties (9+), private events, and Sunday Supper seats are held with a small per-guest deposit, taken securely via Stripe and credited to your final check.</p>
        <p class="aside__note"><span class="aside__lead">Waitlist.</span> If your time is full, join the waitlist and we'll text the moment a table frees up. Confirm with one tap.</p>
      </section>

      <section class="aside__block">
        <h2 class="aside__title">Hours</h2>
        <dl class="reserve__hours">
          <dt>Sun</dt><dd>7a&ndash;3p</dd>
          <dt>Mon&ndash;Thu</dt><dd>7a&ndash;8p</dd>
          <dt>Fri&ndash;Sat</dt><dd>7a&ndash;close</dd>
        </dl>
        <p class="reserve__contact"><a class="ulink" href="tel:6024995195">(602) 499-5195</a> · <a class="ulink" href="mailto:info@ameliasaz.com">info@ameliasaz.com</a></p>
      </section>
    </aside>
  </div>
</section>
<script src="<?= e(asset('js/reserve.js')) ?>" defer></script>
