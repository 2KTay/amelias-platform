<?php
/** Public layout. $content is the rendered page body; $data passes through. */
$bodyClass = $bodyClass ?? 'has-grain';
?><!doctype html>
<html lang="en">
<head>
<?php require __DIR__ . '/../partials/head.php'; ?>
</head>
<body class="<?= e($bodyClass) ?>">
  <div class="wrap">
    <?php require __DIR__ . '/../partials/nav.php'; ?>
    <main id="main">
      <?= $content ?? '' ?>
    </main>
    <?php require __DIR__ . '/../partials/footer.php'; ?>
  </div>

  <?php require __DIR__ . '/../partials/cart-drawer.php'; ?>

  <div class="cookie-bar hidden" data-cookie-bar hidden>
    <span>We use cookies for analytics. See our <a href="<?= e(url('/privacy')) ?>">privacy policy</a>.</span>
    <span class="btn-row">
      <button class="btn btn--sm btn--ghost-light" type="button" data-cookie="decline">Decline</button>
      <button class="btn btn--sm" type="button" data-cookie="accept">Accept</button>
    </span>
  </div>

  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
