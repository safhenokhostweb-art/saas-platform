<?php

/**
 * CSS parity harness (ADR-063) — render a markup corpus through a host's
 * real block render path.
 *
 * Usage:
 *   php tests/Support/CssParity/corpus.php <host-root> <customer|platform> <out.json> [--snapshots=<dir>]
 *
 * Corpus: every registered block type at its defaultProps (20 per
 * document), plus every page of every `*-snapshot.json` canonical theme
 * snapshot (header + page + footer blocks). Snapshots default to
 * customer-site's storage/app, next to this checkout.
 */

[$script, $root, $host, $out] = array_pad(array_slice($argv, 0, 4), 4, null);
if (!$root || !in_array($host, ['customer', 'platform'], true) || !$out) {
    fwrite(STDERR, "usage: php corpus.php <host-root> <customer|platform> <out.json> [--snapshots=<dir>]\n");
    exit(2);
}
$snapDir = dirname(__DIR__, 4) . '/customer-site/storage/app';
foreach (array_slice($argv, 4) as $arg) {
    if (str_starts_with($arg, '--snapshots=')) {
        $snapDir = substr($arg, 12);
    }
}

chdir($root);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
// Renderer warnings are counted and reported, never silenced: a warning
// that only shows up here (e.g. an undefined prop key) is a renderer bug.
$warnings = [];
set_error_handler(function (int $no, string $message, string $file, int $line) use (&$warnings) {
    if ($no & (E_DEPRECATED | E_USER_DEPRECATED)) {
        return true;
    }
    $key = basename($file) . ":{$line} {$message}";
    $warnings[$key] = ($warnings[$key] ?? 0) + 1;
    return true;
});

if ($host === 'customer') {
    $registry = App\Services\ThemeCanvas\BlockRegistry::all();
    $make = function (array $themeData) {
        $renderer = app(App\Services\ThemeCanvas\ThemeCanvasRenderer::class);
        $renderer->setThemeData($themeData);
        return $renderer;
    };
} else {
    $registry = SaaSPlatform\ThemeRuntime\BlockRegistry::all();
    $make = fn (array $themeData) => new App\Services\ThemeBuilder\BlockRenderer();
}

$render = function ($renderer, array $blocks): string {
    $html = '';
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        try {
            $html .= $renderer->renderBlock($block) . "\n";
        } catch (Throwable $e) {
            $html .= "<!-- error: {$e->getMessage()} -->\n";
        }
    }
    return $html;
};

$corpus = [];
$renderer = $make(['pages' => []]);
$chunk = [];
$i = 0;
foreach ($registry as $type => $def) {
    if (str_starts_with($type, 'ext:')) {
        continue;
    }
    $chunk[] = ['id' => 'p03_' . $i++, 'type' => $type, 'props' => $def['defaultProps'] ?? []];
    if (count($chunk) === 20) {
        $corpus['types-' . count($corpus)] = $render($renderer, $chunk);
        $chunk = [];
    }
}
if ($chunk) {
    $corpus['types-' . count($corpus)] = $render($renderer, $chunk);
}

foreach (glob($snapDir . '/*-snapshot.json') ?: [] as $file) {
    $slug = basename($file, '-snapshot.json');
    $snap = json_decode(file_get_contents($file), true);
    if (!is_array($snap)) {
        continue;
    }
    $renderer = $make($snap);
    $header = $render($renderer, is_array($snap['header_blocks'] ?? null) ? $snap['header_blocks'] : []);
    $footer = $render($renderer, is_array($snap['footer_blocks'] ?? null) ? $snap['footer_blocks'] : []);
    foreach ((is_array($snap['pages'] ?? null) ? $snap['pages'] : []) as $key => $page) {
        $blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : [];
        if ($blocks) {
            $corpus["{$slug}/{$key}"] = $header . $render($renderer, $blocks) . $footer;
        }
    }
}

file_put_contents($out, json_encode($corpus, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo count($corpus) . ' documents, ' . round(strlen(json_encode($corpus)) / 1e6, 1) . " MB\n";
if ($warnings) {
    fwrite(STDERR, count($warnings) . " distinct renderer warning(s):\n");
    foreach ($warnings as $key => $count) {
        fwrite(STDERR, "  {$count}× {$key}\n");
    }
    exit(3);
}
