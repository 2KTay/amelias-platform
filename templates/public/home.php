<?php /** Home (screen 1), faithful port of mockups/home-v3.html. Task 1.6 binds copy to CMS. */ ?>
<!-- HERO -->
<section class="hero">
  <div class="hero__copy">
    <h1>The neighborhood<br>just got better</h1>
    <p class="lede">Amelia's is a conscious, made-from-scratch, all-day eating place + market. Created by chef and founder Stacey Weber, mindfully curated foods, from source to plate.</p>
    <div class="hero__cta">
      <a class="btn btn--solid btn--lg" href="<?= e(url('/menu')) ?>">Order Pickup</a>
      <a class="btn btn--lg" href="<?= e(url('/reserve')) ?>">Reserve a Table</a>
    </div>
    <div class="hero__meta">
      <span class="open-dot">Open now · until 8:00p</span>
      <span>8240 N Hayden Rd, Ste B-105 · Scottsdale</span>
    </div>
  </div>
  <div class="hero__media">
    <img src="<?= e(asset('img/5I8A7492-1024x1536.jpeg')) ?>" alt="Amelia's dining room, slatted-wood wall, woven-leather chairs, warm afternoon light" fetchpriority="high">
    <span class="hero__cap">McCormick Ranch · open kitchen</span>
  </div>
</section>

<!-- QUIET VALUES LINE -->
<div class="values"><div class="values__in">
  <span>scratch made</span>·<span>locally sourced</span>·<span>seed-oil free</span>
</div></div>

<!-- FROM THE KITCHEN (admin-picked featured products; section omitted when none) -->
<?php if (!empty($featured)): ?>
  <?php
    /** Price as dollars: whole numbers show no decimals, otherwise 2dp. No $ to match the design. */
    $dishPrice = static function (int $cents): string {
        $dollars = $cents / 100;
        return $dollars == (int) $dollars
            ? (string) (int) $dollars
            : number_format($dollars, 2);
    };
    $lg   = $featured[0];
    $rest = array_slice($featured, 1, 2);
  ?>
  <section class="section"><div class="container">
    <div class="head head--row">
      <h2 class="disp">From the kitchen</h2>
      <a class="link-arrow" href="<?= e(url('/menu')) ?>">View full menu <svg width="20" height="12" viewBox="0 0 20 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 6h17M13 1l5 5-5 5"/></svg></a>
    </div>
    <div class="feat">
      <article class="dish dish--lg">
        <?php if (!empty($lg['image_path'])): ?>
          <div class="dish__img"><img src="<?= e(asset('uploads/' . ltrim((string) $lg['image_path'], '/'))) ?>" alt="<?= e((string) $lg['name']) ?>"></div>
        <?php endif; ?>
        <div class="dish__row"><span class="dish__name"><?= e((string) $lg['name']) ?></span><span class="dish__price"><?= e($dishPrice((int) $lg['price_cents'])) ?></span></div>
        <?php if (!empty($lg['description'])): ?>
          <p class="dish__desc"><?= e((string) $lg['description']) ?></p>
        <?php endif; ?>
      </article>
      <?php if ($rest !== []): ?>
        <div class="feat__col">
          <?php foreach ($rest as $sm): ?>
            <article class="dish dish--sm">
              <?php if (!empty($sm['image_path'])): ?>
                <div class="dish__img"><img src="<?= e(asset('uploads/' . ltrim((string) $sm['image_path'], '/'))) ?>" alt="<?= e((string) $sm['name']) ?>"></div>
              <?php endif; ?>
              <div class="dish__row"><span class="dish__name"><?= e((string) $sm['name']) ?></span><span class="dish__price"><?= e($dishPrice((int) $sm['price_cents'])) ?></span></div>
              <?php if (!empty($sm['description'])): ?>
                <p class="dish__desc"><?= e((string) $sm['description']) ?></p>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div></section>
<?php endif; ?>

<!-- PHILOSOPHY BAND -->
<section class="philo"><div class="container">
  <h2 class="disp">When you eat well, you feel good.</h2>
  <p>Amelia's aims to bring people together with food, a modern dining experience that makes eating well enjoyable, with the freshest locally sourced ingredients to delight and nourish all.</p>
</div></section>

<!-- THE MARKET -->
<section class="split">
  <div class="split__media"><img src="<?= e(asset('img/themarket.jpg')) ?>" alt="Overhead of Amelia's market goods, house granola, oils, salts, root chips, energy bites"></div>
  <div class="split__body section--soft">
    <h2 class="disp">The Market</h2>
    <p>Every saleable grocery item sold in our market is used in Amelia's kitchen. From specialty salts to homemade granola, you'll find a unique assortment of grab-and-go food, beverages, and grocery items to take home, or give as gifts.</p>
    <div class="mt-4"><a class="btn btn--solid" href="<?= e(url('/market')) ?>">View the Market</a></div>
  </div>
</section>

<!-- OUR STORY (B&W heritage) -->
<section class="story"><div class="container">
  <img src="<?= e(asset('img/new-about-hero.jpg')) ?>" alt="Black-and-white portrait of Amelia, and Stacey Weber laughing with her son in the kitchen">
  <div>
    <span class="kicker">Named for Stacey's grandmother</span>
    <h2 class="disp">Our Story</h2>
    <p>Amelia's by EAT, named for founder Stacey Weber's grandmother, is an extension of all that Stacey's customers have come to love about her first concept, EAT. Amelia's offers counter service during the day and table service in the evening, an all-day menu, a build-your-own-board concept in the evening, scratch-made cocktails, craft coffees, grab-and-go, freezer items, plus market goods and a gift wall sourced from small businesses.</p>
    <p>Amelia's is a community gathering place that provides mindful offerings made with the freshest locally sourced ingredients, all served with heart and love, where families can enjoy consciously curated food in a casual environment. Families always welcome.</p>
    <a class="link-arrow" href="<?= e(url('/story')) ?>">Read more <svg width="20" height="12" viewBox="0 0 20 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 6h17M13 1l5 5-5 5"/></svg></a>
  </div>
</div></section>

<!-- CATERING -->
<section class="section"><div class="container">
  <div class="head"><h2 class="disp">Catering &amp; events</h2><p>No matter the occasion, we've got you covered, from grab-and-go platters to full-service events.</p></div>
  <div class="cater__grid">
    <div class="cater__cell">
      <span class="cater__lead">Order 48 hrs ahead</span>
      <h3 class="disp">Party Platters by Amelia's</h3>
      <p>Party platters and nosh boards of our best-selling items, in portions to serve an intimate or large crowd, corporate events, showers, birthdays, family meals.</p>
      <a class="link-arrow" href="<?= e(url('/catering')) ?>">Order online <svg width="20" height="12" viewBox="0 0 20 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 6h17M13 1l5 5-5 5"/></svg></a>
    </div>
    <div class="cater__cell">
      <span class="cater__lead">Full service</span>
      <h3 class="disp">Boutique Catering by EAT</h3>
      <p>EAT is Chef Stacey Weber's first concept, customized menus and full-service catering, from intimate in-home showers to large-scale weddings and corporate events. Let's talk.</p>
      <a class="link-arrow" href="<?= e(url('/catering')) ?>">Start an inquiry <svg width="20" height="12" viewBox="0 0 20 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 6h17M13 1l5 5-5 5"/></svg></a>
    </div>
    <div class="cater__cell">
      <span class="cater__lead">Order Thu · delivered Mon</span>
      <h3 class="disp">Meal Delivery by EAT</h3>
      <p>Pre-prepared meals that take the stress out of eating well, staples spanning a range of dietary preferences. Order by Thursday 8 am for Monday delivery.</p>
      <a class="link-arrow" href="<?= e(url('/catering')) ?>">Order here <svg width="20" height="12" viewBox="0 0 20 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 6h17M13 1l5 5-5 5"/></svg></a>
    </div>
  </div>
</div></section>

<!-- RITUALS: WINE CLUB + SUNDAY SUPPER -->
<section class="rituals">
  <article class="ritual">
    <img src="<?= e(asset('img/WineWall-2-1365x2048.jpg')) ?>" alt="Floor-to-ceiling white-shelved wine wall stocked with natural and organic bottles">
    <div class="ritual__body">
      <span class="ritual__detail">Wine Club · $40 / month</span>
      <h3 class="disp">The Wine Club</h3>
      <p>Two featured bottles a month from our in-house somms, a 3rd-Wednesday tasting with small bites (5–7 pm), waived corkage that night, and 15% off the wall anytime.</p>
      <a class="link-arrow" href="<?= e(url('/wine-club')) ?>">Join the club <svg width="20" height="12" viewBox="0 0 20 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 6h17M13 1l5 5-5 5"/></svg></a>
    </div>
  </article>
  <article class="ritual ritual--bw">
    <img src="<?= e(asset('img/glasses-clinking.jpg')) ?>" alt="Hands raising glasses together in a warm, low-lit toast">
    <div class="ritual__body">
      <span class="ritual__detail">One Sunday a month</span>
      <h3 class="disp">Sunday Supper</h3>
      <p>One long table, one seasonal menu, all-Arizona sourcing. Shared plates and good people, the way a meal is meant to be.</p>
      <a class="link-arrow" href="<?= e(url('/sunday-supper')) ?>">Reserve a seat <svg width="20" height="12" viewBox="0 0 20 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 6h17M13 1l5 5-5 5"/></svg></a>
    </div>
  </article>
</section>

<!-- COME VISIT -->
<section class="section section--soft"><div class="container visit">
  <div class="visit__info">
    <h2 class="disp">Come visit</h2>
    <p class="visit__see serif-it">See you in sunny Scottsdale.</p>
    <dl class="mt-4">
      <dt>Where</dt><dd>8240 N Hayden Road, Ste B-105<br>Scottsdale, AZ 85258</dd>
      <dt>Hours</dt><dd>Sun 7a–3p · Mon–Thu 7a–8p · Fri–Sat 7a–close</dd>
      <dt>Call</dt><dd><a class="ulink" href="tel:6024995195">(602) 499-5195</a></dd>
      <dt>Email</dt><dd><a class="ulink" href="mailto:info@ameliasaz.com">info@ameliasaz.com</a></dd>
    </dl>
    <div class="mt-4"><a class="btn btn--solid" href="<?= e(url('/location')) ?>">Directions &amp; parking</a></div>
  </div>
  <img class="visit__map" src="<?= e(asset('img/5I8A7679-1024x1536.jpeg')) ?>" alt="Amelia's interior">
</div></section>
