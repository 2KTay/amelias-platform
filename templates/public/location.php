<?php
/**
 * Location & Contact (screen 3), faithful port of mockups/location.html.
 * Address / hours / directions + a Formspree-backed contact form with a
 * _gotcha honeypot. Restaurant JSON-LD is emitted via the layout ($jsonLd).
 *
 * HUMAN-BLOCKED: the Formspree endpoint id is a placeholder (REPLACE_ME), a
 * human must create the form at formspree.io and paste the real id.
 */
$formspree = $formspreeContact ?? 'https://formspree.io/f/REPLACE_ME';
?>
<!-- PAGE HERO -->
<section class="page-hero">
  <img src="<?= e(asset('img/5I8A7492-1024x1536.jpeg')) ?>" alt="Amelia's dining room, slatted-wood wall, woven-leather chairs, globe sconces and cacti in warm light" fetchpriority="high">
  <div class="page-hero__in">
    <h1 class="disp">Come visit</h1>
    <p>See you in sunny Scottsdale, counter service by day, table service in the evening, and a market to take home.</p>
  </div>
</section>

<!-- VISIT: details + map -->
<section class="section"><div class="container visit">
  <div class="visit__info">
    <h2 class="disp">Find us in McCormick Ranch</h2>
    <p class="visit__see serif-it">Families always welcome.</p>
    <dl>
      <dt>Where</dt>
      <dd>8240 N Hayden Road, Ste B-105<br>Scottsdale, AZ 85258</dd>
      <dt>Hours</dt>
      <dd>Sun&nbsp;7a&ndash;3p<br>Mon&ndash;Thu&nbsp;7a&ndash;8p<br>Fri&ndash;Sat&nbsp;7a&ndash;close</dd>
      <dt>Call</dt>
      <dd><a href="tel:6024995195">(602) 499-5195</a></dd>
      <dt>Email</dt>
      <dd><a href="mailto:info@ameliasaz.com">info@ameliasaz.com</a></dd>
    </dl>
    <p class="visit__note">We're on N Hayden Road at the McCormick Ranch shops. Free surface parking out front, with additional spaces wrapping around the back of the building.</p>
    <div class="visit__actions"><a class="btn btn--solid" href="https://maps.google.com/?q=8240+N+Hayden+Road+B-105+Scottsdale+AZ+85258" rel="noopener" target="_blank">Get directions</a></div>
  </div>
  <div class="map-box" role="img" aria-label="Map showing Amelia's by EAT at 8240 N Hayden Road, Ste B-105, Scottsdale">
    <span class="map-box__pin">8240 N Hayden Rd</span>
    <span class="map-box__label">Map</span>
  </div>
</div></section>

<!-- CONTACT FORM (Formspree) -->
<section class="section section--soft"><div class="container">
  <div class="head">
    <h2 class="disp">Send us a note</h2>
    <p>Questions about hours, large orders, private events, or anything else? Drop us a line and we'll get back to you.</p>
  </div>
  <form class="contact-form" action="<?= e($formspree) ?>" method="POST">
    <!-- Honeypot: bots fill this; humans never see it. Formspree drops _gotcha hits. -->
    <div class="hp-field" aria-hidden="true">
      <label for="contact-gotcha">Leave this field empty</label>
      <input type="text" id="contact-gotcha" name="_gotcha" tabindex="-1" autocomplete="off">
    </div>
    <div class="field">
      <label for="name">Name <span class="req">*</span></label>
      <input class="input" type="text" id="name" name="name" autocomplete="name" required>
    </div>
    <div class="field">
      <label for="email">Email <span class="req">*</span></label>
      <input class="input" type="email" id="email" name="email" autocomplete="email" required>
    </div>
    <div class="field">
      <label for="message">Message <span class="req">*</span></label>
      <textarea class="textarea" id="message" name="message" rows="6" required></textarea>
    </div>
    <div class="form-actions">
      <a class="btn form-actions__left" href="<?= e(url('/')) ?>">Cancel</a>
      <button class="btn btn--solid" type="submit">Send</button>
    </div>
  </form>
</div></section>
