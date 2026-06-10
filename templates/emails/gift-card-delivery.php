<?php /** Gift card delivery. Expects: code, amount, fromName, redeemUrl */ ?>
<h1 style="font-size:22px;margin:0 0 12px;color:#313530;">You've received a gift card</h1>
<?php if (!empty($fromName)): ?><p style="margin:0 0 12px;">A gift from <strong><?= e($fromName) ?></strong>.</p><?php endif; ?>
<p style="margin:0 0 8px;">Balance: <strong><?= e((string) ($amount ?? '')) ?></strong></p>
<p style="margin:0 0 16px;">Redemption code:</p>
<p style="font-size:24px;letter-spacing:3px;font-weight:700;background:#efe9d9;padding:14px;text-align:center;border-radius:2px;"><?= e((string) ($code ?? '')) ?></p>
<?php if (!empty($redeemUrl)): ?>
<p style="margin:16px 0 0;"><a href="<?= e($redeemUrl) ?>" style="display:inline-block;background:#313530;color:#f9f6e4;padding:12px 22px;border-radius:2px;text-decoration:none;">Order with your gift card</a></p>
<?php endif; ?>
