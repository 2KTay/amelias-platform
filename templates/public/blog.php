<?php
/**
 * Journal (blog index), lists published posts from Content::listPosts().
 * Existing blog URLs survive cutover via these slugs (C-V7-10).
 *
 * Expects: $posts (list of page rows: slug,title,excerpt,hero_image,published_at).
 */
$posts = $posts ?? [];
?>
<section class="page-head"><div class="container">
  <span class="kicker">From Amelia's</span>
  <h1 class="disp">Journal</h1>
  <p class="lede">News from the kitchen, the market, and the people we source from.</p>
</div></section>

<section class="section"><div class="container">
  <?php if ($posts === []): ?>
    <p class="muted">No posts yet, check back soon.</p>
  <?php else: ?>
    <div class="grid grid--3">
      <?php foreach ($posts as $post): ?>
        <article class="card">
          <?php if (!empty($post['hero_image'])): ?>
            <a class="card__media" href="<?= e(url('/blog/' . $post['slug'])) ?>">
              <img src="<?= e(asset('uploads/' . $post['hero_image'])) ?>" alt="<?= e($post['title']) ?>" loading="lazy">
            </a>
          <?php endif; ?>
          <div class="card__body">
            <?php if (!empty($post['published_at'])): ?>
              <span class="cap"><?= e(fmt_date($post['published_at'], 'M j, Y')) ?></span>
            <?php endif; ?>
            <h2 class="disp card__title"><a href="<?= e(url('/blog/' . $post['slug'])) ?>"><?= e($post['title']) ?></a></h2>
            <?php if (!empty($post['excerpt'])): ?>
              <p><?= e($post['excerpt']) ?></p>
            <?php endif; ?>
            <a class="link-arrow" href="<?= e(url('/blog/' . $post['slug'])) ?>">Read more <svg width="20" height="12" viewBox="0 0 20 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 6h17M13 1l5 5-5 5"/></svg></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div></section>
