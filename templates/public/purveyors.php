<?php
/**
 * Our Purveyors (screen 2b), faithful port of mockups/purveyors.html.
 * Source-to-plate ethos + named local Arizona partners.
 */
?>
<!-- PAGE HERO -->
<section class="page-hero">
  <img src="<?= e(asset('img/banner-alternate-1536x1024.jpg')) ?>" alt="Bright daylight spread of fresh greens, plated dishes and pastel glassware on cream linen" fetchpriority="high">
  <div class="page-hero__in">
    <h1 class="disp">Our Purveyors</h1>
    <p>Thoughtful care in the kitchen begins with thoughtful care at the source. We build our menu around the people who grow, bake, and raise it.</p>
  </div>
</section>

<!-- ETHOS -->
<section class="section"><div class="container">
  <div class="ethos">
    <div>
      <span class="kicker">Source to plate</span>
      <h2 class="disp">The good stuff starts upstream</h2>
    </div>
    <div>
      <p>Everything we serve carries the work of someone we know. We prioritize local farmers and artisan growers, the bakers, dairies, and ranchers of the great state 48, because the freshest, most honest ingredients come from people who care as much as we do.</p>
      <p>Scratch-made, seed-oil free, no preservatives, grass-fed and organic where it counts. When a vegetable travels a few miles instead of a few thousand, you can taste the difference, and so can your family.</p>
      <p class="ethos__note">Meet a few of the Arizona makers behind the menu.</p>
    </div>
  </div>
</div></section>

<!-- PURVEYORS GRID -->
<section class="section section--soft"><div class="container">
  <div class="head"><h2 class="disp">Who we work with</h2><p>A short list of the partners whose work shows up on your plate, day in and day out.</p></div>

  <div class="grid grid--3">
    <?php
    $purveyors = [
        ['Noble Bread', 'Phoenix', 'Naturally leavened, slow-fermented loaves baked daily, homestyle white, baguette, and a buttery brioche.', 'Our toasts, sandwiches & boards'],
        ['Steadfast Farm', 'Scottsdale', 'A neighboring farm just up the road, pasture-raised eggs and bright, just-picked greens.', 'Our deviled eggs & arugula salad'],
        ["Crow's Dairy", 'Buckeye', 'Family goat dairy crafting their signature "goatija" and fresh goat cheese, tangy, creamy, local.', 'Our cheese boards & spreads'],
        ["Fra'Mani", 'Artisan charcuterie', 'Old-world salumi made simply and well, including a sweet apple ham we reach for again and again.', 'Our build-your-own boards'],
        ['Sweet Republic', 'Scottsdale', 'Award-winning small-batch creamery, their vanilla bean ice cream is the real deal.', 'Our desserts & affogato'],
        ['Jones', 'Organic soda', 'Cane-sweetened organic root beer, the kind of fizz that pairs with a long, easy lunch.', 'Our cooler & floats'],
        ['Copper State', 'Arizona ranch', 'Grass-fed, well-raised beef, including the tri tip we slow-cook for boards and evening plates.', 'Our evening boards'],
    ];
    foreach ($purveyors as [$name, $where, $desc, $tie]): ?>
    <article class="card">
      <div class="card__body purv__body">
        <div class="purv__head"><span><?= e($name) ?></span><span class="purv__where"><?= e($where) ?></span></div>
        <p><?= e($desc) ?></p>
        <span class="purv__tie"><?= e($tie) ?></span>
      </div>
    </article>
    <?php endforeach; ?>

    <div class="purv__more">
      <h3 class="disp">…and many more local makers</h3>
      <p>From specialty salts to the small businesses on our gift wall, we're always looking for the next neighbor to source from.</p>
    </div>
  </div>
</div></section>

<!-- CLOSING SPLIT -->
<section class="split">
  <div class="split__media"><img src="<?= e(asset('img/themarket.jpg')) ?>" alt="Overhead of Amelia's market goods, house granola, oils, salts, root chips and energy bites"></div>
  <div class="split__body section--soft">
    <h2 class="disp">Taste the source</h2>
    <p>Many of the same ingredients our purveyors supply are bottled, jarred, and bagged for your own kitchen. Browse the market, or come sit down and let us plate it for you.</p>
    <div class="purv__close-cta">
      <a class="btn btn--solid" href="<?= e(url('/market')) ?>">Visit the Market</a>
      <a class="btn" href="<?= e(url('/menu')) ?>">See the Menu</a>
    </div>
  </div>
</section>
