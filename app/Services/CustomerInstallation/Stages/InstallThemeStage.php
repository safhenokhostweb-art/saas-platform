<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

class InstallThemeStage extends AbstractStage
{
    public function name(): string { return 'INSTALL_THEME'; }
    public function label(): string { return 'نصب قالب'; }
    public function estimatedSeconds(): int { return 5; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $themeSlug = $ctx->request->themeSlug;

            if (empty($themeSlug)) {
                return StageResult::skipped($this->name(), 'No theme selected');
            }

            try {
                event('customer.theme.install', [
                    'theme_slug'    => $themeSlug,
                    'installation'  => $ctx->installation,
                    'connection'    => $ctx->connection,
                    'web_root'      => $ctx->webRoot(),
                ]);
            } catch (\Throwable $e) {
                // Theme installation failure is a warning, not a pipeline failure
                return StageResult::success($this->name(), [
                    'theme_slug' => $themeSlug,
                    'status'     => 'warning',
                    'message'    => 'Theme installation event failed: ' . $e->getMessage(),
                ], [], $this->elapsed());
            }

            return StageResult::success($this->name(), [
                'theme_slug' => $themeSlug,
                'status'     => 'installed',
            ], [], $this->elapsed());
        });
    }
}
