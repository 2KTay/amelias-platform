<?php

declare(strict_types=1);

/**
 * Conventions audit — the CI gate (frontend-conventions-audit).
 *
 *   php audit/conventions.php           verbose report
 *   php audit/conventions.php --quiet   print only on failure
 *   php audit/conventions.php --paths a b   audit only the given paths
 *
 * Enforced rules (the spec's ubiquitous frontend/data conventions):
 *   FE-1  No inline style="..." in templates.
 *   FE-2  No PHP short open tag "<?" (allow "<?php" and "<?=").
 *   FE-3  No hardcoded hex colors in CSS outside tokens.css / theme.css.
 *   FE-4  No max-width media queries (mobile-first => min-width only).
 *   DB-1  No variable interpolation / concatenation in SQL strings.
 *
 * Waivers: docs in .frontend-conventions.json as
 *   { "waivers": [ { "id": "FE-EX-1", "file": "templates/x.php", "rule": "FE-1", "reason": "..." } ] }
 * A matching (file, rule) pair is reported as "AslanException FE-EX-{id}" and
 * does not fail the build.
 */

$root = dirname(__DIR__);
$quiet = in_array('--quiet', $argv, true);

// Resolve audit paths (production app dir; mockups/docs excluded).
$paths = [
    $root . '/templates',
    $root . '/public/assets/css',
    $root . '/src',
];
if (($i = array_search('--paths', $argv, true)) !== false) {
    $paths = array_slice($argv, $i + 1);
    $paths = array_map(static fn ($p) => str_starts_with($p, '/') || preg_match('/^[A-Za-z]:/', $p) ? $p : $root . '/' . $p, $paths);
}

// Load waivers.
$waivers = [];
$waiverFile = $root . '/.frontend-conventions.json';
if (is_file($waiverFile)) {
    $json = json_decode((string) file_get_contents($waiverFile), true);
    foreach ($json['waivers'] ?? [] as $w) {
        $key = ($w['file'] ?? '') . '|' . ($w['rule'] ?? '');
        $waivers[$key] = $w['id'] ?? 'FE-EX-?';
    }
}

/** @var list<array{rule:string,file:string,line:int,msg:string}> $violations */
$violations = [];
$waived = [];

$addViolation = static function (string $rule, string $relFile, int $line, string $msg) use (&$violations, &$waived, $waivers): void {
    $key = $relFile . '|' . $rule;
    if (isset($waivers[$key])) {
        $waived[] = "AslanException {$waivers[$key]} — {$rule} @ {$relFile}";
        return;
    }
    $violations[] = ['rule' => $rule, 'file' => $relFile, 'line' => $line, 'msg' => $msg];
};

/** Recursively collect files by extension. */
$collect = static function (string $dir, array $exts): array {
    if (!is_dir($dir)) {
        return [];
    }
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && in_array(strtolower($f->getExtension()), $exts, true)) {
            $out[] = $f->getPathname();
        }
    }
    return $out;
};

$rel = static fn (string $abs): string => str_replace('\\', '/', ltrim(str_replace(str_replace('\\', '/', $root), '', str_replace('\\', '/', $abs)), '/'));

foreach ($paths as $path) {
    // ---- Template / PHP checks ----
    foreach ($collect($path, ['php']) as $file) {
        $relFile = $rel($file);
        $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
        $isTemplate = str_contains($relFile, 'templates/');
        $isSource   = str_contains($relFile, 'src/');

        foreach ($lines as $n => $line) {
            $lineNo = $n + 1;

            // Email templates legitimately require inline styles (clients strip
            // external CSS), so FE-1 does not apply under templates/emails/.
            $isEmail = str_contains($relFile, 'templates/emails/');
            if ($isTemplate && !$isEmail && preg_match('/\sstyle\s*=\s*["\']/i', $line)) {
                $addViolation('FE-1', $relFile, $lineNo, 'inline style attribute');
            }
            // FE-2: short open tag not followed by php/=  (skip "<?php" and "<?=")
            if (preg_match('/<\?(?!php|=|xml)/', $line)) {
                $addViolation('FE-2', $relFile, $lineNo, 'PHP short open tag');
            }
            // DB-1: SQL string with interpolation/concatenation.
            if ($isSource && preg_match('/["\']\s*(SELECT|INSERT|UPDATE|DELETE)\b/i', $line)) {
                if (preg_match('/(SELECT|INSERT|UPDATE|DELETE)[^"\']*\$[A-Za-z_]/i', $line)
                    || preg_match('/(SELECT|INSERT|UPDATE|DELETE)[^"\']*["\']\s*\.\s*\$/i', $line)) {
                    $addViolation('DB-1', $relFile, $lineNo, 'possible SQL interpolation — use bound params');
                }
            }
        }
    }

    // ---- CSS checks ----
    foreach ($collect($path, ['css']) as $file) {
        $relFile = $rel($file);
        $base = basename($file);
        $hexAllowed = in_array($base, ['tokens.css', 'theme.css'], true);
        $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];

        foreach ($lines as $n => $line) {
            $lineNo = $n + 1;
            if (!$hexAllowed && preg_match('/#[0-9a-fA-F]{3,8}\b/', $line)) {
                // Allow hex inside url(...) data URIs (SVG noise textures).
                if (!preg_match('/url\(/i', $line)) {
                    $addViolation('FE-3', $relFile, $lineNo, 'hardcoded hex outside tokens/theme');
                }
            }
            if (preg_match('/@media[^{]*max-width/i', $line)) {
                $addViolation('FE-4', $relFile, $lineNo, 'max-width media query (use mobile-first min-width)');
            }
        }
    }
}

// ---- Report ----
$failed = $violations !== [];

if (!$quiet || $failed) {
    if ($waived !== []) {
        fwrite(STDOUT, count($waived) . " waiver(s) applied:\n");
        foreach ($waived as $w) {
            fwrite(STDOUT, "  · {$w}\n");
        }
    }
    if ($failed) {
        fwrite(STDERR, "\nConventions audit FAILED — " . count($violations) . " violation(s):\n");
        foreach ($violations as $v) {
            fwrite(STDERR, sprintf("  [%s] %s:%d — %s\n", $v['rule'], $v['file'], $v['line'], $v['msg']));
        }
        fwrite(STDERR, "\nFix the violations or add a waiver to .frontend-conventions.json.\n");
    } else {
        fwrite(STDOUT, "Conventions audit passed — 0 violations.\n");
    }
}

exit($failed ? 1 : 0);
