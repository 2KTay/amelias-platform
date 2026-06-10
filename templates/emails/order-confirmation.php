<?php /** Order confirmation email body. Expects: name, orderNumber, total, items[], pickupTime, statusUrl */ ?>
<h1 style="font-size:22px;margin:0 0 12px;color:#313530;">Thanks<?= !empty($name) ? ', ' . e($name) : '' ?>, your order is confirmed</h1>
<p style="margin:0 0 16px;">Order <strong>#<?= e((string) ($orderNumber ?? '')) ?></strong><?php if (!empty($pickupTime)): ?> · pickup <strong><?= e($pickupTime) ?></strong><?php endif; ?>.</p>
<?php if (!empty($items)): ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 16px;">
  <?php foreach ($items as $it): ?>
  <tr>
    <td style="padding:6px 0;border-bottom:1px solid #efe9d9;"><?= e($it['name'] ?? '') ?> × <?= (int) ($it['qty'] ?? 1) ?></td>
    <td align="right" style="padding:6px 0;border-bottom:1px solid #efe9d9;"><?= e($it['price'] ?? '') ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<p style="font-size:18px;margin:0 0 20px;"><strong>Total: <?= e((string) ($total ?? '')) ?></strong></p>
<?php if (!empty($statusUrl)): ?>
<p><a href="<?= e($statusUrl) ?>" style="display:inline-block;background:#313530;color:#f9f6e4;padding:12px 22px;border-radius:2px;text-decoration:none;">Track your order</a></p>
<?php endif; ?>
