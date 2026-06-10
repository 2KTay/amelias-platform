<section class="section">
  <div class="container container--narrow prose">
    <p class="kicker">Error 405</p>
    <h1 class="disp">That action isn't allowed here.</h1>
    <p class="lede">The request method isn't supported for <code><?= e($path ?? '') ?></code>.</p>
    <p class="btn-row"><a class="btn btn--solid btn--lg" href="<?= e(url('/')) ?>">Back to home</a></p>
  </div>
</section>
