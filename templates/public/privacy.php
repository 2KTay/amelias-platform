<?php
/**
 * Privacy Policy, CMS-driven via the 'privacy' page (Content::getPage). When no
 * published page exists, a sensible default policy + cookie note renders so the
 * cookie bar's link is never dead.
 *
 * Expects: $page (?array from pages), title/body_html when published.
 */
$page = $page ?? null;
?>
<section class="page-head"><div class="container">
  <h1 class="disp"><?= e($page['title'] ?? 'Privacy Policy') ?></h1>
  <?php if (!empty($page['published_at'])): ?>
    <p class="muted">Last updated <?= e(fmt_date($page['published_at'], 'F j, Y')) ?></p>
  <?php endif; ?>
</div></section>

<section class="section"><div class="container container--narrow prose">
  <?php if ($page !== null && !empty($page['body_html'])): ?>
    <?= \Amelias\Services\Content::sanitize((string) $page['body_html']) ?>
  <?php else: ?>
    <p>Amelia's by EAT ("we", "us") respects your privacy. This policy explains what we collect, why, and the choices you have. Questions? Email <a class="ulink" href="mailto:info@ameliasaz.com">info@ameliasaz.com</a>.</p>

    <h2>What we collect</h2>
    <p>When you place an order, make a reservation, join the wine club, or contact us, we collect the details you provide, your name, email, phone number, order/reservation details, and (for paid orders) billing information processed securely by our payment provider. We never store full card numbers on our servers.</p>

    <h2>How we use it</h2>
    <ul>
      <li>To process and fulfill your orders, reservations, and memberships.</li>
      <li>To send you transactional messages (receipts, confirmations, status updates).</li>
      <li>To respond to your questions and improve our food and service.</li>
    </ul>

    <h2>Cookies &amp; analytics</h2>
    <p>We use a small number of cookies to keep your cart and session working, and, only with your consent, privacy-respecting analytics to understand how the site is used. You can decline analytics cookies from the banner at the bottom of the page, and you can clear cookies anytime in your browser settings.</p>

    <h2>Sharing</h2>
    <p>We share information only with the service providers who help us operate, our payment processor, email/SMS delivery, and hosting, and only as needed to provide the service. We do not sell your personal information.</p>

    <h2>Your choices</h2>
    <p>You may request access to, correction of, or deletion of your personal information by emailing <a class="ulink" href="mailto:info@ameliasaz.com">info@ameliasaz.com</a>. You can unsubscribe from marketing messages at any time.</p>

    <h2>Contact</h2>
    <p>Amelia's by EAT · 8240 N Hayden Road, Ste B-105, Scottsdale, AZ 85258 · <a class="ulink" href="tel:6024995195">(602) 499-5195</a></p>
  <?php endif; ?>
</div></section>
