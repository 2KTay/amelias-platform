<section class="section">
  <div class="container container--narrow prose">
    <p class="kicker">Error 404</p>
    <h1 class="disp">This page wandered off the menu.</h1>
    <p class="lede">We couldn't find <code><?= e($path ?? '') ?></code>. It may have moved, or the link was mistyped.</p>
    <p class="btn-row">
      <a class="btn btn--solid btn--lg" href="<?= e(url('/')) ?>">Back to home</a>
      <a class="btn btn--lg" href="<?= e(url('/menu')) ?>">View the menu</a>
    </p>
  </div>
</section>
