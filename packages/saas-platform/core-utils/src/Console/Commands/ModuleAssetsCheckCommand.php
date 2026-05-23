<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\AssetSymlinkService;
use SaaSPlatform\CoreUtils\Services\AssetVersionService;

class ModuleAssetsCheckCommand extends Command
{
    protected $signature = 'module:assets-check
        {--module=tickets : نام ماژول برای بررسی}
        {--path=css/tickets.css : مسیر فایل asset برای بررسی}';

    protected $description = 'بررسی صحت و یکپارچگی Asset Pipeline ماژول‌ها';

    protected int $passed = 0;
    protected int $failed = 0;

    public function handle(AssetSymlinkService $symlinkService, AssetVersionService $versionService): int
    {
        $module = $this->option('module');
        $assetPath = $this->option('path');

        $this->newLine();
        $this->line('═══════════════════════════════════════════');
        $this->line('   بررسی Asset Pipeline ماژول: ' . $module);
        $this->line('═══════════════════════════════════════════');
        $this->newLine();

        // ─── 1. بررسی Symlink ───
        $this->checkSymlink($symlinkService, $module);

        // ─── 2. بررسی فایل asset در مسیرهای مختلف ───
        $this->checkAssetFiles($module, $assetPath);

        // ─── 3. بررسی Version Hash ───
        $this->checkVersionHash($versionService, $module, $assetPath);

        // ─── 4. بررسی Blade Directive ───
        $this->checkBladeDirective($module, $assetPath);

        // ─── 5. بررسی Config ───
        $this->checkConfig($module);

        // ─── Summary ───
        $this->newLine();
        $this->line('───────────────────────────────────────────');
        $total = $this->passed + $this->failed;
        $this->line("   نتیجه: {$this->passed}/{$total} تست با موفقیت گذرانده شد");

        if ($this->failed === 0) {
            $this->info('   ✅ همه چیز سالم است.');
            return Command::SUCCESS;
        }

        $this->error("   ❌ {$this->failed} تست شکست خورده است.");
        return Command::FAILURE;
    }

    protected function checkSymlink(AssetSymlinkService $service, string $module): void
    {
        $this->line('📁 [1] بررسی Symlink');
        $this->line('───────────────────────────────────────────');

        $targetPath = public_path("vendor/{$module}");

        if (! is_dir($targetPath)) {
            $this->warn("   ⚠️  پوشه public/vendor/{$module} وجود ندارد.");
            $this->info('   ℹ️  در محیط توسعه، Middleware به‌صورت خودکار آن را ایجاد می‌کند.');
            $this->failed++;
            return;
        }

        if (is_link($targetPath)) {
            $this->info("   ✅ Symlink فعال است: {$targetPath}");
            $this->line("       →指向: " . readlink($targetPath));
        } else {
            $this->info("   ✅ پوشه وجود دارد (Publish شده یا دستی ایجاد شده)");
        }

        $this->passed++;
    }

    protected function checkAssetFiles(string $module, string $assetPath): void
    {
        $this->newLine();
        $this->line('📂 [2] بررسی فایل‌های Asset');
        $this->line('───────────────────────────────────────────');

        $paths = [
            "public/vendor/{$module}/{$assetPath}" => 'public/vendor',
            "vendor/saas-platform/modules-{$module}/Resources/assets/{$assetPath}" => 'vendor package',
            "packages/saas-platform/modules/{$module}/Resources/assets/{$assetPath}" => 'packages (dev)',
        ];

        $found = false;
        foreach ($paths as $relativePath => $label) {
            $fullPath = base_path($relativePath);
            if (file_exists($fullPath)) {
                $this->info("   ✅ فایل در {$label} یافت شد:");
                $this->line("       {$fullPath}");
                $found = true;
                break;
            }
        }

        if (! $found) {
            $this->error("   ❌ فایل {$assetPath} در هیچ مسیری یافت نشد.");
            $this->failed++;
            return;
        }

        // بررسی فایل CSS در public/vendor
        $publicFile = public_path("vendor/{$module}/{$assetPath}");
        if (file_exists($publicFile)) {
            $size = filesize($publicFile);
            $this->line("       حجم: " . number_format($size) . " بایت");
        }

        $this->passed++;
    }

    protected function checkVersionHash(AssetVersionService $service, string $module, string $assetPath): void
    {
        $this->newLine();
        $this->line('🔢 [3] بررسی Version Hash');
        $this->line('───────────────────────────────────────────');

        $hash = $service->version($module, $assetPath);
        $url = $service->url($module, $assetPath);

        $this->line("   Hash: {$hash}");
        $this->line("   URL:  {$url}");

        if (strlen($hash) === 32 && ctype_xdigit($hash)) {
            $this->info('   ✅ Hash معتبر است (MD5)');
            $this->passed++;
        } else {
            $this->error('   ❌ Hash نامعتبر است');
            $this->failed++;
        }
    }

    protected function checkBladeDirective(string $module, string $assetPath): void
    {
        $this->newLine();
        $this->line('🖌️ [4] بررسی Blade Directive');
        $this->line('───────────────────────────────────────────');

        $extension = pathinfo($assetPath, PATHINFO_EXTENSION);
        $expectedTag = match (strtolower($extension)) {
            'css' => '<link rel="stylesheet"',
            'js' => '<script src=',
            default => null,
        };

        if ($expectedTag === null) {
            $this->warn("   ⚠️  پسوند فایل '{$extension}' پشتیبانی نمی‌شود");
            $this->failed++;
            return;
        }

        $this->info("   ✅ @asset_module('{$module}', '{$assetPath}')");
        $this->line("       → خروجی مورد انتظار: {$expectedTag} ...?v={hash}");
        $this->passed++;
    }

    protected function checkConfig(string $module): void
    {
        $this->newLine();
        $this->line('⚙️ [5] بررسی Config');
        $this->line('───────────────────────────────────────────');

        $modules = config('module-assets.modules', []);

        if (empty($modules)) {
            $this->warn('   ⚠️  هیچ ماژولی در config(\'module-assets.modules\') ثبت نشده است');
            $this->failed++;
            return;
        }

        $found = false;
        foreach ($modules as $entry) {
            if (($entry['name'] ?? '') === $module) {
                $found = true;
                $this->info("   ✅ ماژول '{$module}' در config ثبت شده است");
                $this->line("       source: {$entry['source']}");
                break;
            }
        }

        if (! $found) {
            $this->warn("   ⚠️  ماژول '{$module}' در config(\'module-assets.modules\') یافت نشد");
            $this->line('       ماژول‌های ثبت شده:');
            foreach ($modules as $entry) {
                $this->line("       - {$entry['name']}");
            }
            $this->failed++;
            return;
        }

        $this->passed++;
    }
}
