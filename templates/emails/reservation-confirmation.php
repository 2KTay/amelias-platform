<?php /** Reservation confirmation. Expects: name, dateTime, partySize, manageUrl, depositLine */ ?>
<h1 style="font-size:22px;margin:0 0 12px;color:#313530;">Your table is booked</h1>
<p style="margin:0 0 8px;"><strong><?= e((string) ($dateTime ?? '')) ?></strong> · party of <?= (int) ($partySize ?? 2) ?></p>
<?php if (!empty($depositLine)): ?><p style="margin:0 0 16px;color:#9a9a8d;"><?= e($depositLine) ?></p><?php endif; ?>
<p style="margin:0 0 20px;">We look forward to seeing you<?= !empty($name) ? ', ' . e($name) : '' ?>.</p>
<?php if (!empty($manageUrl)): ?>
<p><a href="<?= e($manageUrl) ?>" style="display:inline-block;background:#313530;color:#f9f6e4;padding:12px 22px;border-radius:2px;text-decoration:none;">Modify or cancel</a></p>
<?php endif; ?>
