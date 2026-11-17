<?php

namespace App\Services\CustomerInstallation\Stages;

use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\Onboarding\SiteTemplateBinder;
use App\Services\PublishedThemeAssignmentService;

/**
 * ADR-088 — binds the requested theme to the new site through the canonical
 * PublishedThemeAssignmentService: ThemeLicense, ThemeAssignment and
 * `sites.theme_id`, so the site owns the theme (home page included, e.g. the
 * solar-industry theme's `solar-page-home`) from the moment it exists.
 *
 * Until ADR-088 this stage only fired a `customer.theme.install` event that
 * nothing listened to, so a theme chosen at installation never reached the
 * site. Pushing the theme's pages onto the customer-site is a separate step
 * because it cannot happen here: customer-site redirects every request to
 * /install until CUSTOMER_READY writes its installed.lock. The binding is
 * therefore recorded as `pending_push` and SiteTemplateBinder performs the
 * push (ThemeInstallGateway) once the installation completes.
 *
 * A failed assignment stays a warning, as it always was for this stage: the
 * site itself is installed and the theme can be bound again afterwards.
 */
class InstallThemeStage extends AbstractStage
{
    public function __construct(
        private readonly PublishedThemeAssignmentService $assignments,
        private readonly SiteTemplateBinder $binder,
    ) {}

    public function name(): string { return 'INSTALL_THEME'; }
    public function label(): string { return 'اختصاص قالب'; }
    public function estimatedSeconds(): int { return 5; }

    public function execute(InstallationContext $ctx): StageResult
    {
        return $this->run($ctx, function (InstallationContext $ctx) {
            $req = $ctx->request;

            if ($req->themeId === null && $req->themeSlug === '') {
                return StageResult::skipped($this->name(), 'No theme selected');
            }

            $theme = $req->themeId !== null
                ? Theme::find($req->themeId)
                : Theme::query()->where('slug', $req->themeSlug)->whereNull('site_id')->first();
            $site = $ctx->installation->site_id ? Site::find($ctx->installation->site_id) : null;

            if ($theme === null || $site === null) {
                return $this->warning($ctx, $theme, $theme === null ? 'Requested theme was not found.' : 'Site record is missing.');
            }

            try {
                $this->assignments->assign(
                    $site,
                    $theme,
                    ThemeAssignment::SOURCE_ADMIN,
                    assignedBy: $ctx->installation->installer_user_id,
                );
            } catch (\Throwable $e) {
                return $this->warning($ctx, $theme, $e->getMessage());
            }

            $this->binder->markPendingPush($ctx->installation, $theme);

            return StageResult::success($this->name(), [
                'theme_id'   => $theme->id,
                'theme_slug' => $theme->slug,
                'status'     => 'assigned',
                'push'       => SiteTemplateBinder::STATUS_PENDING_PUSH,
            ], [], $this->elapsed());
        });
    }

    private function warning(InstallationContext $ctx, ?Theme $theme, string $message): StageResult
    {
        $this->binder->markFailed($ctx->installation, $theme, $message);

        return StageResult::success($this->name(), [
            'theme_id' => $theme?->id,
            'status'   => 'warning',
            'message'  => $message,
        ], [], $this->elapsed());
    }
}
