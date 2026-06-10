<?php
/**
 * Single blog post, body_html is sanitized through the CMS allowlist before
 * rendering. Expects: $post (published page row, type='post').
 */
$post = $post ?? [];
?>
<article>
  <?php if (!empty($post['hero_image'])): ?>
    <section class="page-hero">
      <img src="<?= e(asset('uploads/' . $post['hero_image'])) ?>" alt="<?= e($post['title'] ?? '') ?>" fetchpriority="high">
      <div class="page-hero__in">
        <h1 class="disp"><?= e($post['title'] ?? '') ?></h1>
        <?php if (!empty($post['published_at'])): ?><p><?= e(fmt_date($post['published_at'], 'F j, Y')) ?></p><?php endif; ?>
      </div>
    </section>
  <?php else: ?>
    <section class="page-head"><div class="container">
      <h1 class="disp"><?= e($post['title'] ?? '') ?></h1>
      <?php if (!empty($post['published_at'])): ?><p class="muted"><?= e(fmt_date($post['published_at'], 'F j, Y')) ?></p><?php endif; ?>
    </div></section>
  <?php endif; ?>

  <section class="section"><div class="container container--narrow prose">
    <?= \Amelias\Services\Content::sanitize((string) ($post['body_html'] ?? '')) ?>
    <p class="mt-6"><a class="link-arrow" href="<?= e(url('/blog')) ?>">Back to the Journal</a></p>
  </div></section>
</article>
