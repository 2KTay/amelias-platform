<?php /** Reservation reminder. Expects: name, dateTime, partySize, manageUrl */ ?>
<h1 style="font-size:22px;margin:0 0 12px;color:#313530;">See you soon</h1>
<p style="margin:0 0 16px;">A reminder of your reservation: <strong><?= e((string) ($dateTime ?? '')) ?></strong> · party of <?= (int) ($partySize ?? 2) ?>.</p>
<?php if (!empty($manageUrl)): ?>
<p style="margin:0 0 0;">Need to change it? <a href="<?= e($manageUrl) ?>" style="color:#46600f;">Manage your reservation</a>.</p>
<?php endif; ?>
