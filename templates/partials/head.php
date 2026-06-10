<?php
/**
 * <head> partial. Expects optional: $title, $description, $canonical,
 * $ogImage, $jsonLd (raw JSON-LD string already escaped/encoded), $bodyClass.
 */
$appName = config('name', "Amelia's by EAT");
$pageTitle = isset($title) && $title !== '' ? "{$title} · {$appName}" : $appName;
$desc = $description ?? 'Source-to-plate dining, market, wine club and events in Arizona.';
$canonicalUrl = $canonical ?? null;
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if ($canonicalUrl): ?><link rel="canonical" href="<?= e($canonicalUrl) ?>"><?php endif; ?>

<!-- Open Graph / Twitter -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($appName) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">

<link rel="icon" href="<?= e(asset('brand/AmeliasbyEat-sublogo.png')) ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= e(asset('brand/AmeliasbyEat-sublogo.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">

<!-- 5-file token architecture (load order matters) -->
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/theme.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/reset.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/utilities.css')) ?>">

<?php
// Per-page stylesheets: a template/controller may pass $styles => ['pages/foo.css', ...]
// Page CSS lives under assets/css/pages/, so entries resolve relative to assets/css/.
foreach (($styles ?? []) as $sheet): ?>
<link rel="stylesheet" href="<?= e(asset('css/' . ltrim($sheet, '/'))) ?>">
<?php endforeach; ?>

<?php if (!empty($jsonLd)): ?>
<script type="application/ld+json"><?= $jsonLd ?></script>
<?php endif; ?>
