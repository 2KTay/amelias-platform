<?php
/**
 * Catering & events (screen 11), ported from mockups/catering.html.
 *
 * Three offerings, the platter catalog (catering products with serves-12/24
 * variants), and an inquiry form that POSTs to /catering (creates a
 * catering_requests row, lead-time validated server-side).
 *
 * @var array<string,list<array<string,mixed>>> $platterGroups
 * @var int        $platterLead   lead-time hours for party platters
 * @var array|null $flash
 */
$platterGroups = $platterGroups ?? [];
$platterLead   = (int) ($platterLead ?? 48);
$flash         = $flash ?? null;
?>
<section class="page-hero">
  <img src="<?= e(asset('img/boutique-catering-1536x1024.jpg')) ?>" alt="An Amelia's catering spread laid out on cream linen, boards, platters and fresh seasonal dishes">
  <div class="page-hero__in">
    <h1 class="disp">Catering &amp; events</h1>
    <p>No matter the occasion, we've got you covered, from grab-and-go party platters to full-service catered events, all made from scratch with locally sourced ingredients.</p>
  </div>
</section>

<section class="section"><div class="container">
  <?php if ($flash !== null): ?>
    <div class="alert <?= ($flash['type'] ?? '') === 'error' ? 'alert--danger' : 'alert--ok' ?>" role="status"><?= e((string) ($flash['message'] ?? '')) ?></div>
  <?php endif; ?>

  <div class="head">
    <h2 class="disp">Three ways to gather</h2>
    <p>Pick the path that fits your event. Same conscious kitchen, same care at the source.</p>
  </div>
  <div class="cater__grid">
    <div class="cater__cell">
      <span class="cater__lead">Order <?= (int) $platterLead ?> hrs ahead</span>
      <h3 class="disp">Party Platters by Amelia's</h3>
      <p>Party platters and nosh boards of our best-selling items, in portions to serve an intimate or large crowd, corporate events, showers, birthdays, family meals. Order online and pick up in store.</p>
      <a class="link-arrow" href="#platters">Order online <svg width="20" height="12" viewBox="0 0 20 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 6h17M13 1l5 5-5 5"/></svg></a>
    </div>
    <div class="cater__cell">
      <span class="cater__lead">Full service</span>
      <h3 class="disp">Boutique Catering by EAT</h3>
      <p>EAT is Chef Stacey Weber's first concept, customized menus and full-service catering, from intimate in-home showers to large-scale weddings and corporate events. Tell us about your day and we'll build the rest.</p>
      <a class="link-arrow" href="#inquiry">Start an inquiry <svg width="20" height="12" viewBox="0 0 20 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 6h17M13 1l5 5-5 5"/></svg></a>
    </div>
    <div class="cater__cell">
      <span class="cater__lead">Order Thu · delivered Mon</span>
      <h3 class="disp">Meal Delivery by EAT</h3>
      <p>Pre-prepared meals that take the stress out of eating well, staples spanning a range of dietary preferences. Order by Thursday 8 am for Monday delivery.</p>
      <a class="link-arrow" href="#inquiry">Order here <svg width="20" height="12" viewBox="0 0 20 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 6h17M13 1l5 5-5 5"/></svg></a>
    </div>
  </div>
</div></section>

<section class="split split--rev">
  <div class="split__media"><img src="<?= e(asset('img/boutique-catering-2.jpg')) ?>" alt="Yogurt, granola and berry parfaits topped with edible flowers, arranged on a silver catering tray"></div>
  <div class="split__body section--soft">
    <h2 class="disp">Made for the moment</h2>
    <p>Every platter is built from the same scratch-made, seed-oil-free kitchen you'll find on our counter, Noble bread, Steadfast Farm eggs, Fra'Mani charcuterie, Crow's Dairy cheese. Beautiful enough for the table, honest enough for every day.</p>
    <p class="btn-row mt-6"><a class="btn btn--solid" href="#platters">See the platter menu</a></p>
  </div>
</section>

<section class="section" id="platters"><div class="container">
  <div class="head">
    <h2 class="disp">The catering platter menu</h2>
    <p>Every item is a platter offered in two sizes. Prices show as <strong>serves 12 | serves 24</strong>. See online ordering for photos and quantities.</p>
  </div>

  <div class="note-band">
    <p><span class="serif-it">A little planning goes a long way.</span>Order party platters at least <strong><?= (int) $platterLead ?> hours</strong> before your event.</p>
    <p><span class="serif-it">How much to order?</span>We suggest 3–4 platters to serve a group of 12 generously.</p>
  </div>

  <?php if (empty($platterGroups)): ?>
    <div class="alert mt-6" role="status">Our platter menu is being finalized, start an inquiry below or call us and we'll walk you through it.</div>
  <?php else: ?>
    <div class="menu-size-head menu-size-head--spaced">
      <span class="menu-size-head__label">Platter</span>
      <span class="menu-size-head__label">serves 12&nbsp;&nbsp;|&nbsp;&nbsp;serves 24</span>
    </div>

    <?php foreach ($platterGroups as $category => $items): ?>
      <div class="menu-section">
        <h3><?= e((string) $category) ?></h3>
        <?php
          // Category note: surfaced from the first item carrying one (e.g.
          // "Gluten-free bread available +12 | 24."). The class is styled in
          // catering.css; only emit when data provides a note.
          $sectionNote = '';
          foreach ($items as $itemForNote) {
              if (!empty($itemForNote['note'])) {
                  $sectionNote = (string) $itemForNote['note'];
                  break;
              }
          }
        ?>
        <?php if ($sectionNote !== ''): ?>
          <p class="menu-section__note"><?= e($sectionNote) ?></p>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
          <div class="menu-item">
            <span class="menu-item__name"><?= e((string) $item['name']) ?></span>
            <span class="menu-item__price">
              <?php if (!empty($item['variants'])): ?>
                <?php foreach ($item['variants'] as $i => $v): ?><?= $i ? ' | ' : '' ?><?= e(fmt_money((int) $v['price_cents'])) ?><?php endforeach; ?>
              <?php else: ?>
                <?= e(fmt_money((int) $item['price_cents'])) ?>
              <?php endif; ?>
            </span>
            <?php if (!empty($item['description'])): ?>
              <span class="menu-item__desc"><?= e((string) $item['description']) ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div></section>

<section class="section--ink"><div class="container cater__sourcing">
  <h2 class="disp cater__sourcing-h">Thoughtful care in the kitchen begins with thoughtful care at the source.</h2>
  <p class="cater__sourcing-p">To ensure peak nutrition, freshness and deliciousness, we prioritize sourcing from local farmers and artisan growers, Noble Bread, Steadfast Farm, Crow's Dairy, Fra'Mani, Sweet Republic, inspired by the great state 48 we call home.</p>
</div></section>

<section class="section section--soft" id="inquiry"><div class="container">
  <div class="head">
    <h2 class="disp">Start a catering inquiry</h2>
    <p>Tell us about your event and we'll be in touch to build the right menu. For party platters, please allow at least <?= (int) $platterLead ?> hours' notice.</p>
  </div>
  <div class="form-wrap">
    <form action="<?= e(url('/catering')) ?>" method="post" novalidate>
      <?= csrf_field() ?>
      <div class="grid grid--2">
        <div class="field">
          <label for="offering">Offering <span class="req">*</span></label>
          <select class="select" id="offering" name="offering" required>
            <option value="party_platters">Party platters (pickup)</option>
            <option value="boutique">Boutique full-service catering</option>
            <option value="meal_delivery">Meal delivery</option>
          </select>
        </div>
        <div class="field">
          <label for="event-type">Event type</label>
          <input class="input" type="text" id="event-type" name="event_type" placeholder="Corporate, wedding, shower, birthday…">
        </div>
      </div>
      <div class="grid grid--2">
        <div class="field">
          <label for="event-date">Event date <span class="req">*</span></label>
          <input class="input" type="date" id="event-date" name="event_date" required>
          <p class="field__hint">Party platters need <?= (int) $platterLead ?> hours' lead time.</p>
        </div>
        <div class="field">
          <label for="headcount">Headcount</label>
          <input class="input" type="number" id="headcount" name="headcount" min="1" inputmode="numeric" placeholder="e.g. 24">
          <p class="field__hint">We suggest 3–4 platters per 12 guests.</p>
        </div>
      </div>
      <div class="grid grid--2">
        <div class="field">
          <label for="name">Your name <span class="req">*</span></label>
          <input class="input" type="text" id="name" name="name" autocomplete="name" required>
        </div>
        <div class="field">
          <label for="email">Email <span class="req">*</span></label>
          <input class="input" type="email" id="email" name="email" autocomplete="email" required>
        </div>
      </div>
      <div class="grid grid--2">
        <div class="field">
          <label for="phone">Phone</label>
          <input class="input" type="tel" id="phone" name="phone" autocomplete="tel">
        </div>
        <div class="field"></div>
      </div>
      <div class="field">
        <label for="message">Tell us about your event</label>
        <textarea class="textarea" id="message" name="message" rows="5" placeholder="Occasion, dietary needs, favorite platters, pickup or delivery…"></textarea>
      </div>
      <div class="form-actions">
        <span class="form-actions__left muted cater__call">Or call us at <a href="tel:6024995195" class="ulink">(602) 499-5195</a></span>
        <button class="btn btn--solid btn--lg" type="submit">Send inquiry</button>
      </div>
    </form>
  </div>
</div></section>
