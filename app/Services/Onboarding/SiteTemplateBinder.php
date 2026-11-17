<?php

namespace App\Services\Onboarding;

use App\Jobs\PushSiteTemplateJob;
use App\Models\CustomerInstallation;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use App\Services\ActivityLogger;
use App\Services\PublishedThemeAssignmentService;
use App\Services\ThemeBuilder\ThemeInstallGateway;

/**
 * ADR-088 — owns the "template binding" of an onboarded site: the theme the
 * wizard chose, assigned on the platform (InstallThemeStage) and then pushed
 * onto the live customer-site (pages incl. home, header, footer, styles)
 * through ThemeInstallGateway, the single theme install authority.
 *
 * State lives in `customer_installations.metadata.template_binding` so the
 * installation page can show it and an admin can retry it:
 *   pending_push → bound | failed
 * Every write merges into the latest metadata; GenerateEnvironmentStage and
 * others own their own keys there.
 */
class SiteTemplateBinder
{
    public const STATUS_PENDING_PUSH = 'pending_push';

    public const STATUS_BOUND = 'bound';

    public const STATUS_FAILED = 'failed';

    public function __construct(
        private readonly PublishedThemeAssignmentService $assignments,
        private readonly ThemeInstallGateway $gateway,
    ) {}

    public function binding(CustomerInstallation $installation): ?array
    {
        $binding = $installation->metadata['template_binding'] ?? null;

        return is_array($binding) ? $binding : null;
    }

    public function markPendingPush(CustomerInstallation $installation, Theme $theme): void
    {
        $this->write($installation, $theme, self::STATUS_PENDING_PUSH, null);
    }

    public function markFailed(CustomerInstallation $installation, ?Theme $theme, string $message): void
    {
        $this->write($installation, $theme, self::STATUS_FAILED, $message);
    }

    /** Queue the push when the installation is complete and a binding is waiting. */
    public function dispatchIfPending(CustomerInstallation $installation): bool
    {
        $installation->refresh();
        if (! $installation->isComplete() || ($this->binding($installation)['status'] ?? null) !== self::STATUS_PENDING_PUSH) {
            return false;
        }

        $this->dispatch($installation);

        return true;
    }

    public function dispatch(CustomerInstallation $installation): void
    {
        PushSiteTemplateJob::dispatch($installation->id)
            ->onConnection('customer-install')
            ->onQueue('customer-install');
    }

    /**
     * Assign (idempotent) and push the bound theme onto the live site.
     * Returns true when the customer-site accepted the theme.
     */
    public function bind(CustomerInstallation $installation): bool
    {
        $installation->refresh();
        $binding = $this->binding($installation);
        $theme = isset($binding['theme_id']) ? Theme::find($binding['theme_id']) : null;
        $site = $installation->site_id ? Site::find($installation->site_id) : null;

        if ($theme === null || $site === null) {
            $this->markFailed($installation, $theme, $theme === null ? 'قالب انتخاب‌شده یافت نشد.' : 'سایت این نصب یافت نشد.');

            return false;
        }

        try {
            $this->assignments->assign($site, $theme, ThemeAssignment::SOURCE_ADMIN, assignedBy: $installation->installer_user_id);
        } catch (\Throwable $e) {
            $this->markFailed($installation, $theme, $e->getMessage());

            return false;
        }

        $result = $this->gateway->install($theme, $site);
        if (! $result->success) {
            $this->markFailed($installation, $theme, (string) $result->message);

            return false;
        }

        $this->write($installation, $theme, self::STATUS_BOUND, null);
        ActivityLogger::log('onboarding.template_bound', "اعمال قالب «{$theme->name}» روی سایت {$site->domain}", $site, [
            'installation_id' => $installation->id,
            'theme_id'        => $theme->id,
        ]);

        return true;
    }

    private function write(CustomerInstallation $installation, ?Theme $theme, string $status, ?string $message): void
    {
        $installation->refresh();
        $metadata = $installation->metadata ?? [];
        $previous = is_array($metadata['template_binding'] ?? null) ? $metadata['template_binding'] : [];

        $metadata['template_binding'] = [
            'theme_id'   => $theme?->id ?? ($previous['theme_id'] ?? null),
            'theme_name' => $theme?->name ?? ($previous['theme_name'] ?? null),
            'status'     => $status,
            'message'    => $message !== null ? mb_substr($message, 0, 500) : null,
            'attempts'   => (int) ($previous['attempts'] ?? 0) + ($status === self::STATUS_PENDING_PUSH ? 0 : 1),
            'updated_at' => now()->toIso8601String(),
        ];

        $installation->forceFill(['metadata' => $metadata])->save();
    }
}
