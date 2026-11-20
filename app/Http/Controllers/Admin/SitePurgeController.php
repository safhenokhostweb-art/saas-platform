<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SitePurgeRequest;
use App\Jobs\CustomerDeletionPipelineJob;
use App\Models\CustomerDeletion;
use App\Models\Site;
use App\Services\ActivityLogger;
use App\Services\CustomerDeletion\Dto\DeletePolicy;
use App\Services\CustomerDeletion\Purge\PurgePlanBuilder;
use App\Services\CustomerDeletion\Purge\TenantObjectPurger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ADR-090 — «حذف کامل» of a customer site from the admin panel.
 *
 * GET  shows the dry-run: the exact plan (paths, vhost, certificates,
 *      database, Arvan prefixes with live object counts) for the chosen
 *      options, plus anything that will be left for a human.
 * POST rebuilds the plan, refuses if it differs from what was shown, and
 *      queues CustomerDeletionPipelineJob immediately (no grace period —
 *      owner decision 2026-11-20).
 *
 * Policy mapping: «حذف بکاپ‌های آروان» ticked → DELETE_EVERYTHING (every
 * backup, local and Arvan, is deleted and no final backup is kept);
 * otherwise NEVER_DELETE_BACKUP (a final backup is taken and all backups are
 * kept).
 */
class SitePurgeController extends Controller
{
    public function __construct(
        private readonly PurgePlanBuilder $plans,
        private readonly TenantObjectPurger $purger,
    ) {}

    public function show(Request $request, Site $site)
    {
        abort_unless(SitePurgeRequest::allowed($request->user()), 403, 'حذف کامل سایت فقط برای مدیر ارشد مجاز است.');

        $deleteBackups = $request->boolean('delete_backups');
        $deleteMedia   = $request->boolean('delete_media');

        $plan = $this->plans->forSite($site, $deleteBackups, $deleteMedia);

        return view('admin.sites.purge', [
            'site'          => $site,
            'plan'          => $plan,
            'planHash'      => PurgePlanBuilder::hash($plan),
            'manualItems'   => PurgePlanBuilder::manualItems($plan),
            'inventory'     => $this->inventory($plan),
            'deleteBackups' => $deleteBackups,
            'deleteMedia'   => $deleteMedia,
        ]);
    }

    public function store(SitePurgeRequest $request, Site $site)
    {
        $plan = $this->plans->forSite($site, $request->deleteBackups(), $request->deleteMedia());
        $hash = PurgePlanBuilder::hash($plan);

        $back = redirect()->route('admin.sites.purge.show', [
            'site'           => $site,
            'delete_backups' => (int) $request->deleteBackups(),
            'delete_media'   => (int) $request->deleteMedia(),
        ]);

        if ($plan['blockers'] !== []) {
            return $back->with('error', implode(' ', $plan['blockers']));
        }
        if (!hash_equals($hash, (string) $request->input('plan_hash'))) {
            return $back->with('error', 'وضعیت سایت از زمان نمایش پیش‌نمایش تغییر کرده است. پیش‌نمایش جدید را بررسی و دوباره تأیید کنید.');
        }

        $policy = $request->deleteBackups() ? DeletePolicy::DELETE_EVERYTHING : DeletePolicy::NEVER_DELETE_BACKUP;

        $deletion = CustomerDeletion::create([
            'installation_id'      => $plan['installation']['id'],
            'site_id'              => $site->id,
            'initiated_by'         => Auth::id(),
            'policy'               => $policy->value,
            'status'               => 'pending',
            'plan'                 => $plan,
            'plan_sha256'          => $hash,
            'options'              => ['delete_backups' => $request->deleteBackups(), 'delete_media' => $request->deleteMedia(), 'source' => 'site_purge'],
            'requested_ip'         => $request->ip(),
            'requested_user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        ActivityLogger::log('site.purge_requested', "درخواست حذف کامل سایت «{$site->title}» ({$site->domain})", $site, [
            'deletion_id'    => $deletion->id,
            'site_id'        => $site->id,
            'domain'         => $site->domain,
            'policy'         => $policy->value,
            'delete_backups' => $request->deleteBackups(),
            'delete_media'   => $request->deleteMedia(),
            'plan_sha256'    => $hash,
        ]);

        CustomerDeletionPipelineJob::dispatch($deletion->id)
            ->onConnection('customer-install')
            ->onQueue('customer-install');

        return redirect()
            ->route('admin.infrastructure.customer-deployment.deletions.progress', $deletion)
            ->with('success', 'حذف کامل سایت آغاز شد. پیشرفت را در همین صفحه دنبال کنید.');
    }

    /**
     * Live object counts per Arvan prefix for the preview — best effort: an
     * unreachable bucket shows an error line instead of breaking the page.
     */
    private function inventory(array $plan): array
    {
        $rows = [];
        foreach (['backups' => TenantObjectPurger::TARGET_BACKUPS, 'media' => TenantObjectPurger::TARGET_MEDIA] as $section => $target) {
            foreach ($plan[$section]['prefixes'] ?? [] as $prefix) {
                try {
                    $rows[$prefix] = $this->purger->inventory($target, $prefix);
                } catch (\Throwable $e) {
                    $rows[$prefix] = ['error' => mb_substr($e->getMessage(), 0, 200)];
                }
            }
        }

        return $rows;
    }
}
