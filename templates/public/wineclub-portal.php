<?php
/**
 * Wine Club member portal (screen 14), ported from mockups/wineclub-portal.html.
 *
 * Shows membership status, next billing, and pause/cancel actions (each a
 * CSRF-guarded POST to /wine-club/portal). Mirrors status from Stripe; the
 * controller redirects anonymous visitors to /login and non-members to the
 * storefront.
 *
 * @var array<string,mixed> $customer
 * @var array<string,mixed> $subscription
 * @var array{brand:string,last4:string,exp:string}|null $payment
 * @var list<array{name:string,meta:string,desc:string,image:?string}> $bottles
 * @var list<array{date:string,bottles:string,status:string,status_class:string}> $history
 * @var bool $dtcEnabled
 * @var array|null $flash
 */
$customer = $customer ?? [];
$sub = $subscription ?? [];
$status = (string) ($sub['status'] ?? 'incomplete');
$priceCents = (int) ($sub['price_cents'] ?? 4000);
$interval = (string) ($sub['interval'] ?? 'month');
$payment = $payment ?? null;
$bottles = $bottles ?? [];
$history = $history ?? [];
$flash = $flash ?? null;

$statusLabel = match ($status) {
    'active'   => 'Active',
    'paused'   => 'Paused',
    'past_due' => 'Payment due',
    'canceled' => 'Cancelling',
    default    => 'Pending',
};
$statusClass = match ($status) {
    'active'   => 'badge--ok',
    'paused'   => 'badge--warn',
    'past_due' => 'badge--warn',
    default    => '',
};
$firstName = trim((string) ($customer['name'] ?? '')) !== '' ? explode(' ', (string) $customer['name'])[0] : 'there';
?>
<section class="page-head"><div class="container">
  <span class="kicker">Welcome back, <?= e($firstName) ?></span>
  <h1 class="disp portal__h">Your Wine Club</h1>
  <p>Two featured bottles a month, a 3rd-Wednesday tasting, waived corkage that night, and 15% off the wall anytime. Manage your membership below.</p>
</div></section>

<section class="section portal__section"><div class="container">
  <?php if ($flash !== null): ?>
    <div class="alert <?= ($flash['type'] ?? '') === 'error' ? 'alert--danger' : 'alert--ok' ?>" role="status"><?= e((string) ($flash['message'] ?? '')) ?></div>
  <?php endif; ?>

  <div class="portal">
    <div>
      <div class="panel">
        <div class="status-row">
          <h2 class="disp">Membership</h2>
          <span class="badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
        </div>
        <dl class="detail-list">
          <dt>Plan</dt>
          <dd><?= e(fmt_money($priceCents)) ?> / <?= e($interval) ?><span class="sub">Two bottles · monthly</span></dd>
          <dt>Member since</dt>
          <dd><?= e(fmt_date((string) ($sub['created_at'] ?? ''), 'F Y') ?: '–') ?></dd>
          <dt>Next billing</dt>
          <dd>
            <?php if (!empty($sub['current_period_end'])): ?>
              <?= e(fmt_date((string) $sub['current_period_end'], 'F j, Y')) ?>
              <span class="sub"><?= $status === 'canceled' ? 'Ends this date' : 'Renews automatically' ?></span>
            <?php else: ?>–<?php endif; ?>
          </dd>
          <?php if ($payment !== null): ?>
            <dt>Payment</dt>
            <dd>
              <?= e($payment['brand']) ?> &bull;&bull;&bull;&bull; <?= e($payment['last4']) ?>
              <?php if ($payment['exp'] !== ''): ?><span class="sub">Exp <?= e($payment['exp']) ?></span><?php endif; ?>
            </dd>
          <?php endif; ?>
        </dl>

        <div class="actions">
          <div class="actions__primary">
            <?php if ($payment !== null && $status !== 'canceled'): ?>
              <form method="post" action="<?= e(url('/wine-club/portal')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_payment">
                <button class="btn btn--solid" type="submit">Update payment</button>
              </form>
            <?php endif; ?>
            <?php if ($status === 'paused'): ?>
              <form method="post" action="<?= e(url('/wine-club/portal')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="resume">
                <button class="btn btn--solid" type="submit">Resume membership</button>
              </form>
            <?php elseif ($status === 'active'): ?>
              <form method="post" action="<?= e(url('/wine-club/portal')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="pause">
                <button class="btn" type="submit">Pause membership</button>
              </form>
            <?php endif; ?>
          </div>
          <?php if ($status !== 'canceled'): ?>
            <form method="post" action="<?= e(url('/wine-club/portal')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="cancel">
              <button class="danger-link" type="submit">Cancel membership</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <div class="panel">
        <h2 class="disp">Pickup &amp; shipment history</h2>
        <p class="panel__note">
          Bottles are held at the counter for two weeks, we'll text you when yours are ready.
          <?php if (!$dtcEnabled): ?> Club wine is pickup-only (21+, valid ID required); we do not ship alcohol.<?php endif; ?>
        </p>
        <?php if ($history !== []): ?>
          <div class="tbl-wrap">
            <table class="tbl">
              <thead>
                <tr><th>Date</th><th>Bottles</th><th>Status</th></tr>
              </thead>
              <tbody>
                <?php foreach ($history as $row): ?>
                  <tr>
                    <td><?= e((string) ($row['date'] ?? '')) ?></td>
                    <td><?= e((string) ($row['bottles'] ?? '')) ?></td>
                    <td><span class="badge <?= e((string) ($row['status_class'] ?? '')) ?>"><?= e((string) ($row['status'] ?? '')) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="panel__note panel__note--last">No pickups or shipments yet, your first selections will appear here once they're set aside for you.</p>
        <?php endif; ?>
      </div>
    </div>

    <div>
      <div class="panel">
        <h2 class="disp">This month's bottles</h2>
        <p class="panel__note">Chosen by our in-house somms</p>
        <?php if ($bottles !== []): ?>
          <?php foreach ($bottles as $bottle): ?>
            <div class="bottle">
              <?php if (!empty($bottle['image'])): ?>
                <div class="bottle__img"><img src="<?= e(asset('img/' . $bottle['image'])) ?>" alt="<?= e((string) ($bottle['name'] ?? '')) ?>"></div>
              <?php endif; ?>
              <div>
                <?php if (!empty($bottle['meta'])): ?><p class="bottle__meta"><?= e((string) $bottle['meta']) ?></p><?php endif; ?>
                <h3 class="bottle__name"><?= e((string) ($bottle['name'] ?? '')) ?></h3>
                <?php if (!empty($bottle['desc'])): ?><p class="bottle__desc"><?= e((string) $bottle['desc']) ?></p><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="bottle">
            <div class="bottle__img"><img src="<?= e(asset('img/WineWall-2-1365x2048.jpg')) ?>" alt="Bottles on Amelia's wine wall"></div>
            <div>
              <p class="bottle__meta">Members' selection</p>
              <h3 class="bottle__name">Your two bottles</h3>
              <p class="bottle__desc">Each month's pair is posted here and held for you at the counter. Can't make the tasting? We'll set yours aside.</p>
            </div>
          </div>
        <?php endif; ?>
        <div class="portal__cta">
          <p class="panel__note">Next tasting: third Wednesday of the month · 5–7 pm. Small bites, no corkage that night.</p>
          <a class="link-arrow" href="<?= e(url('/wine-club')) ?>">Club details &amp; perks</a>
        </div>
      </div>
    </div>
  </div>
</div></section>
