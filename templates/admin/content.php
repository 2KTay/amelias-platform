<?php
/**
 * Content admin (owner/manager), edit keyed content blocks + manage CMS pages.
 * Two-column layout: editor surfaces on the left, a Pages quick-list on the right.
 * Expects: $blocks (key=>['label','value']), $pages (rows), $editPage (?row),
 * $saved (bool), $error (?string), $foodProducts (rows id/name),
 * $featuredIds (list<int> currently selected, ordered).
 */
$editPage     = $editPage ?? null;
$foodProducts = $foodProducts ?? [];
$featuredIds  = $featuredIds ?? [];
?>
<div class="admin-head">
  <div>
    <h1 class="admin-h1 disp mb-0">Content</h1>
    <p class="muted content-sub">Edit site copy and pages without touching code. Changes are sanitized before they appear on the public site.</p>
  </div>
</div>

<?php if (!empty($saved)): ?>
  <div class="alert alert--ok" role="status">Saved.</div>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <div class="alert alert--danger" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<div class="content-cols">
  <div class="content-main">
    <!-- CONTENT BLOCKS, status table + expandable inline editors -->
    <form method="post" action="<?= e(url('/admin/content')) ?>" id="blocks-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="blocks">
      <div class="admin-card">
        <div class="admin-head"><h2 class="disp mb-0">Content blocks</h2><span class="badge">Site copy</span></div>
        <p class="muted mb-2">Short bits of text reused across pages. Allowed formatting: links, bold, italic, lists, sub-headings. Editing any block and saving updates the public site.</p>

        <table class="admin-tbl content-blocks-tbl">
          <thead>
            <tr>
              <th scope="col">Block</th>
              <th scope="col">Status</th>
              <th scope="col">Last edited</th>
              <th scope="col" class="content-col-actions"><span class="visually-hidden">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($blocks as $key => $b): $editorId = 'block-editor-' . md5($key); ?>
              <tr>
                <th scope="row" class="content-block-name" data-label="Block">
                  <?= e($b['label']) ?>
                  <span class="muted content-block-key">(<?= e($key) ?>)</span>
                </th>
                <td data-label="Status" class="content-status-cell">
                  <?php if (!empty($b['published'])): ?>
                    <span class="badge badge--ok">Published</span>
                  <?php else: ?>
                    <span class="badge badge--warn">Empty</span>
                  <?php endif; ?>
                </td>
                <td data-label="Last edited" class="muted content-block-date">
                  <?= $b['updated_at'] ? e(fmt_date((string) $b['updated_at'], 'M j, Y')) : '–' ?>
                </td>
                <td data-label="Actions" class="content-col-actions">
                  <button type="button" class="btn btn--sm content-edit-toggle"
                          aria-expanded="false" aria-controls="<?= e($editorId) ?>"
                          data-target="<?= e($editorId) ?>">Edit</button>
                </td>
              </tr>
              <tr class="content-editor-row" id="<?= e($editorId) ?>" hidden>
                <td colspan="4" class="content-editor-cell">
                  <div class="field">
                    <label for="input-<?= e($key) ?>"><?= e($b['label']) ?> <span class="muted">(<?= e($key) ?>)</span></label>
                    <div class="toolbar" role="toolbar" aria-label="Text formatting" data-toolbar-for="input-<?= e($key) ?>">
                      <button type="button" class="btn btn--ghost btn--sm" data-fmt="bold" aria-label="Bold"><strong aria-hidden="true">B</strong></button>
                      <button type="button" class="btn btn--ghost btn--sm" data-fmt="italic" aria-label="Italic"><em aria-hidden="true">I</em></button>
                      <button type="button" class="btn btn--ghost btn--sm" data-fmt="link" aria-label="Insert link">Link</button>
                      <button type="button" class="btn btn--ghost btn--sm" data-fmt="list" aria-label="Bulleted list">List</button>
                    </div>
                    <textarea class="textarea editor-area" id="input-<?= e($key) ?>" name="<?= e($key) ?>" rows="4"
                              data-charcount="count-<?= e($key) ?>"><?= e($b['value']) ?></textarea>
                    <div id="count-<?= e($key) ?>" class="charcount" aria-live="polite" aria-atomic="true"><?= (int) mb_strlen($b['value']) ?> characters</div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <p class="muted content-block-note">Content blocks don't have a separate draft state, saving publishes the change immediately. Use Pages below for draft/publish workflow.</p>
        <div class="sticky-actions"><button class="btn btn--solid" type="submit">Save copy blocks</button></div>
      </div>
    </form>

    <!-- HOME · FROM THE KITCHEN (featured products picker) -->
    <form method="post" action="<?= e(url('/admin/content')) ?>" class="admin-card">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="featured">
      <div class="admin-head"><h2 class="disp mb-0">Home &middot; From the kitchen</h2><span class="badge">Featured items</span></div>
      <p class="muted mb-2">Choose up to three products to feature on the home page. The first is shown as the large card; the next two appear beside it. Leave a slot on &ldquo;none&rdquo; to skip it. If all three are &ldquo;none&rdquo;, the whole section is hidden.</p>

      <?php if ($foodProducts === []): ?>
        <p class="muted">No active food products yet. Add products first to feature them here.</p>
      <?php else: ?>
        <div class="featured-picker">
          <?php for ($slot = 1; $slot <= 3; $slot++): ?>
            <?php $current = $featuredIds[$slot - 1] ?? 0; ?>
            <div class="field">
              <label for="featured-<?= (int) $slot ?>">Slot <?= (int) $slot ?><?= $slot === 1 ? ' (large card)' : '' ?></label>
              <select class="select" id="featured-<?= (int) $slot ?>" name="product_id_<?= (int) $slot ?>">
                <option value="">, none,</option>
                <?php foreach ($foodProducts as $product): ?>
                  <option value="<?= (int) $product['id'] ?>"<?= (int) $product['id'] === $current ? ' selected' : '' ?>><?= e((string) $product['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endfor; ?>
        </div>
        <div class="sticky-actions"><button class="btn btn--solid" type="submit">Save featured items</button></div>
      <?php endif; ?>
    </form>

    <!-- PAGES LIST -->
    <div class="admin-card">
      <h2 class="disp">Pages &amp; posts</h2>
      <?php if ($pages === []): ?>
        <p class="muted">No pages yet. Create one below.</p>
      <?php else: ?>
        <table class="admin-tbl mt-2">
          <thead><tr><th scope="col">Title</th><th scope="col">Slug</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Last edited</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
          <tbody>
            <?php foreach ($pages as $p): ?>
              <tr>
                <td data-label="Title"><?= e($p['title']) ?></td>
                <td data-label="Slug" class="muted"><?= e($p['slug']) ?></td>
                <td data-label="Type"><?= e($p['type']) ?></td>
                <td data-label="Status">
                  <?php if ($p['status'] === 'published'): ?><span class="badge badge--ok">Published</span>
                  <?php else: ?><span class="badge badge--warn">Draft</span><?php endif; ?>
                </td>
                <td data-label="Last edited" class="muted"><?= e(fmt_date($p['updated_at'], 'M j, Y')) ?></td>
                <td data-label="Actions"><a class="btn btn--sm" href="<?= e(url('/admin/content?page=' . (int) $p['id'])) ?>">Edit</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- PAGE EDITOR -->
    <form method="post" action="<?= e(url('/admin/content')) ?>" class="admin-card" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="page">
      <input type="hidden" name="id" value="<?= e((string) ($editPage['id'] ?? '')) ?>">
      <div class="admin-head"><h2 class="disp mb-0"><?= $editPage ? 'Editing: ' . e((string) $editPage['title']) : 'New page' ?></h2><span class="badge">Page</span></div>

      <div class="field">
        <label for="page-title">Title <span class="req">*</span></label>
        <input class="input" type="text" id="page-title" name="title" value="<?= e((string) ($editPage['title'] ?? '')) ?>" required>
      </div>
      <div class="field">
        <label for="page-slug">Slug <span class="req">*</span></label>
        <input class="input" type="text" id="page-slug" name="slug" value="<?= e((string) ($editPage['slug'] ?? '')) ?>" placeholder="e.g. privacy" required>
      </div>
      <div class="field">
        <label for="page-type">Type</label>
        <select class="select" id="page-type" name="type">
          <option value="page"<?= ($editPage['type'] ?? 'page') === 'page' ? ' selected' : '' ?>>Page</option>
          <option value="post"<?= ($editPage['type'] ?? '') === 'post' ? ' selected' : '' ?>>Blog post</option>
        </select>
      </div>
      <div class="field">
        <label for="page-excerpt">Excerpt</label>
        <textarea class="textarea" id="page-excerpt" name="excerpt" rows="2"><?= e((string) ($editPage['excerpt'] ?? '')) ?></textarea>
      </div>
      <div class="field">
        <?php $currentHero = (string) ($editPage['hero_image'] ?? ''); ?>
        <label for="page-hero">Hero image</label>
        <?php if ($currentHero !== ''): ?>
          <p class="content-hero-current">
            <img class="content-hero-thumb" src="<?= e(asset('uploads/' . $currentHero)) ?>" alt="Current hero image" width="160">
          </p>
          <input type="hidden" name="hero_image_current" value="<?= e($currentHero) ?>">
          <label class="content-hero-remove">
            <input type="checkbox" name="hero_image_remove" value="1"> Remove current image
          </label>
        <?php endif; ?>
        <input class="input" type="file" id="page-hero" name="hero_image" accept="image/jpeg,image/png,image/webp,image/gif">
        <p class="field__hint">Upload a JPEG, PNG, WebP or GIF (max 10 MB). Leave empty to keep the current image.</p>
      </div>
      <div class="field">
        <label for="page-body">Body</label>
        <textarea class="textarea editor-area" id="page-body" name="body_html" rows="10"><?= e((string) ($editPage['body_html'] ?? '')) ?></textarea>
        <p class="field__hint">Allowed: paragraphs, links, bold/italic, lists, h2/h3, blockquote. Other tags are stripped on render.</p>
      </div>

      <div class="content-seo">
        <h3 class="disp">Page SEO</h3>
        <div class="field">
          <label for="page-seo-title">SEO title</label>
          <input class="input" type="text" id="page-seo-title" name="seo_title" maxlength="60" aria-describedby="page-seo-title-count" data-charcount="page-seo-title-count" data-charmax="60" value="<?= e((string) ($editPage['seo_title'] ?? '')) ?>">
          <div id="page-seo-title-count" class="charcount" aria-live="polite" aria-atomic="true"><?= 60 - (int) mb_strlen((string) ($editPage['seo_title'] ?? '')) ?> characters remaining</div>
        </div>
        <div class="field">
          <label for="page-seo-desc">Meta description</label>
          <textarea class="textarea" id="page-seo-desc" name="seo_description" rows="3" maxlength="160" aria-describedby="page-seo-desc-count" data-charcount="page-seo-desc-count" data-charmax="160"><?= e((string) ($editPage['seo_description'] ?? '')) ?></textarea>
          <div id="page-seo-desc-count" class="charcount" aria-live="polite" aria-atomic="true"><?= 160 - (int) mb_strlen((string) ($editPage['seo_description'] ?? '')) ?> characters remaining</div>
        </div>
        <div class="field">
          <label for="page-status">Status</label>
          <select class="select" id="page-status" name="status">
            <option value="draft"<?= ($editPage['status'] ?? 'draft') === 'draft' ? ' selected' : '' ?>>Draft</option>
            <option value="published"<?= ($editPage['status'] ?? '') === 'published' ? ' selected' : '' ?>>Published</option>
          </select>
        </div>
      </div>

      <div class="sticky-actions">
        <?php if ($editPage): ?><a class="btn" href="<?= e(url('/admin/content')) ?>">Cancel</a><?php endif; ?>
        <button class="btn btn--solid" type="submit"><?= $editPage ? 'Save page' : 'Create page' ?></button>
      </div>
    </form>
  </div>

  <!-- PAGES SIDEBAR -->
  <aside class="content-side">
    <div class="admin-card mb-0">
      <h2 class="disp">Pages</h2>
      <?php if ($pages === []): ?>
        <p class="muted">No pages yet.</p>
      <?php else: ?>
        <div class="content-pagelist mt-2">
          <?php foreach ($pages as $p): ?>
            <a href="<?= e(url('/admin/content?page=' . (int) $p['id'])) ?>"><?= e($p['title']) ?> <span class="muted">Edit</span></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </aside>
</div>

<script>
(function () {
  'use strict';

  // --- Expand/collapse inline block editors -------------------------------
  document.querySelectorAll('.content-edit-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var row = document.getElementById(btn.getAttribute('data-target'));
      if (!row) { return; }
      var open = row.hasAttribute('hidden');
      if (open) { row.removeAttribute('hidden'); } else { row.setAttribute('hidden', ''); }
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.textContent = open ? 'Close' : 'Edit';
    });
  });

  // --- Formatting toolbar, wraps the current selection -------------------
  function wrap(area, before, after, placeholder) {
    var start = area.selectionStart, end = area.selectionEnd;
    var sel = area.value.slice(start, end) || placeholder || '';
    var inserted = before + sel + after;
    area.value = area.value.slice(0, start) + inserted + area.value.slice(end);
    area.focus();
    area.selectionStart = start + before.length;
    area.selectionEnd = start + before.length + sel.length;
    area.dispatchEvent(new Event('input', { bubbles: true }));
  }

  document.querySelectorAll('.toolbar[data-toolbar-for]').forEach(function (bar) {
    var area = document.getElementById(bar.getAttribute('data-toolbar-for'));
    if (!area) { return; }
    bar.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-fmt]');
      if (!b) { return; }
      ev.preventDefault();
      switch (b.getAttribute('data-fmt')) {
        case 'bold':   wrap(area, '<strong>', '</strong>', 'bold text'); break;
        case 'italic': wrap(area, '<em>', '</em>', 'italic text'); break;
        case 'link':   wrap(area, '<a href="https://">', '</a>', 'link text'); break;
        case 'list':
          var s = area.selectionStart, e = area.selectionEnd;
          var lines = (area.value.slice(s, e) || 'List item').split('\n');
          var html = '<ul>\n' + lines.map(function (l) { return '  <li>' + l + '</li>'; }).join('\n') + '\n</ul>';
          area.value = area.value.slice(0, s) + html + area.value.slice(e);
          area.focus();
          area.dispatchEvent(new Event('input', { bubbles: true }));
          break;
      }
    });
  });

  // --- Live character counts ---------------------------------------------
  document.querySelectorAll('[data-charcount]').forEach(function (field) {
    var out = document.getElementById(field.getAttribute('data-charcount'));
    if (!out) { return; }
    var max = parseInt(field.getAttribute('data-charmax') || '', 10);
    var update = function () {
      var n = field.value.length;
      out.textContent = isNaN(max)
        ? n + ' characters'
        : Math.max(0, max - n) + ' characters remaining';
    };
    field.addEventListener('input', update);
    update();
  });
})();
</script>
