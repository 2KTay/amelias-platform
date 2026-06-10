<?php /** Order-ready email. Expects: name, orderNumber */ ?>
<h1 style="font-size:22px;margin:0 0 12px;color:#313530;">Your order is ready for pickup</h1>
<p style="margin:0 0 16px;">Order <strong>#<?= e((string) ($orderNumber ?? '')) ?></strong> is freshly prepared and waiting at the counter. See you soon<?= !empty($name) ? ', ' . e($name) : '' ?>!</p>
<p style="margin:0;color:#9a9a8d;">8240 N Hayden Road, Ste B-105, Scottsdale</p>
