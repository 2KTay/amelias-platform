<?php
/**
 * Customers (screen 24, 6.2), Manager + Owner.
 * Search list (left) + unified profile (right). LTV is an SQL aggregate.
 *
 * @var string $q
 * @var list<array<string,mixed>> $customers
 * @var int $selectedId
 * @var array<string,mixed>|null $profile
 */
$exportUrl = url('/admin/customers?export=csv' . ($q !== '' ? '&q=' . rawurlencode($q) : ''));
?>
<div class="admin-head">
  <h1 class="admin-h1 disp">Customers</h1>
  <div class="admin-head__actions">
    <a class="btn btn--sm" href="<?= e($exportUrl) ?>">Export CSV</a>
  </div>
</div>

<form class="admin-search" method="get" action="<?= e(url('/admin/customers')) ?>" role="search">
  <label class="visually-hidden" for="cust-q">Search customers</label>
  <input class="input" type="search" id="cust-q" name="q" value="<?= e($q) ?>" placeholder="Search by name, email, or phone…" autocomplete="off">
  <button class="btn btn--sm btn--solid" type="submit">Search</button>
</form>

<div class="admin-cols">
  <div class="admin-card mb-0">
    <h2 class="disp">All customers <span class="muted">(<?= e((string) count($customers)) ?>)</span></h2>
    <div class="table-wrap mt-2" role="region" aria-labelledby="customers-caption" tabindex="0">
    <table class="admin-tbl">
      <caption id="customers-caption" class="visually-hidden">All customers</caption>
      <thead>
        <tr>
          <th scope="col" class="col-primary">Name</th><th scope="col">Email</th><th scope="col" class="col-priority-low">Phone</th>
          <th scope="col" class="col-num">Orders</th><th scope="col" class="col-num">LTV</th>
          <th scope="col" class="col-date col-priority-medium">Last visit</th><th scope="col">Club</th>
          <th scope="col" class="col-actions"><span class="visually-hidden">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($customers === []): ?>
          <tr><td data-label="Name" class="muted" colspan="8">No customers found.</td></tr>
        <?php else: foreach ($customers as $c):
          $cUrl = url('/admin/customers?id=' . $c['id'] . ($q !== '' ? '&q=' . rawurlencode($q) : '')); ?>
          <tr>
            <td data-label="Name" class="col-primary"><a class="ulink" href="<?= e($cUrl) ?>"><?= e($c['name']) ?></a></td>
            <td data-label="Email" class="muted"><?= e((string) $c['email']) ?></td>
            <td data-label="Phone" class="col-priority-low"><?= e((string) ($c['phone'] ?? '')) ?></td>
            <td data-label="Orders" class="col-num"><?= e((string) $c['order_count']) ?></td>
            <td data-label="LTV" class="col-num"><?= e(fmt_money($c['ltv_cents'])) ?></td>
            <td data-label="Last visit" class="col-date col-priority-medium"><?= $c['last_order_at'] !== null ? e(fmt_date((string) $c['last_order_at'], 'M j')) : '–' ?></td>
            <td data-label="Club"><?= $c['in_club'] ? '<span class="badge badge--new">Wine Club</span>' : '' ?></td>
            <td data-label="Actions" class="col-actions"><a class="kebab" href="<?= e($cUrl) ?>" aria-label="View <?= e($c['name']) ?>" title="View profile"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg></a></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>
  </div>

  <div class="admin-card mb-0">
    <?php if ($profile === null): ?>
      <p class="muted">Select a customer to view their profile.</p>
    <?php else: $cust = $profile['customer']; ?>
      <div class="profile__head">
        <div>
          <div class="profile__name disp"><?= e($cust['name']) ?></div>
          <?php if ($profile['subscription'] !== null): ?>
            <span class="badge badge--new">Wine Club · <?= e((string) $profile['subscription']['tier_name']) ?></span>
          <?php endif; ?>
        </div>
        <button class="btn btn--sm" type="button" disabled title="Inline editing is not available yet">Edit</button>
      </div>

      <div>
        <div class="kv"><span class="kv__k">Email</span><span class="kv__v"><?= e((string) $cust['email']) ?></span></div>
        <div class="kv"><span class="kv__k">Phone</span><span class="kv__v"><?= e((string) ($cust['phone'] ?? '–')) ?></span></div>
        <div class="kv"><span class="kv__k">Member since</span><span class="kv__v"><?= e(fmt_date((string) $cust['created_at'], 'M Y')) ?></span></div>
        <div class="kv"><span class="kv__k">Lifetime value</span><span class="kv__v price"><?= e(fmt_money($profile['ltv_cents'])) ?></span></div>
        <div class="kv"><span class="kv__k">Total orders</span><span class="kv__v"><?= e((string) $profile['order_count']) ?></span></div>
      </div>

      <div class="tabs" role="tablist" data-cust-tabs>
        <a href="#tab-orders" id="lbl-orders" role="tab" class="is-active" aria-controls="tab-orders" aria-selected="true">Order history</a>
        <a href="#tab-reservations" id="lbl-reservations" role="tab" aria-controls="tab-reservations" aria-selected="false">Reservations</a>
        <a href="#tab-feedback" id="lbl-feedback" role="tab" aria-controls="tab-feedback" aria-selected="false">Feedback</a>
        <a href="#tab-wineclub" id="lbl-wineclub" role="tab" aria-controls="tab-wineclub" aria-selected="false">Wine Club</a>
        <a href="#tab-prefs" id="lbl-prefs" role="tab" aria-controls="tab-prefs" aria-selected="false">Preferences</a>
      </div>

      <div class="tab-panel" id="tab-orders" role="tabpanel" aria-labelledby="lbl-orders">
        <h3 class="disp mt-2">Order history</h3>
        <?php if ($profile['orders'] === []): ?><p class="muted">No orders yet.</p>
        <?php else: foreach ($profile['orders'] as $o): ?>
          <div class="row-line"><span><?= e('#' . $o['id'] . ' · ' . fmt_date((string) $o['placed_at'], 'M j')) ?> <span class="badge"><?= e((string) $o['status']) ?></span></span><span class="price"><?= e(fmt_money((int) $o['total_cents'])) ?></span></div>
        <?php endforeach; endif; ?>
      </div>

      <div class="tab-panel" id="tab-reservations" role="tabpanel" aria-labelledby="lbl-reservations" hidden>
        <h3 class="disp mt-2">Reservations</h3>
        <?php if ($profile['reservations'] === []): ?><p class="muted">None.</p>
        <?php else: foreach ($profile['reservations'] as $r): ?>
          <div class="row-line"><span><?= e(fmt_date((string) $r['slot_start'], 'M j · g:i A')) ?> · Party of <?= e((string) $r['party_size']) ?></span><span class="badge"><?= e((string) $r['status']) ?></span></div>
        <?php endforeach; endif; ?>
      </div>

      <div class="tab-panel" id="tab-feedback" role="tabpanel" aria-labelledby="lbl-feedback" hidden>
        <h3 class="disp mt-2">Feedback</h3>
        <?php if ($profile['feedback'] === []): ?><p class="muted">None.</p>
        <?php else: foreach ($profile['feedback'] as $f): $rt = (int) $f['rating']; ?>
          <div class="row-line"><span class="stars" role="img" aria-label="<?= e((string) $rt) ?> out of 5"><?= str_repeat('★', $rt) ?><span class="stars__dim"><?= str_repeat('★', 5 - $rt) ?></span></span><span class="muted"><?= e(fmt_date((string) $f['created_at'], 'M j')) ?></span></div>
          <?php if (!empty($f['comment'])): ?><p class="muted"><?= e((string) $f['comment']) ?></p><?php endif; ?>
        <?php endforeach; endif; ?>
      </div>

      <div class="tab-panel" id="tab-wineclub" role="tabpanel" aria-labelledby="lbl-wineclub" hidden>
        <h3 class="disp mt-2">Wine Club</h3>
        <?php if ($profile['subscription'] !== null): $s = $profile['subscription']; ?>
          <div class="row-line"><span><?= e((string) $s['tier_name']) ?> · <?= e(fmt_money((int) $s['price_cents'])) ?>/<?= e((string) $s['interval']) ?></span><span class="badge badge--ok"><?= e((string) $s['status']) ?></span></div>
          <?php if (!empty($s['current_period_end'])): ?>
            <div class="row-line"><span>Renews</span><span><?= e(fmt_date((string) $s['current_period_end'], 'M j, Y')) ?></span></div>
          <?php endif; ?>
        <?php else: ?>
          <p class="muted">Not a Wine Club member.</p>
        <?php endif; ?>
      </div>

      <div class="tab-panel" id="tab-prefs" role="tabpanel" aria-labelledby="lbl-prefs" hidden>
        <h3 class="disp mt-2">Preferences</h3>
        <div class="kv"><span class="kv__k">Notifications</span><span class="kv__v"><?= e(ucfirst((string) ($cust['notify_channel'] ?? 'email'))) ?></span></div>
        <div class="kv"><span class="kv__k">Marketing opt-in</span><span class="kv__v"><?= !empty($cust['marketing_opt_in']) ? 'Yes' : 'No' ?></span></div>
      </div>

      <script>
      (function () {
        var strip = document.querySelector('[data-cust-tabs]');
        if (!strip) { return; }
        var tabs = strip.querySelectorAll('[role="tab"]');
        strip.addEventListener('click', function (ev) {
          var tab = ev.target.closest('[role="tab"]');
          if (!tab) { return; }
          ev.preventDefault();
          tabs.forEach(function (t) {
            var on = t === tab;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            var panel = document.getElementById(t.getAttribute('aria-controls'));
            if (panel) { panel.hidden = !on; }
          });
        });
      })();
      </script>
    <?php endif; ?>
  </div>
</div>
