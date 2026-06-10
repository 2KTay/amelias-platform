<?php
/**
 * Email layout, bulletproof, inline-styled table shell (email clients ignore
 * external CSS, so inline styles here are correct, not a convention violation).
 * $innerTemplate is the body partial; CAN-SPAM footer included.
 */
$response = new \Amelias\Http\Response();
$inner = $response->capture($innerTemplate ?? 'emails/blank', get_defined_vars());
$brand = config('name', "Amelia's by EAT");
$addr = '8240 N Hayden Road, Ste B-105, Scottsdale, AZ 85258';
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f9f6e4;font-family:Helvetica,Arial,sans-serif;color:#313530;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9f6e4;padding:24px 0;">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fcfaf3;border:1px solid #e4ddca;border-radius:2px;">
        <tr><td style="padding:28px 32px;border-bottom:1px solid #e4ddca;">
          <span style="font-size:20px;font-weight:600;color:#313530;"><?= e($brand) ?></span>
        </td></tr>
        <tr><td style="padding:28px 32px;font-size:15px;line-height:1.6;color:#313530;">
          <?= $inner ?>
        </td></tr>
        <tr><td style="padding:20px 32px;border-top:1px solid #e4ddca;font-size:12px;color:#9a9a8d;">
          <?= e($brand) ?> · <?= e($addr) ?><br>
          <?php if (!empty($isMarketing)): ?>
            You received this because you opted in. <a href="<?= e($unsubscribeUrl ?? '#') ?>" style="color:#46600f;">Unsubscribe</a>.
          <?php else: ?>
            This is a transactional message about your order or reservation.
          <?php endif; ?>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
