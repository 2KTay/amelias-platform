<?php
/** Public footer, faithful to mockups/home-v3.html (brand · Visit · Hours · More). */
$year = gmdate('Y');
?>
<footer class="foot">
  <div class="container">
    <div class="foot__grid">
      <div>
        <div class="foot__brand"><img src="<?= e(asset('brand/AmeliasbyEatLogo-Final-Color.svg')) ?>" alt="<?= e(config('name')) ?>"></div>
        <p class="muted mt-4">A conscious, made-from-scratch, all-day eating place &amp; market in Scottsdale.</p>
      </div>
      <div>
        <h4>Visit</h4>
        <a href="<?= e(url('/location')) ?>">8240 N Hayden Rd, B-105<br>Scottsdale, AZ 85258</a>
        <a href="tel:6024995195">(602) 499-5195</a>
        <a href="mailto:info@ameliasaz.com">info@ameliasaz.com</a>
      </div>
      <div>
        <h4>Hours</h4>
        <a>Sun &nbsp;7a–3p</a>
        <a>Mon–Thu &nbsp;7a–8p</a>
        <a>Fri–Sat &nbsp;7a–close</a>
      </div>
      <div>
        <h4>More</h4>
        <a href="<?= e(url('/careers')) ?>">Now Hiring</a>
        <a href="<?= e(url('/story')) ?>">Our Values</a>
        <a href="<?= e(url('/purveyors')) ?>">Our Purveyors</a>
        <a href="<?= e(url('/gift-cards')) ?>">Gift Cards</a>
        <a href="https://www.instagram.com/ameliasbyeat/" rel="noopener" target="_blank">Instagram</a>
      </div>
    </div>
    <div class="foot__base">
      <span>&copy; <?= e($year) ?> <?= e(config('name')) ?> · Scottsdale, Arizona</span>
      <span>
        <a href="<?= e(url('/privacy')) ?>">Privacy</a> ·
        <a href="<?= e(url('/terms')) ?>">Terms &amp; Refunds</a> ·
        Eat &amp; drink local.
      </span>
    </div>
  </div>
</footer>
