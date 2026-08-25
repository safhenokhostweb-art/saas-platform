<?php

namespace App\Console\Commands;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use App\Services\ThemeBuilder\ReverseEngineering\PlaywrightExtractionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Replaces placehold.co placeholder marketplace previews with real
 * Playwright-captured screenshots of each theme's actual rendered pages.
 *
 * Reuses, unmodified:
 *  - PlaywrightExtractionService (existing headless engine, no changes)
 *  - ThemeCanvasController::preview() (existing renderer, only gained an
 *    optional `screenshot=1` flag to hide the admin preview topbar)
 *  - The public `storage` disk / `storage:link` convention already used
 *    elsewhere in this app for public-facing uploaded assets.
 *
 * Usage:
 *   php artisan theme:capture-screenshots
 *   php artisan theme:capture-screenshots --theme=khabar-online
 */
class CaptureThemeScreenshotsCommand extends Command
{
    protected $signature = 'theme:capture-screenshots
        {--theme= : Only capture a single theme by slug}
        {--pages=home,about,contact : Comma-separated page keys to capture}';

    protected $description = "Capture real Playwright screenshots of marketplace themes' actual rendered pages";

    public function handle(PlaywrightExtractionService $playwright): int
    {
        $report = $playwright->availabilityReport();
        if (empty($report['ready'])) {
            $this->error('Playwright engine is not available: ' . json_encode($report, JSON_UNESCAPED_UNICODE));
            return self::FAILURE;
        }

        $pageKeys = array_filter(array_map('trim', explode(',', (string) $this->option('pages'))));

        $query = Theme::query()->where('is_marketplace', true);
        if ($slug = $this->option('theme')) {
            $query->where('slug', $slug);
        }

        $themes = $query->get();
        $this->info("Capturing screenshots for {$themes->count()} theme(s), pages: " . implode(', ', $pageKeys));

        $captured = 0;
        $failed   = 0;

        foreach ($themes as $theme) {
            $available = $theme->getPageList();
            $urls = [];

            foreach ($pageKeys as $pageKey) {
                if (!isset($available[$pageKey])) {
                    continue;
                }

                $signedUrl = URL::temporarySignedRoute(
                    'admin.theme-builder.canvas.page-preview-signed',
                    now()->addMinutes(10),
                    ['theme' => $theme->id, 'pageKey' => $pageKey, 'screenshot' => 1]
                );

                try {
                    $result = $playwright->extract($signedUrl, 30000, 'desktop');
                } catch (\Throwable $e) {
                    $this->warn("  ✘ {$theme->slug} [{$pageKey}]: {$e->getMessage()}");
                    $failed++;
                    continue;
                }

                if (empty($result['success']) || empty($result['screenshot_base64'])) {
                    $this->warn("  ✘ {$theme->slug} [{$pageKey}]: no screenshot returned");
                    $failed++;
                    continue;
                }

                $path = "theme-screenshots/{$theme->slug}-{$pageKey}.png";
                Storage::disk('public')->put($path, base64_decode($result['screenshot_base64']));
                $urls[$pageKey] = Storage::disk('public')->url($path);
                $captured++;
                $this->info("  ✔ {$theme->slug} [{$pageKey}] → {$path}");
            }

            if (empty($urls)) {
                continue;
            }

            // Only a page actually captured in THIS run may become the new
            // thumbnail/preview — never fall back to whatever other page
            // happened to be captured, which would silently replace the
            // homepage thumbnail with e.g. the contact page on a partial run.
            if (isset($urls['home'])) {
                $theme->update([
                    'thumbnail'     => $urls['home'],
                    'preview_image' => $urls['home'],
                ]);
            }

            $meta = ThemeMarketplace::where('theme_id', $theme->id)->first();
            if ($meta) {
                $meta->update(['screenshots' => $this->mergeScreenshots($meta->screenshots ?? [], $urls, $theme->slug)]);
            }
        }

        $this->newLine();
        $this->info("Done. Captured: {$captured}, failed: {$failed}.");

        return self::SUCCESS;
    }

    /**
     * Merges freshly captured page URLs into the existing screenshot list
     * instead of replacing it — a partial run (--pages=contact) must update
     * only that page's entry and leave every other existing screenshot
     * (home, about, ...) untouched. Screenshots are stored as a flat URL
     * list (no page-key column), so each existing URL is matched back to a
     * page key via the "{slug}-{pageKey}.png" naming convention this command
     * itself always writes.
     *
     * @param  string[]  $existing
     * @param  array<string,string>  $freshByPageKey
     */
    private function mergeScreenshots(array $existing, array $freshByPageKey, string $slug): array
    {
        $remaining = $freshByPageKey;
        $merged = [];

        foreach ($existing as $url) {
            $matchedKey = null;
            foreach ($remaining as $pageKey => $freshUrl) {
                if (str_ends_with((string) $url, "{$slug}-{$pageKey}.png")) {
                    $matchedKey = $pageKey;
                    break;
                }
            }

            if ($matchedKey !== null) {
                $merged[] = $remaining[$matchedKey];
                unset($remaining[$matchedKey]);
            } else {
                $merged[] = $url;
            }
        }

        // Any captured page that wasn't already present in the existing list
        // (first-ever capture, or a newly added page) is appended.
        foreach ($remaining as $freshUrl) {
            $merged[] = $freshUrl;
        }

        return $merged;
    }
}
