<?php

/**
 * CSS parity harness (ADR-063) — render a host's effective block CSS: the
 * theme token block plus the host's style partial (which includes the
 * shared theme-runtime contract and block stylesheet).
 *
 * Usage:
 *   php tests/Support/CssParity/css.php <host-root> <customer|platform> <out.css> [--tokens=default|none|<palette.json>]
 *
 *   default  token block for an empty global_styles (a theme with no palette)
 *   none     no token block at all — exercises every var() fallback
 *   <file>   a JSON global_styles object, e.g. {"primary_color":"#e11d48"}
 */

[$script, $root, $host, $out] = array_pad(array_slice($argv, 0, 4), 4, null);
if (!$root || !in_array($host, ['customer', 'platform'], true) || !$out) {
    fwrite(STDERR, "usage: php css.php <host-root> <customer|platform> <out.css> [--tokens=default|none|<palette.json>]\n");
    exit(2);
}
$mode = 'default';
foreach (array_slice($argv, 4) as $arg) {
    if (str_starts_with($arg, '--tokens=')) {
        $mode = substr($arg, 9);
    }
}

chdir($root);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$gs = [];
if (!in_array($mode, ['default', 'none'], true)) {
    $gs = json_decode((string) file_get_contents($mode), true);
    if (!is_array($gs)) {
        fwrite(STDERR, "palette file is not a JSON object: {$mode}\n");
        exit(2);
    }
}
$tokens = (new SaaSPlatform\ThemeRuntime\ThemeTokenNormalizer())->renderCssBlock($gs);

if ($host === 'customer') {
    // The customer partial renders the token block itself.
    $css = view('partials.theme-canvas-style-contract', ['gs' => $gs])->render();
    if ($mode === 'none') {
        $count = 0;
        $css = str_replace($tokens, '', $css, $count);
        if ($count !== 1) {
            fwrite(STDERR, "could not strip the token block from the customer partial\n");
            exit(1);
        }
    }
} else {
    $css = ($mode === 'none' ? '' : $tokens . "\n") . view('admin.theme-builder.partials.canvas-css-contract')->render();
}

file_put_contents($out, $css);
echo strlen($css) . " bytes ({$host}, tokens: {$mode})\n";
