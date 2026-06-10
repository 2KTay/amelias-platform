<?php
/**
 * Our Story (screen 2), faithful port of mockups/story.html onto the public
 * layout. Nav + footer come from layout partials; this is the <main> body.
 * The opening prose is CMS-bound via the `story.body` content block, with the
 * mockup copy as the editorial fallback.
 *
 * Expects: $storyBody (sanitized HTML string).
 */
$storyBody = $storyBody ?? '';
?>
<!-- PAGE HERO (B&W heritage) -->
<section class="page-hero">
  <img src="<?= e(asset('img/new-about-hero.jpg')) ?>" alt="Black-and-white photograph of grandmother Amelia and Stacey Weber laughing together with her son" fetchpriority="high">
  <div class="page-hero__in">
    <span class="kicker">Named for Stacey's grandmother</span>
    <h1 class="disp mt-2">Our Story</h1>
    <p>A conscious, made-from-scratch, all-day eating place &amp; market by chef and founder Stacey Weber, created in the spirit of the woman who fed her family with heart.</p>
  </div>
</section>

<!-- STORY (CMS body + heritage image) -->
<section class="section"><div class="container">
  <div class="story-lead">
    <div class="story-prose">
      <?php if ($storyBody !== ''): ?>
        <?= $storyBody /* sanitized by Content::get() */ ?>
      <?php else: ?>
        <p>Amelia's by EAT, named for founder Stacey Weber's grandmother, is an extension of all that Stacey's customers have come to love about her first concept EAT. Amelia's offers counter service during the day and table service in the evening with an all day menu, BYOB (build your own board concept in the evening), scratch made cocktails, craft coffees, grab and go food options, freezer items, assorted beverages, plus market items and unique gift wall sourced from small businesses.</p>
        <p>Amelia's is a community gathering place that provides mindful offerings made with the freshest locally sourced ingredients, all served with heart and love, where families can enjoy consciously curated food in a casual environment. Families always welcome!</p>
      <?php endif; ?>
    </div>
    <div class="story-lead__media">
      <img src="<?= e(asset('img/5I8A7492-1024x1536.jpeg')) ?>" alt="Amelia's dining room, slatted-wood wall, woven-leather chairs, globe sconces and cacti in warm light">
    </div>
  </div>
</div></section>

<!-- VALUES BAND -->
<div class="values-band"><div class="values-band__in">
  <span>scratch made</span>·<span>locally sourced</span>·<span>seed-oil free</span>·<span>no preservatives</span>·<span>grass-fed &amp; organic</span>
</div></div>

<!-- OUR PURVEYORS TEASER -->
<section class="split">
  <div class="split__media"><img src="<?= e(asset('img/themarket.jpg')) ?>" alt="Overhead of Amelia's market goods, house granola, oils, salts, root chips and energy bites"></div>
  <div class="split__body section--soft">
    <span class="kicker">Sourced with care</span>
    <h2 class="disp mt-2">Our purveyors</h2>
    <p>We build our menu around the growers, bakers and makers we trust, bread from Noble Bread, produce and eggs from Steadfast Farm, dairy from Crow's Dairy, charcuterie from Fra'Mani, and ice cream from Sweet Republic. Local first, always.</p>
    <div class="purveyors__cta"><a class="link-arrow" href="<?= e(url('/purveyors')) ?>">Meet our purveyors <svg width="20" height="12" viewBox="0 0 20 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 6h17M13 1l5 5-5 5"/></svg></a></div>
  </div>
</section>

<!-- CTA -->
<section class="section section--soft"><div class="container prose-center">
  <h2 class="disp">Come eat with us</h2>
  <p class="lede mx-auto">The neighborhood just got better. Pull up a chair, or take a little of Amelia's home with you.</p>
  <div class="cta-row">
    <a class="btn btn--solid btn--lg" href="<?= e(url('/reserve')) ?>">Reserve a Table</a>
    <a class="btn btn--lg" href="<?= e(url('/menu')) ?>">Order Pickup</a>
  </div>
  <p class="serif-it families-note">Families always welcome.</p>
</div></section>
