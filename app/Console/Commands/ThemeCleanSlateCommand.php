<?php

namespace App\Console\Commands;

use App\Services\ThemeBuilder\ThemeCleanSlate;
use App\Support\DataPurge\TablePurger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Removes every platform theme, its releases/licenses/marketplace records,
 * theme templates, packs, bundles and site menus — after writing a verified
 * snapshot that --restore can put back. See ThemeCleanSlate for the scope.
 *
 *   php artisan themes:clean-slate --dry-run
 *   php artisan themes:clean-slate --force
 *   php artisan themes:clean-slate --restore=storage/app/clean-slate/<dir> --force
 */
class ThemeCleanSlateCommand extends Command
{
    protected $signature = 'themes:clean-slate
                            {--dry-run : List the rows that would be removed, change nothing}
                            {--force : Run without the confirmation prompt (required when not interactive)}
                            {--confirm-database= : Required in production: the name of the database being changed}
                            {--restore= : Put back a snapshot directory written by an earlier run}';

    protected $description = 'Snapshot, then remove all platform themes, theme records and site menus (clean slate)';

    public function handle(): int
    {
        $purger = ThemeCleanSlate::purger();
        $restoreDir = $this->option('restore');

        $plan = $purger->plan();
        $this->table(['جدول', 'ردیف‌های فعلی'], collect($plan)->map(fn ($n, $t) => [$t, $n])->values()->all());

        if ($this->option('dry-run')) {
            $this->info('[dry-run] هیچ تغییری اعمال نشد.');

            return self::SUCCESS;
        }

        if (!$this->authorized($restoreDir ? 'بازگردانی snapshot' : 'حذف همهٔ قالب‌ها و منوها')) {
            return self::FAILURE;
        }

        try {
            return $restoreDir ? $this->restore($purger, $restoreDir) : $this->purge($purger);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function purge(TablePurger $purger): int
    {
        $dir = storage_path('app/clean-slate/'.ThemeCleanSlate::KIND.'-'.now()->format('Ymd\THis'));
        $manifest = $purger->snapshot($dir);
        $this->info("snapshot ثبت و بازخوانی شد: {$dir} (".array_sum(array_column($manifest['entries'], 'rows')).' ردیف)');

        $result = $purger->purge($dir);
        $this->table(['جدول', 'حذف یا جدا شد'], collect($result['removed'])->map(fn ($n, $t) => [$t, $n])->values()->all());

        if ($result['ids_reset'] !== []) {
            $this->line('شمارندهٔ شناسه بازنشانی شد: '.implode(', ', $result['ids_reset']));
        }
        if ($result['ids_reset_skipped']) {
            $this->warn('بازنشانی شناسه‌ها رد شد: فرمان داخل یک تراکنش باز اجرا شد.');
        }

        $this->line("برای بازگردانی: php artisan themes:clean-slate --restore={$dir} --force");

        return self::SUCCESS;
    }

    private function restore(TablePurger $purger, string $dir): int
    {
        $dir = is_dir($dir) ? $dir : base_path($dir);
        $restored = $purger->restore($dir);
        $this->table(['جدول', 'بازگردانده شد'], collect($restored)->map(fn ($n, $t) => [$t, $n])->values()->all());

        return self::SUCCESS;
    }

    private function authorized(string $action): bool
    {
        $database = DB::connection()->getDatabaseName();

        if (app()->environment('production') && $this->option('confirm-database') !== $database) {
            $this->error("در production باید نام پایگاه داده را تأیید کنید: --confirm-database={$database}");

            return false;
        }

        if ($this->option('force')) {
            return true;
        }

        if (!$this->input->isInteractive()) {
            $this->error('اجرای غیرتعاملی بدون --force مجاز نیست.');

            return false;
        }

        if (!$this->confirm("{$action} روی پایگاه داده «{$database}» انجام شود؟ (ابتدا --dry-run را اجرا کنید)")) {
            $this->line('لغو شد.');

            return false;
        }

        return true;
    }
}
