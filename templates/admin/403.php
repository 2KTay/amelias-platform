<?php /** Authenticated but under-privileged. */ ?>
<div class="admin-card">
  <h1 class="admin-h1 disp">Not allowed</h1>
  <p class="muted">Your role doesn't have access to this screen. If you think this is a mistake, ask the owner to adjust your permissions.</p>
  <p class="btn-row mt-4"><a class="btn btn--solid" href="<?= e(url('/admin')) ?>">Back to dashboard</a></p>
</div>
