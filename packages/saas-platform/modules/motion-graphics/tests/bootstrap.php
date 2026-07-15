<?php

foreach ([dirname(__DIR__).'/vendor/autoload.php', dirname(__DIR__, 5).'/vendor/autoload.php'] as $autoloader) {
    if (is_file($autoloader)) {
        require $autoloader;
        break;
    }
}
spl_autoload_register(function (string $class): void {
    $map = [
        'SaaSPlatform\\Modules\\MotionGraphics\\Tests\\' => __DIR__.'/',
        'SaaSPlatform\\Modules\\MotionGraphics\\' => dirname(__DIR__).'/src/',
    ];
    foreach ($map as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $path = $base.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($path)) require $path;
        }
    }
});
require_once __DIR__.'/Fakes/Fakes.php';
