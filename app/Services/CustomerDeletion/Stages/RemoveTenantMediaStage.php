<?php

namespace App\Services\CustomerDeletion\Stages;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;
use App\Services\CustomerDeletion\Guards\ObjectPrefixGuard;
use App\Services\CustomerDeletion\Purge\TenantObjectPurger;

/**
 * STAGE: REMOVE_TENANT_MEDIA (ADR-090)
 *
 * When the operator ticked «حذف مدیا», deletes everything under the site's
 * own prefix in the tenant media bucket — sites/{id}/ (media + private) —
 * with the dedicated tenant-media key. Non-critical but retryable: a failure
 * keeps the platform records (REMOVE_PLATFORM_RECORDS requires a clean run).
 */
class RemoveTenantMediaStage extends AbstractDeleteStage
{
    public function __construct(private readonly TenantObjectPurger $purger) {}

    public function name(): string { return 'REMOVE_TENANT_MEDIA'; }
    public function label(): string { return 'حذف مدیای آروان'; }
    public function isCritical(): bool { return false; }
    public function estimatedSeconds(): int { return 30; }

    public function execute(DeleteContext $ctx): DeleteStageResult
    {
        return $this->run($ctx, function (DeleteContext $ctx) {
            if (!$ctx->purgeMedia) {
                return DeleteStageResult::skipped($this->name(), 'حذف مدیا انتخاب نشده — فایل‌های مدیا حفظ می‌شوند');
            }
            if (!$ctx->siteId) {
                return DeleteStageResult::failure($this->name(), 'شناسهٔ سایت برای حذف مدیا معلوم نیست');
            }

            $results = [];
            foreach (ObjectPrefixGuard::mediaPrefixes((int) $ctx->siteId) as $prefix) {
                $results[] = $this->purger->purge(
                    TenantObjectPurger::TARGET_MEDIA,
                    ObjectPrefixGuard::assertMediaPrefix($prefix, (int) $ctx->siteId),
                );
            }

            return DeleteStageResult::success($this->name(), ['removed' => $results]);
        });
    }

    public function verify(DeleteContext $ctx): array
    {
        if (!$ctx->purgeMedia || !$ctx->siteId) {
            return [['check' => 'Tenant media', 'passed' => true, 'detail' => 'not selected — preserved']];
        }

        $checks = [];
        foreach (ObjectPrefixGuard::mediaPrefixes((int) $ctx->siteId) as $prefix) {
            try {
                $left = $this->purger->inventory(TenantObjectPurger::TARGET_MEDIA, $prefix)['objects'];
                $checks[] = ['check' => "Tenant media cleared: {$prefix}", 'passed' => $left === 0, 'detail' => "{$left} objects remaining"];
            } catch (\Throwable $e) {
                $checks[] = ['check' => "Tenant media cleared: {$prefix}", 'passed' => false, 'detail' => 'unverifiable: ' . $e->getMessage()];
            }
        }

        return $checks;
    }
}
