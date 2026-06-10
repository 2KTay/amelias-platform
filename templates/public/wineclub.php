<?php
/**
 * Wine Club storefront (screen 13), ported from mockups/wineclub.html.
 *
 * Perks + a $40/mo join card priced from subscription_tiers. The join button
 * POSTs to /wine-club/join (requires login; the controller redirects to /login
 * when anonymous). Wine is 21+ and pickup-only while the DTC gate is closed.
 *
 * @var array<string,mixed>|null $tier
 * @var list<string> $perks
 * @var bool $loggedIn
 * @var bool $hasMembership
 * @var bool $dtcEnabled
 * @var array|null $flash
 */
$tier  = $tier ?? null;
$perks = $perks ?? [];
$priceCents = (int) ($tier['price_cents'] ?? 4000);
$loggedIn = (bool) ($loggedIn ?? false);
$hasMembership = (bool) ($hasMembership ?? false);
$flash = $flash ?? null;

$perkDetails = [
  'Two featured bottles every month' => 'Hand-chosen by our in-house somms, a pour you\'d probably never have reached for, and one you\'ll want again.',
  'A monthly tasting, 5–7 pm'        => 'Members gather on the third Wednesday for the month\'s selections, poured alongside small bites from the kitchen.',
  'Waived corkage that evening'      => 'Enjoy your club wine on premises the night of the tasting and we\'ll waive the corkage fee.',
  '15% off the curated collection'   => 'On any bottle you buy off the wall, anytime you\'re in, members\' price, no minimum.',
  'Early access to wine dinners'      => 'Member tickets open before the public for our seasonal winemaker dinners and special pourings.',
  'Bottles held for pickup'          => 'Can\'t make the tasting? We\'ll set your selections aside and you can grab them next time you\'re by.',
];

// Short tag shown in the left .perks__mark column (mockup: "Two bottles",
// "3rd Wed.", etc). Mapped for the known perks; falls back to the first two
// words of the perk so it stays data-driven for unmapped tiers.
$perkMarks = [
  'Two featured bottles every month' => 'Two bottles',
  'A monthly tasting, 5–7 pm'        => '3rd Wed.',
  'Waived corkage that evening'      => 'No corkage',
  '15% off the curated collection'   => '15% off',
  'Early access to wine dinners'      => 'First seats',
  'Bottles held for pickup'          => 'Held for you',
];
$perkMark = static function (string $perk) use ($perkMarks): string {
    if (isset($perkMarks[$perk])) {
        return $perkMarks[$perk];
    }
    $words = preg_split('/\s+/', trim($perk)) ?: [];
    return implode(' ', array_slice($words, 0, 2));
};
?>
<section class="page-hero">
  <img src="<?= e(asset('img/WineWall-2-1365x2048.jpg')) ?>" alt="Floor-to-ceiling white-shelved wine wall stocked with natural and organic bottles" fetchpriority="high">
  <div class="page-hero__in">
    <span class="kicker"><?= e(fmt_money($priceCents)) ?> / month · members</span>
    <h1 class="disp">The Wine Club</h1>
    <p>Two bottles a month, chosen for you by our in-house somms, plus a standing seat at the table on the third Wednesday. A small, friendly way to drink better, one neighborhood at a time.</p>
  </div>
</section>

<section class="section"><div class="container">
  <?php if ($flash !== null): ?>
    <div class="alert <?= ($flash['type'] ?? '') === 'error' ? 'alert--danger' : 'alert--ok' ?>" role="status"><?= e((string) ($flash['message'] ?? '')) ?></div>
  <?php endif; ?>

  <div class="club">
    <div>
      <div class="head club__head">
        <h2 class="disp">What's included</h2>
        <p>Membership is <?= e(fmt_money($priceCents)) ?> a month, billed as a simple subscription. Cancel anytime, the wine is yours to keep.</p>
      </div>
      <ul class="perks">
        <?php foreach ($perks as $perk): ?>
          <li>
            <span class="perks__mark"><?= e($perkMark($perk)) ?></span>
            <div>
              <span class="perks__t"><?= e($perk) ?></span>
              <?php if (isset($perkDetails[$perk])): ?>
                <span class="perks__d"><?= e($perkDetails[$perk]) ?></span>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <aside class="join" aria-label="Join the Wine Club">
      <div class="join__price"><span class="join__num"><?= e(fmt_money($priceCents)) ?></span><span class="join__per">/ month</span></div>
      <p class="join__sub">A monthly subscription, billed to your card. Cancel anytime.</p>
      <ul class="join__list">
        <li>Two somm-selected bottles each month</li>
        <li>3rd-Wednesday tasting with small bites</li>
        <li>Waived corkage on tasting night</li>
        <li>15% off the curated collection</li>
      </ul>

      <?php if ($hasMembership): ?>
        <a class="btn btn--solid btn--lg btn--block" href="<?= e(url('/wine-club/portal')) ?>">Manage your membership</a>
      <?php else: ?>
        <form method="post" action="<?= e(url('/wine-club/join')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn--solid btn--lg btn--block" type="submit">Join the club</button>
        </form>
        <?php if (!$loggedIn): ?>
          <p class="join__signin">You'll sign in or create an account to join.</p>
        <?php endif; ?>
      <?php endif; ?>

      <p class="age">
        Must be 21 or older to join. Valid ID is required at pickup and at tastings; we cannot release wine to anyone under 21.
        <?php if (!$dtcEnabled): ?> Club bottles are held for in-store pickup, we do not ship alcohol.<?php endif; ?>
      </p>
    </aside>
  </div>
</div></section>

<section class="section section--soft"><div class="container">
  <div class="head">
    <h2 class="disp">Chosen by our somms</h2>
    <p>Every month's pair is tasted, debated, and picked in-house, no algorithm, no warehouse list. Just two people who love this and want you to love it too.</p>
  </div>
  <div class="somms">
    <article class="somm">
      <h3 class="disp">April</h3>
      <span>Level-2 sommelier</span>
      <p>Leads the monthly selections and the third-Wednesday pour, with an eye for small growers and the bottles worth talking about.</p>
    </article>
    <article class="somm">
      <h3 class="disp">Stacey</h3>
      <span>Level-2 taster · founder</span>
      <p>Chef and founder Stacey Weber tastes alongside April, keeping each pick honest to how we cook and eat at Amelia's.</p>
    </article>
  </div>
</div></section>

<section class="split split--rev">
  <div class="split__media"><img src="<?= e(asset('img/Cooler-1405x1536.jpg')) ?>" alt="Grab-and-go cooler and fridge of bottles at Amelia's"></div>
  <div class="split__body">
    <span class="kicker">Not ready to commit?</span>
    <h2 class="disp club__wall-h">The wine wall &amp; shop</h2>
    <p>The whole collection lives on the wall, natural, organic, and small-production bottles we actually pour and drink. Browse on your own or ask whoever's working; everyone here has a few favorites.</p>
    <p class="club__wall-note"><strong>Mix &amp; match any six bottles and take 10% off</strong>, members save 15% on every bottle, every visit.</p>
    <p class="btn-row mt-6"><a class="btn btn--solid" href="<?= e(url('/market')) ?>">Browse the wall</a></p>
  </div>
</section>
