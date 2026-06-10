<?php
/**
 * Customer account portal (screen 17, 6.6).
 *
 * @var array{id:int,email:string,name:string} $customer
 * @var array{first_name:string,last_name:string,email:string,phone:string} $profile
 * @var list<array<string,mixed>> $orders
 * @var list<array<string,mixed>> $favorites
 * @var list<array<string,mixed>> $addresses
 * @var array<string,mixed>|null $subscription
 * @var list<array<string,mixed>> $upcoming
 * @var list<array<string,mixed>> $past
 * @var array{type:string,message:string}|null $flash
 */
$firstName = trim((string) ($customer['name'] ?? '')) !== '' ? explode(' ', trim((string) $customer['name']))[0] : 'there';
?>
<header class="page-head">
  <div class="container">
    <div class="greeting-bar">
      <div>
        <p class="kicker">Your account</p>
        <h1 class="disp">Hi, <?= e($firstName) ?></h1>
      </div>
      <form method="post" action="<?= e(url('/logout')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn--sm" type="submit">Sign out</button>
      </form>
    </div>
  </div>
</header>

<section class="section">
  <div class="container">
    <?php if (!empty($flash)): ?>
      <div class="alert <?= $flash['type'] === 'error' ? 'alert--danger' : 'alert--ok' ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div class="acct-grid">
      <div class="acct-main">

        <div class="acct-panel">
          <div class="acct-panel__head">
            <h2 class="disp">Order history</h2>
            <a class="link-arrow" href="<?= e(url('/menu')) ?>">Order again</a>
          </div>
          <div class="acct-tbl-wrap">
            <table class="tbl">
              <thead><tr><th>Date</th><th>Order #</th><th>Items</th><th>Total</th><th><span class="visually-hidden">Reorder</span></th></tr></thead>
              <tbody>
                <?php if ($orders === []): ?>
                  <tr><td colspan="5" class="muted">No orders yet. <a class="ulink" href="<?= e(url('/menu')) ?>">Browse the menu</a>.</td></tr>
                <?php else: foreach ($orders as $o): ?>
                  <tr>
                    <td><?= e(fmt_date((string) $o['placed_at'], 'M j, Y')) ?></td>
                    <td>#<?= e((string) $o['id']) ?></td>
                    <td><?= e((string) ($o['items_summary'] ?? '')) ?></td>
                    <td><?= e(fmt_money((int) $o['total_cents'])) ?></td>
                    <td>
                      <form method="post" action="<?= e(url('/account/reorder/' . $o['id'])) ?>">
                        <?= csrf_field() ?>
                        <button class="reorder" type="submit">Reorder</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="acct-panel">
          <div class="acct-panel__head">
            <h2 class="disp">Saved favorites</h2>
            <a class="link-arrow" href="<?= e(url('/menu')) ?>">Browse menu</a>
          </div>
          <?php if ($favorites === []): ?>
            <p class="muted">No saved favorites yet.</p>
          <?php else: ?>
            <div class="fav-grid">
              <?php foreach ($favorites as $f): ?>
                <?php $viewBase = ($f['type'] ?? '') === 'market' ? '/market/' : '/menu/'; ?>
                <div class="fav">
                  <div class="fav__img">
                    <?php if (!empty($f['image_path'])): ?>
                      <img src="<?= e(asset('uploads/' . ltrim((string) $f['image_path'], '/'))) ?>" alt="<?= e((string) $f['name']) ?>">
                    <?php endif; ?>
                  </div>
                  <div class="fav__body">
                    <div class="fav__name"><?= e((string) $f['name']) ?></div>
                    <div class="fav__meta"><?= e(fmt_money((int) $f['price_cents'])) ?></div>
                    <?php if ((int) $f['is_86'] === 1): ?>
                      <div class="fav__flag">Currently sold out</div>
                    <?php else: ?>
                      <form class="fav__addform" method="post" action="<?= e(url('/cart/add')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= e((string) $f['product_id']) ?>">
                        <input type="hidden" name="quantity" value="1">
                        <button class="fav__add" type="submit">Add to order</button>
                      </form>
                    <?php endif; ?>
                  </div>
                  <div class="fav__actions">
                    <a class="ulink" href="<?= e(url($viewBase . $f['slug'])) ?>">View</a>
                    <form method="post" action="<?= e(url('/account')) ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="fav_remove">
                      <input type="hidden" name="favorite_id" value="<?= e((string) $f['id']) ?>">
                      <button class="btn btn--sm" type="submit" aria-label="Remove <?= e((string) $f['name']) ?> from favorites">Remove</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="acct-panel">
          <div class="acct-panel__head"><h2 class="disp">Saved addresses</h2></div>
          <?php if ($addresses === []): ?>
            <p class="muted">No saved addresses.</p>
          <?php else: ?>
            <?php foreach ($addresses as $a): ?>
              <div class="fav">
                <div>
                  <div class="fav__name"><?= e((string) ($a['label'] ?: ucfirst((string) $a['purpose']))) ?></div>
                  <div class="fav__meta"><?= e((string) $a['line1']) ?><?= $a['line2'] ? ', ' . e((string) $a['line2']) : '' ?>, <?= e((string) $a['city']) ?> <?= e((string) $a['state']) ?> <?= e((string) $a['postal']) ?></div>
                </div>
                <form method="post" action="<?= e(url('/account')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="addr_remove">
                  <input type="hidden" name="address_id" value="<?= e((string) $a['id']) ?>">
                  <button class="btn btn--sm" type="submit">Remove</button>
                </form>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <form class="mt-4" method="post" action="<?= e(url('/account')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="addr_add">
            <div class="grid grid--2">
              <div class="field"><label for="addr-label">Label</label><input class="input" type="text" id="addr-label" name="label" placeholder="Home, Office…"></div>
              <div class="field"><label for="addr-line1">Address</label><input class="input" type="text" id="addr-line1" name="line1" required></div>
              <div class="field"><label for="addr-line2">Apt / suite</label><input class="input" type="text" id="addr-line2" name="line2"></div>
              <div class="field"><label for="addr-city">City</label><input class="input" type="text" id="addr-city" name="city" required></div>
              <div class="field"><label for="addr-state">State</label><input class="input" type="text" id="addr-state" name="state" maxlength="2" required></div>
              <div class="field"><label for="addr-postal">ZIP</label><input class="input" type="text" id="addr-postal" name="postal" required></div>
            </div>
            <button class="btn" type="submit">Add address</button>
          </form>
        </div>

      </div>

      <aside class="acct-aside">

        <h2>Upcoming reservations</h2>
        <?php if ($upcoming === []): ?>
          <div class="side-card"><p class="muted">No upcoming reservations. <a class="ulink" href="<?= e(url('/reserve')) ?>">Reserve a table</a>.</p></div>
        <?php else: foreach ($upcoming as $r): ?>
          <div class="side-card">
            <h3 class="disp">Reservation</h3>
            <dl>
              <div class="meta-row"><dt>Date</dt><dd><?= e(fmt_date((string) $r['slot_start'], 'D, M j · g:i A')) ?></dd></div>
              <div class="meta-row"><dt>Party</dt><dd><?= e((string) $r['party_size']) ?> guests</dd></div>
              <?php if (!empty($r['seating'])): ?>
                <div class="meta-row"><dt>Seating</dt><dd><?= e(ucfirst((string) $r['seating'])) ?></dd></div>
              <?php endif; ?>
              <div class="meta-row"><dt>Status</dt><dd><?= e(ucfirst((string) $r['status'])) ?></dd></div>
              <?php if (!empty($r['public_token'])): ?>
                <div class="meta-row"><dt>Confirmation</dt><dd>#<?= e((string) $r['public_token']) ?></dd></div>
              <?php endif; ?>
            </dl>
            <div class="card-actions">
              <a class="btn btn--sm" href="<?= e(url('/reservation/' . $r['public_token'])) ?>">Manage</a>
              <a class="link-arrow" href="<?= e(url('/reservation/' . $r['public_token'])) ?>">Add a note</a>
            </div>
          </div>
        <?php endforeach; endif; ?>

        <?php if ($past !== []): ?>
          <h2>Past reservations</h2>
          <div class="side-card">
            <dl>
              <?php foreach ($past as $r): ?>
                <div class="meta-row"><dt><?= e(fmt_date((string) $r['slot_start'], 'M j, Y')) ?></dt><dd><?= e(ucfirst((string) $r['status'])) ?></dd></div>
              <?php endforeach; ?>
            </dl>
          </div>
        <?php endif; ?>

        <h2>Wine Club</h2>
        <div class="side-card">
          <?php if ($subscription === null): ?>
            <p class="muted">Not a member yet.</p>
            <div class="mt-2"><a class="btn btn--solid btn--block" href="<?= e(url('/wine-club')) ?>">Join the Wine Club</a></div>
          <?php else: ?>
            <div class="flex-between">
              <h3 class="disp mb-0">Membership</h3>
              <span class="badge <?= $subscription['status'] === 'active' ? 'badge--ok' : 'badge--warn' ?>"><?= e(ucfirst((string) $subscription['status'])) ?></span>
            </div>
            <dl class="mt-2">
              <div class="meta-row"><dt>Plan</dt><dd><?= e((string) $subscription['tier_name']) ?> · <?= e(fmt_money((int) $subscription['price_cents'])) ?>/<?= e((string) $subscription['interval']) ?></dd></div>
              <?php if (!empty($subscription['member_since'])): ?>
                <div class="meta-row"><dt>Member since</dt><dd><?= e(fmt_date((string) $subscription['member_since'], 'M Y')) ?></dd></div>
              <?php endif; ?>
              <?php if (!empty($subscription['next_tasting'])): ?>
                <div class="meta-row"><dt>Next tasting</dt><dd><?= e(fmt_date((string) $subscription['next_tasting'], 'M j · g:i A')) ?></dd></div>
              <?php endif; ?>
              <?php if (!empty($subscription['current_period_end'])): ?>
                <div class="meta-row"><dt>Renews</dt><dd><?= e(fmt_date((string) $subscription['current_period_end'], 'M j, Y')) ?></dd></div>
              <?php endif; ?>
            </dl>
            <div class="mt-2"><a class="btn btn--solid btn--block" href="<?= e(url('/wine-club/portal')) ?>">Manage in portal</a></div>
          <?php endif; ?>
        </div>

        <h2>Profile</h2>
        <div class="side-card">
          <form method="post" action="<?= e(url('/account')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile_update">
            <div class="grid--form">
              <div class="field">
                <label for="f-first">First name</label>
                <input class="input" id="f-first" type="text" name="first_name" value="<?= e((string) $profile['first_name']) ?>" autocomplete="given-name">
              </div>
              <div class="field">
                <label for="f-last">Last name</label>
                <input class="input" id="f-last" type="text" name="last_name" value="<?= e((string) $profile['last_name']) ?>" autocomplete="family-name">
              </div>
              <div class="field field--full">
                <label for="f-email">Email</label>
                <input class="input" id="f-email" type="email" name="email" value="<?= e((string) $profile['email']) ?>" autocomplete="email" required>
              </div>
              <div class="field field--full">
                <label for="f-phone">Phone</label>
                <input class="input" id="f-phone" type="tel" name="phone" value="<?= e((string) $profile['phone']) ?>" autocomplete="tel">
              </div>
            </div>
            <div class="form-actions mt-2">
              <button class="btn btn--solid" type="submit">Save changes</button>
            </div>
          </form>
        </div>

      </aside>
    </div>
  </div>
</section>
