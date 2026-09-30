<?php

use App\Services\ThemeBuilder\BlockRenderer;
use Database\Seeders\SleepShimanHomeBlueThemeSeeder;
use SaaSPlatform\ThemeRuntime\SleepShimanBlockTypes;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$output = $argv[1] ?? storage_path('app/theme-baselines/sleepshiman-home-blue/preview.html');
$directory = dirname($output);
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    throw new RuntimeException('Unable to create preview directory.');
}

$renderer = app(BlockRenderer::class);
$definitions = SleepShimanBlockTypes::definitions();
$block = static fn (string $id, string $type): array => [
    'id' => $id, 'type' => $type, 'props' => $definitions[$type]['defaultProps'] ?? [],
];
$pages = SleepShimanHomeBlueThemeSeeder::pages();
$header = $renderer->renderBlocks([
    $block('sleepshiman-header', 'sleepshiman_header'),
    $block('sleepshiman-navigation', 'sleepshiman_navigation'),
]);
$body = $renderer->renderBlocks($pages['home']['blocks']);
$footer = $renderer->renderBlock($block('sleepshiman-footer', 'sleepshiman_footer'));
$assetRoot = str_replace('\\', '/', realpath(public_path('theme-assets/sleepshiman-home-blue/1.0.0')) ?: '');
$css = file_get_contents(public_path('theme-assets/sleepshiman-home-blue/1.0.0/theme.css'));
$js = file_get_contents(public_path('theme-assets/sleepshiman-home-blue/1.0.0/theme.js'));
$html = '<!doctype html><html lang="fa-IR" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SleepShiman Home blue visual acceptance</title><base href="file:///'.$assetRoot.'/"><style>body{margin:0;background:#fff}'.$css.'</style></head><body>'.$header.$body.$footer.'<script>'.$js.'</script></body></html>';
if (file_put_contents($output, $html) === false) throw new RuntimeException('Unable to write preview.');
fwrite(STDOUT, $output.PHP_EOL);
