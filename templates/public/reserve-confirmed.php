<?php
/** Reservation confirmation / waitlist acknowledgement (screen 12 success). */
$waitlist = $waitlist ?? false;
?>
<header class="page-head">
  <div class="container">
    <p class="kicker">Thank you</p>
    <?php if ($waitlist): ?>
      <h1 class="disp">You're on the waitlist</h1>
      <p>We'll text you the moment a table opens up, confirm with one tap and we'll have a seat waiting.</p>
    <?php else: ?>
      <h1 class="disp">Reservation confirmed</h1>
      <p>We've sent a confirmation to your email with a secure link to modify or cancel.</p>
    <?php endif; ?>
  </div>
</header>

<?php if (!$waitlist && !empty($booking)): ?>
<section class="section">
  <div class="container reserve-confirm">
    <div class="card">
      <h3 class="disp">Your table</h3>
      <dl class="reserve-confirm__dl">
        <dt>When</dt><dd><?= e($when ?? '') ?></dd>
        <dt>Party</dt><dd><?= (int) $booking['party_size'] ?> guests</dd>
        <dt>Name</dt><dd><?= e((string) $booking['contact_name']) ?></dd>
      </dl>
      <p class="btn-row mt-6">
        <a class="btn btn--solid" href="<?= e($manageUrl ?? url('/')) ?>">Manage reservation</a>
        <a class="btn" href="<?= e(url('/menu')) ?>">See the menu</a>
      </p>
    </div>
  </div>
</section>
<?php endif; ?>
