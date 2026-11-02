<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\Widget\WidgetFeatureProfile;

/**
 * ADR-077: writes the Sentinel UI verifier's widget profiles
 * (tests/sentinel/widget-profiles.json) from the registry and
 * WidgetFeatureProfile. `--check` exits 1 when the committed file is stale.
 */
class SentinelProfilesCommand extends Command
{
    protected $signature = 'sentinel:profiles {--check : Fail when the committed file differs instead of writing it}';

    protected $description = 'Write tests/sentinel/widget-profiles.json for the Sentinel UI verifier';

    public static function path(): string
    {
        return base_path('tests/sentinel/widget-profiles.json');
    }

    public static function render(): string
    {
        return json_encode(WidgetFeatureProfile::export(BlockRegistry::all()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    public function handle(): int
    {
        $violations = WidgetFeatureProfile::violations(BlockRegistry::all());
        if ($violations !== []) {
            foreach ($violations as $line) {
                $this->error($line);
            }
            return self::FAILURE;
        }
        $json = self::render();
        if ($this->option('check')) {
            $current = @file_get_contents(self::path());
            if ($current !== $json) {
                $this->error('tests/sentinel/widget-profiles.json is stale; run php artisan sentinel:profiles.');
                return self::FAILURE;
            }
            $this->info('widget-profiles.json is current.');
            return self::SUCCESS;
        }
        file_put_contents(self::path(), $json);
        $this->info('Wrote ' . self::path());
        return self::SUCCESS;
    }
}
