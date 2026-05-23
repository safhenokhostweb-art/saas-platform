<?php

namespace SaaSPlatform\CoreUtils\Blade;

use SaaSPlatform\CoreUtils\Services\AssetVersionService;

/**
 * Blade Directive برای بارگذاری خودکار Assetهای ماژول.
 *
 * استفاده در Viewها:
 *   @asset_module('tickets', 'css/tickets.css')
 *   @asset_module('tickets', 'js/tickets.js')
 *
 * خروجی:
 *   <link rel="stylesheet" href="{{ asset('vendor/tickets/css/tickets.css') }}?v=hash">
 *   <script src="{{ asset('vendor/tickets/js/tickets.js') }}?v=hash"></script>
 */
class AssetModuleDirective
{
    /**
     * ثبت directive در Blade compiler.
     */
    public static function register(): void
    {
        $blade = app('blade.compiler');

        $blade->directive('asset_module', function (string $expression) {
            // پارس کردن پارامترها: ('module', 'path')
            $params = self::parseExpression($expression);
            $module = $params['module'] ?? '';
            $path = $params['path'] ?? '';

            if (empty($module) || empty($path)) {
                return "<!-- @asset_module: invalid parameters -->";
            }

            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $versionService = app(AssetVersionService::class);
            $url = $versionService->url($module, $path);

            switch (strtolower($extension)) {
                case 'css':
                    return "<link rel=\"stylesheet\" href=\"{$url}\">";
                case 'js':
                    return "<script src=\"{$url}\"></script>";
                default:
                    return "<!-- @asset_module: unsupported file type '{$extension}' -->";
            }
        });
    }

    /**
     * پارس کردن عبارت Blade directive.
     * فرمت: ('module', 'path') یا ("module", "path")
     */
    protected static function parseExpression(string $expression): array
    {
        $expression = trim($expression);

        // حذف پرانتزهای ابتدا و انتها
        if (str_starts_with($expression, '(') && str_ends_with($expression, ')')) {
            $expression = substr($expression, 1, -1);
        }

        // جدا کردن پارامترها با کاما
        $parts = explode(',', $expression, 2);
        $parts = array_map(function ($part) {
            return trim($part, " \t\n\r\0\x0B'\"");
        }, $parts);

        return [
            'module' => $parts[0] ?? '',
            'path' => $parts[1] ?? '',
        ];
    }
}
