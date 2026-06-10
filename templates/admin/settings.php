<?php
/**
 * Settings & Integrations (screen 28), Owner only.
 * Sectioned layout (subnav + panels) over one save form. Secrets are encrypted
 * at rest, masked here, and a blank field on save keeps the current value.
 *
 * Single global save form is preserved: every input below maps to a key the
 * controller whitelists (SECRETS/PLAIN). Per-section panels are visual only.
 *
 * @var bool $saved
 * @var array<string,array{label:string,configured:bool,masked:string}> $secrets
 * @var array<string,array{label:string,value:mixed}> $plain
 */
$plainVal = static fn (string $key): string => isset($plain[$key]) ? (string) $plain[$key]['value'] : '';

/** A secret field: type=password + per-field Reveal toggle. Empty = keep current. */
$secretField = static function (string $key, array $secrets): string {
    if (!isset($secrets[$key])) {
        return '';
    }
    $s = $secrets[$key];
    $id = 'set-' . str_replace('.', '-', $key);
    ob_start(); ?>
    <div class="field full">
      <div class="set-label-row">
        <label for="<?= e($id) ?>"><?= e($s['label']) ?></label>
        <button type="button" class="set-reveal" aria-pressed="false" data-reveal="<?= e($id) ?>"
                aria-controls="<?= e($id) ?>" aria-label="Show <?= e($s['label']) ?>">Reveal</button>
      </div>
      <input class="input set-secret" type="password" id="<?= e($id) ?>" name="<?= e($key) ?>"
             autocomplete="off" placeholder="<?= $s['configured'] ? e($s['masked']) : 'Enter value' ?>">
      <?php if ($s['configured']): ?>
        <p class="field__hint">Masked, leave blank to keep the current value.</p>
      <?php else: ?>
        <p class="field__hint set-badge-row"><span class="badge badge--warn">Not set</span></p>
      <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
};

/** A plain (non-secret) text/number/url field. */
$plainField = static function (string $key, array $plain, string $type = 'text', bool $full = false) use ($plainVal): string {
    if (!isset($plain[$key])) {
        return '';
    }
    $p = $plain[$key];
    $id = 'set-' . str_replace('.', '-', $key);
    ob_start(); ?>
    <div class="field<?= $full ? ' full' : '' ?>">
      <label for="<?= e($id) ?>"><?= e($p['label']) ?></label>
      <input class="input" type="<?= e($type) ?>" id="<?= e($id) ?>" name="<?= e($key) ?>"
             value="<?= e($plainVal($key)) ?>">
    </div>
    <?php
    return (string) ob_get_clean();
};

// Hours grid rows: [day label, key prefix].
$hourDays = [
    'Mon' => 'mon', 'Tue' => 'tue', 'Wed' => 'wed', 'Thu' => 'thu',
    'Fri' => 'fri', 'Sat' => 'sat', 'Sun' => 'sun',
];
?>
<div class="admin-head">
  <div class="flex">
    <h1 class="admin-h1 disp mb-0">Settings &amp; Integrations</h1>
    <span class="badge badge--warn">Owner only</span>
  </div>
</div>

<?php if (!empty($saved)): ?>
  <div class="alert alert--ok" role="status">Settings saved.</div>
<?php endif; ?>

<div class="set-note" role="note">
  <strong>Secrets are encrypted at rest, masked here, Owner-only, and never exposed to the public site.</strong>
  <span class="muted">Enter and rotate your own keys, leave a secret blank to keep the current value.</span>
</div>

<form method="post" action="<?= e(url('/admin/settings')) ?>">
  <?= csrf_field() ?>
  <div class="set-cols">
    <nav class="set-subnav" aria-label="Settings sections">
      <a href="#payments">Payments</a>
      <a href="#sms">SMS</a>
      <a href="#email">Email</a>
      <a href="#google">Google</a>
      <a href="#social">Social &amp; misc</a>
      <a href="#business">Business config</a>
    </nav>

    <div class="set-main">
      <!-- PAYMENTS -->
      <section class="admin-card set-panel" id="payments">
        <div class="admin-head">
          <h2 class="disp mb-0">Payments · Stripe</h2>
          <span class="badge">Secrets</span>
        </div>
        <div class="set-row">
          <?= $secretField('stripe.publishable_key', $secrets) ?>
          <?= $secretField('stripe.secret_key', $secrets) ?>
          <?= $secretField('stripe.webhook_secret', $secrets) ?>
          <div class="field full">
            <label class="set-toggle">
              <input type="checkbox" name="stripe.live_mode" value="1" <?= $plainVal('stripe.live_mode') === '1' ? 'checked' : '' ?>>
              <span class="set-toggle__track"></span>
              <span>Live mode <span class="muted">(unchecked = Test)</span></span>
            </label>
          </div>
        </div>
        <p class="btn-row mt-2">
          <button class="btn btn--sm" type="button" data-test-integration="stripe">Test Stripe connection</button>
          <span class="muted" data-test-result></span>
        </p>
      </section>

      <!-- SMS -->
      <section class="admin-card set-panel" id="sms">
        <div class="admin-head">
          <h2 class="disp mb-0">SMS · Twilio</h2>
          <span class="badge">Secrets</span>
        </div>
        <div class="set-row">
          <?= $secretField('twilio.sid', $secrets) ?>
          <?= $secretField('twilio.token', $secrets) ?>
          <?= $plainField('twilio.from', $plain, 'tel', true) ?>
        </div>
      </section>

      <!-- EMAIL -->
      <section class="admin-card set-panel" id="email">
        <div class="admin-head">
          <h2 class="disp mb-0">Email · SendGrid / SMTP</h2>
          <span class="badge">Secrets</span>
        </div>
        <div class="set-row">
          <?= $secretField('mail.sendgrid_key', $secrets) ?>
          <?= $plainField('mail.from', $plain, 'email', true) ?>
        </div>
      </section>

      <!-- GOOGLE -->
      <section class="admin-card set-panel" id="google">
        <div class="admin-head">
          <h2 class="disp mb-0">Google</h2>
          <span class="badge">Secrets</span>
        </div>
        <div class="set-row">
          <?= $secretField('google.places_key', $secrets) ?>
          <?= $plainField('google.ga4_id', $plain, 'text') ?>
          <?= $plainField('feedback.review_url', $plain, 'url', true) ?>
        </div>
      </section>

      <!-- SOCIAL & MISC -->
      <section class="admin-card set-panel" id="social">
        <div class="admin-head">
          <h2 class="disp mb-0">Social &amp; misc</h2>
          <span class="badge">Secrets</span>
        </div>
        <div class="set-row">
          <?= $secretField('recaptcha.secret', $secrets) ?>
          <?= $plainField('business.phone', $plain, 'tel') ?>
          <?= $plainField('business.email', $plain, 'email') ?>
        </div>
      </section>

      <!-- BUSINESS CONFIG -->
      <section class="admin-card set-panel" id="business">
        <div class="admin-head">
          <h2 class="disp mb-0">Business configuration</h2>
          <span class="badge">Non-secret</span>
        </div>

        <fieldset class="set-fieldset">
          <legend class="set-legend disp">Hours</legend>
          <div class="set-daygrid">
            <span class="visually-hidden" id="set-hrs-open">Open</span>
            <span class="visually-hidden" id="set-hrs-close">Close</span>
            <?php foreach ($hourDays as $label => $d):
              $openKey = "business.hours.{$d}_open";
              $closeKey = "business.hours.{$d}_close";
              $openId = 'set-hours-' . $d . '-open';
              $closeId = 'set-hours-' . $d . '-close'; ?>
              <label for="<?= e($openId) ?>"><?= e($label) ?></label>
              <input id="<?= e($openId) ?>" name="<?= e($openKey) ?>" class="input" type="time"
                     aria-labelledby="set-hrs-open" value="<?= e($plainVal($openKey)) ?>">
              <input id="<?= e($closeId) ?>" name="<?= e($closeKey) ?>" class="input" type="time"
                     aria-label="<?= e($label) ?> close" value="<?= e($plainVal($closeKey)) ?>">
            <?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset class="set-fieldset">
          <legend class="set-legend disp">Tax, AZ TPT rates by category</legend>
          <div class="set-row">
            <div class="field">
              <label for="set-tax-prepared_food_pct">Prepared food %</label>
              <input id="set-tax-prepared_food_pct" name="tax.prepared_food_pct" class="input"
                     type="number" inputmode="decimal" step="0.01" min="0" value="<?= e($plainVal('tax.prepared_food_pct')) ?>">
            </div>
            <div class="field">
              <label for="set-tax-retail_pct">Retail %</label>
              <input id="set-tax-retail_pct" name="tax.retail_pct" class="input"
                     type="number" inputmode="decimal" step="0.01" min="0" value="<?= e($plainVal('tax.retail_pct')) ?>">
            </div>
          </div>
        </fieldset>

        <fieldset class="set-fieldset">
          <legend class="set-legend disp">Ordering &amp; service</legend>
          <div class="set-row">
            <div class="field">
              <label for="set-pickup-slot_capacity">Pickup slot capacity</label>
              <input id="set-pickup-slot_capacity" name="pickup.slot_capacity" class="input"
                     type="number" inputmode="numeric" min="0" value="<?= e($plainVal('pickup.slot_capacity')) ?>">
            </div>
            <div class="field">
              <label for="set-pickup-lead_minutes">Pickup prep lead (minutes)</label>
              <input id="set-pickup-lead_minutes" name="pickup.lead_minutes" class="input"
                     type="number" inputmode="numeric" min="0" value="<?= e($plainVal('pickup.lead_minutes')) ?>">
            </div>
            <div class="field">
              <label for="set-ordering-service_fee_pct">Service fee %</label>
              <input id="set-ordering-service_fee_pct" name="ordering.service_fee_pct" class="input"
                     type="number" inputmode="decimal" step="0.1" min="0" value="<?= e($plainVal('ordering.service_fee_pct')) ?>">
            </div>
            <div class="field">
              <label for="set-reservation-deposit_threshold">Large-party deposit threshold (party size)</label>
              <input id="set-reservation-deposit_threshold" name="reservation.deposit_threshold" class="input"
                     type="number" inputmode="numeric" min="0" value="<?= e($plainVal('reservation.deposit_threshold')) ?>">
            </div>
            <div class="field">
              <label for="set-reservation-deposit_cents">Large-party deposit amount (cents)</label>
              <input id="set-reservation-deposit_cents" name="reservation.deposit_cents" class="input"
                     type="number" inputmode="numeric" min="0" value="<?= e($plainVal('reservation.deposit_cents')) ?>">
            </div>
            <div class="field">
              <label for="set-reservation-cancel_cutoff_hours">Reservation cancel cutoff (hours)</label>
              <input id="set-reservation-cancel_cutoff_hours" name="reservation.cancel_cutoff_hours" class="input"
                     type="number" inputmode="numeric" min="0" value="<?= e($plainVal('reservation.cancel_cutoff_hours')) ?>">
            </div>
            <div class="field full">
              <label for="set-business-closures">Closures / holiday dates</label>
              <textarea id="set-business-closures" name="business.closures" class="textarea" rows="3"><?= e($plainVal('business.closures')) ?></textarea>
            </div>
            <?= $plainField('tip.presets', $plain, 'text') ?>
            <?= $plainField('feedback.low_score_threshold', $plain, 'number') ?>
          </div>
        </fieldset>
      </section>

      <div class="sticky-actions">
        <button class="btn btn--solid" type="submit">Save settings</button>
      </div>
    </div>
  </div>
</form>

<script>
  // Per-field Reveal toggle for secret inputs (aria-pressed reflects state).
  document.querySelectorAll('.set-reveal').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-reveal'));
      if (!input) { return; }
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.textContent = show ? 'Hide' : 'Reveal';
    });
  });
</script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
