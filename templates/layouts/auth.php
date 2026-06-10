<?php /** Minimal centered layout for sign-in screens (no nav/footer chrome). */ ?>
<!doctype html>
<html lang="en">
<head>
<?php require __DIR__ . '/../partials/head.php'; ?>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="auth-body">
  <main class="auth-wrap">
    <a class="auth-logo" href="<?= e(url('/')) ?>">
      <img src="<?= e(asset('brand/AmeliasbyEatLogo-Final-Color.svg')) ?>" alt="<?= e(config('name')) ?>">
    </a>
    <div class="auth-card">
      <?= $content ?? '' ?>
    </div>
    <p class="auth-foot"><a href="<?= e(url('/')) ?>">← Back to site</a></p>
  </main>
  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
