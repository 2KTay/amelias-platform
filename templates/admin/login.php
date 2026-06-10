<?php /** Staff login (screen 18b). */ ?>
<h1 class="disp auth-title">Staff Sign In</h1>
<p class="muted">Back-office access for Owner · Manager · Staff.</p>

<?php if (!empty($error)): ?>
  <div class="alert alert--danger" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url('/admin/login')) ?>" class="mt-4">
  <?= csrf_field() ?>
  <div class="field">
    <label for="email">Email</label>
    <input class="input" type="email" id="email" name="email" required autocomplete="username" autofocus>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
  </div>
  <button class="btn btn--solid btn--block btn--lg" type="submit">Sign in</button>
</form>

<?php if (!empty($isDemo)): ?>
  <div class="demo-creds mt-4">
    <p class="muted">Demo credentials (staging only):</p>
    <ul>
      <li><code data-copy>owner@amelias.local</code> / <code data-copy>password</code>, Owner</li>
      <li><code data-copy>manager@amelias.local</code> / <code data-copy>password</code>, Manager</li>
      <li><code data-copy>staff@amelias.local</code> / <code data-copy>password123</code>, Staff</li>
    </ul>
  </div>
<?php endif; ?>
