<?php
/**
 * Users & roles (screen 27, 6.5), Owner ONLY.
 *
 * @var int $currentUserId
 * @var list<array<string,mixed>> $users
 * @var list<array<string,mixed>> $audit
 * @var list<string> $roles
 * @var string|null $notice
 * @var string|null $error
 */
?>
<div class="admin-head">
  <div class="flex">
    <h1 class="admin-h1 disp mb-0">Staff &amp; Access</h1>
    <span class="badge badge--warn">Owner only</span>
  </div>
</div>

<?php if (!empty($notice)): ?><div class="alert alert--ok" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert--danger" role="alert"><?= e($error) ?></div><?php endif; ?>

<div class="admin-card">
  <h2 class="disp" id="staff-caption">Staff &amp; roles</h2>
  <div class="table-wrap mt-2" role="region" aria-labelledby="staff-caption" tabindex="0">
  <table class="admin-tbl">
    <caption class="visually-hidden">Staff and roles</caption>
    <thead>
      <tr><th scope="col" class="col-primary">Name</th><th scope="col" class="col-priority-low">Email</th><th scope="col">Role</th><th scope="col" class="col-date col-priority-medium">Last active</th><th scope="col">Status</th><th scope="col" class="col-actions">Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): $uid = (int) $u['id']; $isSelf = $uid === $currentUserId; ?>
        <tr>
          <td data-label="Name" class="col-primary"><?= e((string) $u['name']) ?></td>
          <td data-label="Email" class="muted col-priority-low"><?= e((string) $u['email']) ?></td>
          <td data-label="Role"><span class="badge<?= $u['role'] === 'owner' ? ' badge--new' : '' ?>"><?= e(ucfirst((string) $u['role'])) ?></span></td>
          <td data-label="Last active" class="col-date col-priority-medium"><?= $u['last_login_at'] !== null ? e(fmt_date((string) $u['last_login_at'], 'M j, g:i A')) : '–' ?></td>
          <td data-label="Status">
            <?php if (!$u['is_active']): ?><span class="badge badge--danger">Deactivated</span>
            <?php elseif ($u['must_reset_password']): ?><span class="badge badge--warn">Invited</span>
            <?php else: ?><span class="badge badge--ok">Active</span><?php endif; ?>
          </td>
          <td data-label="Actions" class="col-actions">
            <?php if ($isSelf): ?>
              <span class="muted">Owner (you)</span>
            <?php else: ?>
              <span class="adj">
                <form method="post" action="<?= e(url('/admin/users')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="role">
                  <input type="hidden" name="user_id" value="<?= e((string) $uid) ?>">
                  <label class="visually-hidden" for="role-<?= e((string) $uid) ?>">Role for <?= e((string) $u['name']) ?></label>
                  <select class="select role-select" id="role-<?= e((string) $uid) ?>" name="role" onchange="this.form.submit()">
                    <?php foreach ($roles as $r): ?>
                      <option value="<?= e($r) ?>"<?= $u['role'] === $r ? ' selected' : '' ?>><?= e(ucfirst($r)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
                <form method="post" action="<?= e(url('/admin/users')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="user_id" value="<?= e((string) $uid) ?>">
                  <?php if ($u['is_active']): ?>
                    <input type="hidden" name="action" value="deactivate">
                    <button class="btn btn--sm" type="submit">Deactivate</button>
                  <?php else: ?>
                    <input type="hidden" name="action" value="reactivate">
                    <button class="btn btn--sm" type="submit">Reactivate</button>
                  <?php endif; ?>
                </form>
              </span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="admin-card">
  <h2 class="disp">Invite user</h2>
  <form method="post" action="<?= e(url('/admin/users')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="invite">
    <div class="field"><label for="inv-name">Name</label><input class="input" type="text" id="inv-name" name="name" autocomplete="off"></div>
    <div class="field"><label for="inv-email">Email <span class="req">*</span></label><input class="input" type="email" id="inv-email" name="email" required autocomplete="off"></div>
    <div class="field">
      <label for="inv-role">Role</label>
      <select class="select" id="inv-role" name="role">
        <?php foreach ($roles as $r): ?><option value="<?= e($r) ?>"<?= $r === 'staff' ? ' selected' : '' ?>><?= e(ucfirst($r)) ?></option><?php endforeach; ?>
      </select>
    </div>
    <p class="muted">The invited user is created with a forced password reset and must set their own password before signing in.</p>
    <div class="sticky-actions"><button class="btn btn--solid" type="submit">Send invite</button></div>
  </form>
</div>

<div class="admin-card">
  <h2 class="disp">Role permissions reference</h2>
  <div class="perms mt-2">
    <div class="perm">
      <h3 class="disp"><span class="badge badge--new">Owner</span></h3>
      <p class="muted">Full access</p>
      <ul><li>All dashboards &amp; operations</li><li>Reports &amp; financials</li><li>User &amp; role management</li><li>Menu, pricing &amp; inventory</li><li>Stripe &amp; payouts</li></ul>
    </div>
    <div class="perm">
      <h3 class="disp"><span class="badge">Manager</span></h3>
      <p class="muted">Operations</p>
      <ul><li>Orders &amp; reservations</li><li>Menu, pricing &amp; inventory</li><li>Customers &amp; feedback</li><li class="no">No Users management</li><li class="no">No financial exports</li></ul>
    </div>
    <div class="perm">
      <h3 class="disp"><span class="badge">Staff</span></h3>
      <p class="muted">Floor &amp; service</p>
      <ul><li>Orders &amp; reservations</li><li>Feedback (view)</li><li class="no">Cannot see Reports</li><li class="no">Cannot see Users</li><li class="no">Cannot see financials</li></ul>
    </div>
  </div>
</div>

<div class="admin-card">
  <h2 class="disp" id="audit-caption">Audit log</h2>
  <div class="table-wrap mt-2" role="region" aria-labelledby="audit-caption" tabindex="0">
  <table class="admin-tbl">
    <caption class="visually-hidden">Audit log</caption>
    <thead><tr><th scope="col" class="col-date">Time</th><th scope="col">User</th><th scope="col">Action</th></tr></thead>
    <tbody>
      <?php if ($audit === []): ?><tr><td data-label="Time" class="muted" colspan="3">No audit entries yet.</td></tr>
      <?php else: foreach ($audit as $a): ?>
        <tr>
          <td data-label="Time" class="col-date"><?= e(fmt_date((string) $a['created_at'], 'M j · H:i')) ?></td>
          <td data-label="User"><?= e((string) ($a['actor_name'] ?? 'System')) ?></td>
          <td data-label="Action"><?= e((string) $a['action']) ?><?= $a['entity_id'] !== null ? ' · ' . e((string) $a['entity_type']) . ' #' . e((string) $a['entity_id']) : '' ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>
