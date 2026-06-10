<?php
/**
 * Now Hiring (screen 18a), faithful port of mockups/careers.html.
 * Intro + values + open roles + a Formspree-backed application form with a
 * _gotcha honeypot.
 *
 * HUMAN-BLOCKED: the Formspree endpoint id is a placeholder (REPLACE_ME), a
 * human must create the form at formspree.io and paste the real id.
 */
$formspree = $formspreeCareers ?? 'https://formspree.io/f/REPLACE_ME';
$roles = [
    ['Line Cook', 'Full-time · scratch kitchen', "Cook our all-day menu from scratch on a busy line, prep, plating and a build-your-own-board concept in the evenings. Steady hands, clean habits, and pride in a plate that goes out right."],
    ['Barista', 'Full or part-time · craft coffee', "Pull craft coffees and pour our morning energy from behind the counter. A friendly face for regulars, an eye for detail in the cup, and the easy warmth that makes someone's day start better."],
    ['Host', 'Full or part-time · front of house', "Set the tone the moment guests walk in, counter service by day, table service in the evening. Genuinely welcoming, organized under a rush, and happy to make families feel right at home."],
    ['Catering Lead', 'Full-time · catering & events', "Own platters, boards and full-service events from inquiry to delivery. Calm logistics, sharp communication and a love of feeding a crowd well, coordinating closely with our kitchen and Boutique Catering by EAT."],
];
?>
<!-- PAGE HERO -->
<section class="page-hero">
  <img src="<?= e(asset('img/5I8A7492-1024x1536.jpeg')) ?>" alt="Amelia's dining room, slatted-wood wall, woven-leather chairs and warm afternoon light" fetchpriority="high">
  <div class="page-hero__in">
    <h1 class="disp">Now hiring</h1>
    <p>Come build something good with us. We're growing our McCormick Ranch team and we'd love to meet people who cook, pour and host with heart.</p>
  </div>
</section>

<!-- INTRO + VALUES -->
<section class="section"><div class="container">
  <div class="intro">
    <div>
      <span class="kicker">A place to grow</span>
      <h2 class="disp mt-2">Work where the food is made from scratch</h2>
      <p class="lede mt-4">Amelia's is a conscious, all-day eating place + market built around real cooking and real hospitality. Our kitchen is scratch-made and seed-oil free, our shelves are stocked from small local farms and makers, and our team treats every guest like a neighbor, because most of them are.</p>
      <p class="mt-2">If you care about doing things the right way, work hard, and like the people you work beside, you'll feel at home here. We hire for warmth and curiosity first; skills we can teach.</p>
      <ul class="values-list">
        <li>Scratch-made cooking, no shortcuts, no preservatives, seed-oil free</li>
        <li>Locally sourced from purveyors like Noble Bread, Steadfast Farm, Crow's Dairy, Fra'Mani &amp; Sweet Republic</li>
        <li>Grass-fed &amp; organic wherever we can get it</li>
        <li>A community gathering place, families and good people always welcome</li>
      </ul>
    </div>
    <div class="intro__media">
      <img src="<?= e(asset('img/5I8A7749-1-1024x1536.jpeg')) ?>" alt="Warm interior corner of Amelia's with woven-leather seating and globe sconces">
    </div>
  </div>
</div></section>

<!-- OPEN POSITIONS -->
<section class="section section--soft"><div class="container">
  <div class="head"><h2 class="disp">Open positions</h2><p>Roles open right now. Don't see your fit? Reach out anyway, we're always glad to meet good people.</p></div>

  <div class="apply-wrap">
    <?php foreach ($roles as [$name, $meta, $desc]): ?>
    <div class="role">
      <div>
        <div class="role__name"><?= e($name) ?></div>
        <div class="role__meta"><?= e($meta) ?></div>
      </div>
      <a class="btn role__apply" href="#apply">Apply</a>
      <p class="role__desc"><?= e($desc) ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</div></section>

<!-- APPLICATION FORM (Formspree) -->
<section class="section" id="apply"><div class="container container--narrow">
  <div class="head"><h2 class="disp">Tell us about you</h2><p>Send a quick note and we'll be in touch. Prefer email? Write us anytime at <a class="ulink" href="mailto:info@ameliasaz.com">info@ameliasaz.com</a>.</p></div>

  <form class="apply-wrap" action="<?= e($formspree) ?>" method="POST">
    <!-- Honeypot: bots fill this; humans never see it. Formspree drops _gotcha hits. -->
    <div class="hp-field" aria-hidden="true">
      <label for="apply-gotcha">Leave this field empty</label>
      <input type="text" id="apply-gotcha" name="_gotcha" tabindex="-1" autocomplete="off">
    </div>
    <div class="grid--form">
      <div class="field">
        <label for="name">Name <span class="req">*</span></label>
        <input class="input" id="name" name="name" type="text" autocomplete="name" required>
      </div>
      <div class="field">
        <label for="email">Email <span class="req">*</span></label>
        <input class="input" id="email" name="email" type="email" autocomplete="email" required>
      </div>
    </div>

    <div class="field">
      <label for="role">Role you're applying for <span class="req">*</span></label>
      <select class="select" id="role" name="role" required>
        <option value="" selected disabled>Choose a role…</option>
        <?php foreach ($roles as [$name]): ?>
          <option value="<?= e($name) ?>"><?= e($name) ?></option>
        <?php endforeach; ?>
        <option value="General interest">Something else / general interest</option>
      </select>
    </div>

    <div class="field">
      <label for="message">A little about you <span class="req">*</span></label>
      <textarea class="textarea" id="message" name="message" rows="5" placeholder="Your experience, when you can start, and why Amelia's sounds like your kind of place." required></textarea>
      <p class="field__hint">No formal resume needed to start the conversation, just say hello.</p>
    </div>

    <div class="form-actions">
      <span class="form-actions__left muted">We read every note. Thanks for thinking of us.</span>
      <button class="btn btn--solid btn--lg" type="submit">Submit application</button>
    </div>
  </form>
</div></section>
