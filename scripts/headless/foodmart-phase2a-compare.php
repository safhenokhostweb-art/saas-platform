<?php

/**
 * Phase 2A — FoodMart visual-regression comparison.
 *
 * Compares a candidate FoodMart capture (from
 * scripts/headless/foodmart-phase2a-capture.mjs) against the accepted
 * pre-change baseline using the SAME normalized comparison definition the
 * existing phase2c/2d harnesses established (per-channel maximum delta,
 * tolerance 12). PNG-only; uses the same GD approach as those harnesses.
 *
 * Usage:
 *   php foodmart-phase2a-compare.php <baselineDir> <candidateDir>
 * Defaults: baseline=phase2a-capture, candidate=phase2a-candidate
 *
 * Writes storage/app/theme-baselines/foodmart/phase2a/pixeldiff-results.json
 * and prints a summary table. Exit code 0 = pass (all viewports within
 * tolerance), 1 = regression.
 */

$root = 'storage/app/theme-baselines/foodmart';
$baselineDir = $argv[1] ?? "{$root}/phase2a-capture";
$candidateDir = $argv[2] ?? "{$root}/phase2a-candidate";
$viewports = ['390', '768', '991', '1200', '1440'];
$tolerance = 12;

function comparePng(string $baseline, string $candidate, int $tolerance): array
{
    if (!is_file($baseline) || !is_file($candidate)) {
        return ['missing' => true, 'baselineExists' => is_file($baseline), 'candidateExists' => is_file($candidate)];
    }
    [$aw, $ah] = getimagesize($baseline);
    [$bw, $bh] = getimagesize($candidate);
    if ($aw !== $bw || $ah !== $bh) {
        return ['dimensionMismatch' => true, 'baseline' => "{$aw}x{$ah}", 'candidate' => "{$bw}x{$bh}"];
    }
    $a = imagecreatefrompng($baseline);
    $b = imagecreatefrompng($candidate);
    $different = 0;
    for ($y = 0; $y < $ah; $y++) {
        for ($x = 0; $x < $aw; $x++) {
            $ac = imagecolorat($a, $x, $y);
            $bc = imagecolorat($b, $x, $y);
            $delta = max(
                abs((($ac >> 16) & 0xff) - (($bc >> 16) & 0xff)),
                abs((($ac >> 8) & 0xff) - (($bc >> 8) & 0xff)),
                abs(($ac & 0xff) - ($bc & 0xff))
            );
            if ($delta > $tolerance) {
                $different++;
            }
        }
    }
    imagedestroy($a);
    imagedestroy($b);
    $total = $aw * $ah;

    return ['width' => $aw, 'height' => $ah, 'diffPixels' => $different, 'totalPixels' => $total, 'diffPercent' => round($different * 100 / $total, 4)];
}

$results = [
    'definition' => "per-channel maximum delta > {$tolerance} (normalized, same as phase2c/2d harnesses)",
    'baselineDir' => str_replace('\\', '/', $baselineDir),
    'candidateDir' => str_replace('\\', '/', $candidateDir),
    'comparedAt' => date('c'),
    'fullPage' => [],
];
$pass = true;
foreach ($viewports as $w) {
    $r = comparePng("{$baselineDir}/w{$w}.png", "{$candidateDir}/w{$w}.png", $tolerance);
    $results['fullPage'][$w] = $r;
    $ok = !($r['missing'] ?? false) && !($r['dimensionMismatch'] ?? false) && ($r['diffPercent'] ?? 100) === 0.0;
    if (!$ok) {
        $pass = false;
    }
    printf("%5s: %s\n", $w, $r['missing'] ?? false ? 'MISSING' : (($r['dimensionMismatch'] ?? false) ? 'DIM-MISMATCH' : sprintf('diff=%.4f%% (%d px)', $r['diffPercent'], $r['diffPixels'])));
}

$outDir = "{$root}/phase2a";
@mkdir($outDir, 0777, true);
file_put_contents("{$outDir}/pixeldiff-results.json", json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo $pass ? "VISUAL REGRESSION: PASS (byte-identical within tolerance)\n" : "VISUAL REGRESSION: FAIL\n";
exit($pass ? 0 : 1);
