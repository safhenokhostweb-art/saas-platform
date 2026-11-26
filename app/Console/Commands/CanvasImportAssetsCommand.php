<?php

namespace App\Console\Commands;

use App\Services\AssetProvisioning\CanvasAssetImportService;
use Illuminate\Console\Command;
use Throwable;

class CanvasImportAssetsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'canvas:import-assets {file : Path to the JSON assets definition file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import canvas assets (button packs, icon packs, header presets, motions) with SemVer and changelog from a JSON file';

    /**
     * Execute the console command.
     */
    public function handle(CanvasAssetImportService $importer): int
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            // Check relative to base path
            $relative = base_path($filePath);
            if (file_exists($relative)) {
                $filePath = $relative;
            } else {
                $this->error("فایل در مسیر [{$filePath}] یافت نشد.");
                return self::FAILURE;
            }
        }

        $this->info("در حال ورود دارایی‌های Canvas از فایل: {$filePath} ...");

        try {
            $summary = $importer->importFromFile($filePath);

            $this->table(
                ['نوع دارایی', 'تعداد ثبت / بروزرسانی'],
                [
                    ['پک‌های دکمه (Button Packs)', $summary['button_packs']],
                    ['پریست‌های دکمه (Button Presets)', $summary['button_presets']],
                    ['پک‌های آیکن (Icon Packs)', $summary['icon_packs']],
                    ['آیتم‌های آیکن (Icon Items)', $summary['icon_items']],
                    ['قالب‌های هدر (Header Presets)', $summary['header_presets']],
                    ['موشن گرافیک (Motion Graphics)', $summary['motion_graphics']],
                    ['نسخه‌های ثبت‌شده (Asset Versions)', $summary['versions']],
                ]
            );

            $this->info('ورود دارایی‌ها با موفقیت انجام شد.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('خطا در ورود دارایی‌ها: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
