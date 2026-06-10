<?php
/**
 * Customer auth (screen 18), faithful port of mockups/auth.html onto the public
 * layout. Two-column: brand image + warm welcome | sign-in / create-account card.
 *
 * The mockup toggles sign-in/create with client JS; production renders the active
 * pane server-side via $mode (login | register | reset) so it works without JS and
 * keeps real form actions + CSRF. "Continue as guest" and the reset flow are
 * preserved.
 *
 * @var string $mode  login | register | reset
 * @var string|null $error
 * @var bool|null $sent
 */
$mode = $mode ?? 'login';
$isRegister = $mode === 'register';
$isReset    = $mode === 'reset';

$heading = $isRegister ? 'Create account' : ($isReset ? 'Reset your password' : 'Sign in');
$intro   = $isRegister
    ? "Join Amelia's to save favorites, reorder fast, and keep it all together."
    : ($isReset
        ? "Enter your email and we'll send a link to set a new password."
        : 'Pick up where you left off, orders, reservations, and more.');
?>
<main class="auth">
  <!-- LEFT: brand image + warm welcome -->
  <div class="auth__media">
    <img src="<?= e(asset('img/5I8A7492-1024x1536.jpeg')) ?>" alt="Amelia's dining room, slatted-wood wall, woven-leather chairs and warm afternoon light">
    <div class="auth__welcome">
      <span class="kicker">Welcome to the table</span>
      <h2 class="disp">Good to see you again</h2>
      <p>Save your favorites, reorder in a tap, track pickup, and keep your reservations and Wine Club all in one place.</p>
    </div>
  </div>

  <!-- RIGHT: sign in / create account card -->
  <div class="auth__panel">
    <div class="auth__card">
      <span class="kicker">Your account</span>
      <h1 class="disp"><?= e($heading) ?></h1>
      <p class="auth__intro"><?= e($intro) ?></p>

      <?php if (!empty($error)): ?>
        <div class="alert alert--danger" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <?php if ($isReset): ?>
        <?php if (!empty($sent)): ?>
          <div class="alert alert--ok" role="status">If an account exists for that email, we've sent reset instructions.</div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/reset-password')) ?>" novalidate>
          <?= csrf_field() ?>
          <div class="field">
            <label for="email">Email</label>
            <input class="input" type="email" id="email" name="email" autocomplete="email" placeholder="you@example.com" required>
          </div>
          <button class="btn btn--solid btn--lg btn--block" type="submit">Send reset link</button>
        </form>
        <p class="auth__alt">Remembered it? <a class="ulink" href="<?= e(url('/login')) ?>">Back to sign in</a></p>

      <?php else: ?>
        <!-- TABS (server-rendered active state; each links to its own page) -->
        <div class="tabs" role="tablist" aria-label="Sign in or create an account">
          <a class="tab" id="tab-signin" role="tab" aria-selected="<?= $isRegister ? 'false' : 'true' ?>" href="<?= e(url('/login')) ?>">Sign in</a>
          <a class="tab" id="tab-create" role="tab" aria-selected="<?= $isRegister ? 'true' : 'false' ?>" href="<?= e(url('/register')) ?>">Create account</a>
        </div>

        <?php if ($isRegister): ?>
          <!-- CREATE ACCOUNT -->
          <form class="pane" id="pane-create" role="tabpanel" aria-labelledby="tab-create" method="post" action="<?= e(url('/register')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="field">
              <label for="first_name">First name</label>
              <input class="input" type="text" id="first_name" name="first_name" autocomplete="given-name" placeholder="Amelia">
            </div>
            <div class="field">
              <label for="last_name">Last name</label>
              <input class="input" type="text" id="last_name" name="last_name" autocomplete="family-name">
            </div>
            <div class="field">
              <label for="email">Email <span class="req">*</span></label>
              <input class="input" type="email" id="email" name="email" autocomplete="email" placeholder="you@example.com" required>
            </div>
            <div class="field">
              <label for="password">Create a password <span class="req">*</span></label>
              <input class="input" type="password" id="password" name="password" autocomplete="new-password" placeholder="At least 8 characters" required minlength="8">
              <p class="field__hint">8+ characters. Use a mix of letters and numbers.</p>
            </div>
            <button class="btn btn--solid btn--lg btn--block" type="submit">Create account</button>
            <p class="fine">By creating an account you agree to our <a href="<?= e(url('/terms')) ?>">Terms</a> and <a href="<?= e(url('/privacy')) ?>">Privacy Policy</a>.</p>
          </form>
        <?php else: ?>
          <!-- SIGN IN -->
          <form class="pane" id="pane-signin" role="tabpanel" aria-labelledby="tab-signin" method="post" action="<?= e(url('/login')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="field">
              <label for="email">Email</label>
              <input class="input" type="email" id="email" name="email" autocomplete="username" placeholder="you@example.com" required>
            </div>
            <div class="field">
              <div class="label-row">
                <label for="password">Password</label>
                <a class="forgot" href="<?= e(url('/reset-password')) ?>">Forgot password?</a>
              </div>
              <input class="input" type="password" id="password" name="password" autocomplete="current-password" placeholder="Your password" required>
            </div>
            <button class="btn btn--solid btn--lg btn--block" type="submit">Continue</button>
          </form>
        <?php endif; ?>

        <!-- GUEST -->
        <div class="divider">or</div>
        <a class="btn btn--lg btn--block" href="<?= e(url('/menu')) ?>">Continue as guest</a>

        <p class="auth__alt">
          <?php if ($isRegister): ?>
            Already have an account? <a class="ulink" href="<?= e(url('/login')) ?>">Sign in</a>
          <?php else: ?>
            New here? <a class="ulink" href="<?= e(url('/register')) ?>">Create an account</a>
          <?php endif; ?>
        </p>
      <?php endif; ?>
    </div>
  </div>
</main>
