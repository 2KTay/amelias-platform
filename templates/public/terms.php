<?php
/**
 * Terms of Service + refund / cancellation policy, CMS-driven via the 'terms'
 * page (Content::getPage), with a sensible default when none is published.
 *
 * Expects: $page (?array from pages).
 */
$page = $page ?? null;
?>
<section class="page-head"><div class="container">
  <h1 class="disp"><?= e($page['title'] ?? 'Terms & Refund Policy') ?></h1>
  <?php if (!empty($page['published_at'])): ?>
    <p class="muted">Last updated <?= e(fmt_date($page['published_at'], 'F j, Y')) ?></p>
  <?php endif; ?>
</div></section>

<section class="section"><div class="container container--narrow prose">
  <?php if ($page !== null && !empty($page['body_html'])): ?>
    <?= \Amelias\Services\Content::sanitize((string) $page['body_html']) ?>
  <?php else: ?>
    <p>These terms govern your use of the Amelia's by EAT website and the orders, reservations, and memberships you place through it. By using the site, you agree to them.</p>

    <h2>Orders &amp; pickup</h2>
    <p>Menu availability, pricing, and pickup times are shown at checkout and may change. We prepare food to order; please pick up within your selected window so it's at its best. If we can't fulfill an item, we'll contact you to adjust or refund it.</p>

    <h2>Refunds &amp; cancellations, pickup orders</h2>
    <p>If something isn't right with your order, contact us the same day at <a class="ulink" href="tel:6024995195">(602) 499-5195</a> and we'll make it right with a remake or a refund. Because food is made fresh, we generally cannot refund completed orders that have been picked up unless there was an error on our part.</p>

    <h2>Reservations</h2>
    <p>For larger parties we may require a deposit at booking; the deposit is applied to your final bill. You may cancel or change a reservation up to the cutoff shown when you book (typically 24 hours in advance) for a full deposit refund. Cancellations after the cutoff, or no-shows, may forfeit the deposit.</p>

    <h2>Catering &amp; events</h2>
    <p>Catering and full-service events are quoted individually and may require advance notice and a deposit. Cancellation terms are stated on your catering agreement.</p>

    <h2>Wine club &amp; memberships</h2>
    <p>Recurring memberships renew automatically until you cancel. You can cancel anytime before the next billing date; we don't refund the current period once it has begun, but you'll keep your benefits through the end of that period.</p>

    <h2>Gift cards</h2>
    <p>Gift cards are redeemable for food and goods at Amelia's, are non-refundable, and cannot be exchanged for cash except where required by law.</p>

    <h2>Contact</h2>
    <p>Amelia's by EAT · 8240 N Hayden Road, Ste B-105, Scottsdale, AZ 85258 · <a class="ulink" href="mailto:info@ameliasaz.com">info@ameliasaz.com</a></p>
  <?php endif; ?>
</div></section>
