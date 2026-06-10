<?php
/**
 * QR feedback (screen 16). The ?table= code is reflected ONLY through e()
 * (XSS-safe). The Google review link is shown to EVERYONE, gating it by rating
 * would violate platform policy. A low score also opens a staff alert.
 */
$table     = (string) ($table ?? '');
$reviewUrl = (string) ($reviewUrl ?? '');
$submitted = (bool) ($submitted ?? false);
$error     = $error ?? null;
?>
<header class="page-head">
  <div class="container fb">
    <span class="kicker">Thanks for eating with us</span>
    <h1 class="disp"><?= $submitted ? 'Thank you, truly.' : 'How was your visit?' ?></h1>
    <?php if (!$submitted): ?>
      <p>It takes about thirty seconds, and it helps Stacey and the team make the next plate even better. Tell us how we did.</p>
      <?php if ($table !== ''): ?>
        <span class="fb__table">Table <?= e($table) ?></span>
      <?php endif; ?>
    <?php else: ?>
      <p>We've logged your feedback. If anything fell short, a manager has already been alerted so we can make it right.</p>
    <?php endif; ?>
  </div>
</header>

<section class="section">
  <div class="container fb">
    <?php if (!$submitted): ?>
      <form method="post" action="<?= e(url('/feedback')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="table" value="<?= e($table) ?>">

        <?php if ($error !== null): ?>
          <div class="alert alert--danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="field">
          <label id="rateLabel">Your rating <span class="req">*</span></label>
          <div class="stars" role="radiogroup" aria-labelledby="rateLabel">
            <?php foreach ([5, 4, 3, 2, 1] as $star): ?>
              <input type="radio" id="star<?= $star ?>" name="rating" value="<?= $star ?>">
              <label for="star<?= $star ?>" aria-label="<?= $star ?> star<?= $star === 1 ? '' : 's' ?>">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.2l2.95 5.98 6.6.96-4.78 4.66 1.13 6.57L12 17.77l-5.9 3.1 1.13-6.57L2.45 9.64l6.6-.96L12 2.2z"/></svg>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field">
          <label for="comment">Tell us more</label>
          <textarea class="textarea" id="comment" name="comment" rows="4" placeholder="What did you love? Anything we could do better?"></textarea>
          <span class="field__hint">Optional, but the details really help.</span>
        </div>

        <div class="grid grid--2 fb-grid">
          <div class="field fb-field-flush">
            <label for="name">Name</label>
            <input class="input" type="text" id="name" name="name" autocomplete="name" placeholder="First name">
            <span class="field__hint">Optional.</span>
          </div>
          <div class="field fb-field-flush">
            <label for="email">Email</label>
            <input class="input" type="email" id="email" name="email" autocomplete="email" placeholder="you@email.com">
            <span class="field__hint">Optional, only if you'd like a reply.</span>
          </div>
        </div>

        <div class="form-actions">
          <button class="btn btn--solid btn--lg" type="submit">Send feedback</button>
        </div>
      </form>
    <?php endif; ?>

    <!-- Google review, shown to EVERYONE, regardless of rating (never gated). -->
    <div class="gpanel">
      <h2 class="disp">Loved it? Tell the neighborhood.</h2>
      <p>A quick public review goes a long way for a small, family-run kitchen. We'd be grateful, whatever your rating, the link's right here.</p>
      <?php if ($reviewUrl !== ''): ?>
        <a class="btn btn--solid gbtn" href="<?= e($reviewUrl) ?>" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.2l2.95 5.98 6.6.96-4.78 4.66 1.13 6.57L12 17.77l-5.9 3.1 1.13-6.57L2.45 9.64l6.6-.96L12 2.2z"/></svg>
          Leave us a Google review
        </a>
      <?php else: ?>
        <p class="field__hint">Our Google review link is being set up, thank you for your patience.</p>
      <?php endif; ?>
    </div>

    <?php if (!$submitted): ?>
      <p class="recovery">Your table is identified automatically from the QR code you scanned, so you don't need to tell us where you sat.</p>
    <?php endif; ?>
  </div>
</section>
