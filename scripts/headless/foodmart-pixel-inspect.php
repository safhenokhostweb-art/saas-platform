<?php

$root = 'C:/laragon/www/saas-platform/storage/app/theme-baselines/foodmart';
$pairs = [
    'mobile-footer' => ["{$root}/original/mobile-390/sections/footer.png", "{$root}/phase2d/mobile-390/sections/footer.png"],
    'breakpoint-full' => ["{$root}/original/breakpoint-991/full-page.png", "{$root}/phase2d/breakpoint-991/full-page.png"],
    'large-full' => ["{$root}/original/large-desktop-1440/full-page.png", "{$root}/phase2d/large-desktop-1440/full-page.png"],
];
$result = [];
foreach ($pairs as $name => [$baseline, $candidate]) {
    $a = imagecreatefrompng($baseline);
    $b = imagecreatefrompng($candidate);
    $width = imagesx($a);
    $height = imagesy($a);
    $differences = [];
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $ac = imagecolorat($a, $x, $y);
            $bc = imagecolorat($b, $x, $y);
            if ($ac !== $bc) {
                $differences[] = [
                    'x' => $x, 'y' => $y,
                    'baseline' => [($ac >> 16) & 255, ($ac >> 8) & 255, $ac & 255],
                    'candidate' => [($bc >> 16) & 255, ($bc >> 8) & 255, $bc & 255],
                ];
            }
        }
    }
    imagedestroy($a);
    imagedestroy($b);
    $result[$name] = $differences;
}
file_put_contents("{$root}/phase2d/direct-pixel-inspection.json", json_encode($result, JSON_PRETTY_PRINT));
echo json_encode(array_map('count', $result), JSON_PRETTY_PRINT), PHP_EOL;
