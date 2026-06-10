<?php
/**
 * Admin layout, templated internal-tool shell (data-theme="admin": sans
 * display, navy chrome). No analytics ever loads here. $nav holds the current
 * user's role-filtered nav (built by the admin controller); $content the body.
 */
$role = $_SESSION['user']['role'] ?? null;
$navItems = [
    ['/admin', 'Dashboard', ['owner', 'manager', 'staff']],
    ['/admin/orders', 'Orders', ['owner', 'manager', 'staff']],
    ['/admin/reservations', 'Reservations', ['owner', 'manager', 'staff']],
    ['/admin/menu', 'Menu', ['owner', 'manager']],
    ['/admin/inventory', 'Inventory', ['owner', 'manager']],
    ['/admin/feedback', 'Feedback', ['owner', 'manager']],
    ['/admin/content', 'Content', ['owner', 'manager']],
    ['/admin/customers', 'Customers', ['owner', 'manager']],
    ['/admin/reports', 'Reports', ['owner']],
    ['/admin/users', 'Staff & Access', ['owner']],
    ['/admin/settings', 'Settings', ['owner']],
];

// Resolve the active nav path once: drop the query string, then strip the
// mount base path so it lines up with the href values above. A nav item is
// active when the path equals its href OR sits under it (href . '/…'); the
// Dashboard ('/admin') is special-cased to match ONLY exactly /admin so it
// doesn't light up on every /admin/* page.
$uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/admin');
$path = explode('?', $uri, 2)[0];
$base = base_path();
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
if ($path === '') {
    $path = '/';
}
$isActive = static function (string $href) use ($path): bool {
    if ($href === '/admin') {
        return $path === '/admin';
    }
    return $path === $href || str_starts_with($path, $href . '/');
};
?><!doctype html>
<html lang="en" data-theme="admin">
<head>
<?php $title = ($title ?? 'Admin'); require __DIR__ . '/../partials/head.php'; ?>
</head>
<body class="admin">
  <div class="admin-shell">
    <aside class="admin-nav" aria-label="Admin">
      <a class="admin-nav__brand" href="<?= e(url('/admin')) ?>"><?= e(config('name')) ?></a>
      <nav>
        <?php foreach ($navItems as [$href, $label, $roles]): ?>
          <?php if ($role !== null && in_array($role, $roles, true)): ?>
            <a href="<?= e(url($href)) ?>"<?= $isActive($href) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <form class="admin-nav__foot" method="post" action="<?= e(url('/admin/logout')) ?>">
        <?= csrf_field() ?>
        <span class="muted"><?= e($_SESSION['user']['name'] ?? '') ?> · <?= e($role ?? '') ?></span>
        <button class="btn btn--sm" type="submit">Sign out</button>
      </form>
    </aside>
    <main class="admin-main" id="main">
      <?= $content ?? '' ?>
    </main>
  </div>
  <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</body>
</html>
