<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\TenantPruneAuditLog;
use App\Services\DevOps\ByteFormatter;
use App\Services\DevOps\Exceptions\PruneInProgressException;
use App\Services\DevOps\ServerMetricsCollector;
use App\Services\DevOps\TenantResourceGovernor;
use App\Services\DevOps\TenantStoragePruner;
use App\Services\PersianFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * DevOps resource governor dashboard (/admin/devops/resources).
 *
 * Every endpoint needs admin access (route middleware); re-measuring and
 * quota edits stay at that level, like SiteResourceGovernanceController's
 * recalculate/updateLimits. Preview and prune additionally need
 * TenantResourceGovernor::canPrune(). A prune must follow a dry-run preview:
 * the preview issues a single-use token bound to the site, user and profile,
 * and the prune is refused without it. The prune itself re-scans the host,
 * so the token proves intent, not the file list.
 */
class DevOpsResourceController extends Controller
{
    private const TOKEN_PREFIX = 'devops:prune-token:';

    public function __construct(
        private readonly TenantResourceGovernor $governor,
        private readonly TenantStoragePruner    $pruner,
        private readonly ServerMetricsCollector $metrics,
    ) {}

    public function index(Request $request)
    {
        $search  = $this->validatedSearch($request);
        $payload = $this->payload($search);

        return view('admin.devops.index', [
            'payload'   => $payload,
            'search'    => $search,
            'canPrune'  => TenantResourceGovernor::canPrune($request->user()),
            'profiles'  => TenantStoragePruner::profiles(),
        ]);
    }

    public function metrics(Request $request): JsonResponse
    {
        return response()->json($this->payload($this->validatedSearch($request), (bool) $request->boolean('fresh')));
    }

    public function show(Site $site): JsonResponse
    {
        $row = collect($this->governor->tenantMatrix())->firstWhere('site_id', $site->id);
        abort_if($row === null, 404);

        $history = TenantPruneAuditLog::where('site_id', $site->id)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (TenantPruneAuditLog $log) => $this->presentLog($log, false));

        return response()->json([
            'tenant'  => $this->presentRow($row),
            'history' => $history,
        ]);
    }

    public function audit(Site $site): JsonResponse
    {
        $result = $this->governor->audit($site);

        if (!$result['ok']) {
            return response()->json(['message' => 'اندازه‌گیری ناموفق بود: ' . $result['error']], 422);
        }

        $row = collect($this->governor->tenantMatrix())->firstWhere('site_id', $site->id);

        return response()->json(['tenant' => $row ? $this->presentRow($row) : null]);
    }

    public function preview(Request $request, Site $site): JsonResponse
    {
        $this->authorizePrune($request);
        $profile = $this->validatedProfile($request);

        try {
            $log = $this->pruner->preview($site, $profile, TenantPruneAuditLog::TRIGGER_ADMIN, $request->user()->id);
        } catch (PruneInProgressException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        if ($log->status === TenantPruneAuditLog::STATUS_FAILED) {
            return response()->json([
                'message' => 'پیش‌نمایش ناموفق بود: ' . $log->error,
                'audit'   => $this->presentLog($log),
            ], 422);
        }

        $token = Str::random(48);
        Cache::put(self::TOKEN_PREFIX . $token, [
            'site_id'  => $site->id,
            'user_id'  => $request->user()->id,
            'profile'  => $profile,
            'audit_id' => $log->id,
        ], now()->addMinutes((int) config('resource_governance.prune.confirmation_ttl_minutes', 10)));

        return response()->json([
            'audit'              => $this->presentLog($log),
            'confirmation_token' => $token,
        ]);
    }

    public function prune(Request $request, Site $site): JsonResponse
    {
        $this->authorizePrune($request);

        $data = $request->validate([
            'confirmation_token' => ['required', 'string', 'size:48', 'alpha_num'],
        ]);

        // Single use: pulled before the run, so a retry needs a fresh preview.
        $grant = Cache::pull(self::TOKEN_PREFIX . $data['confirmation_token']);

        if (!is_array($grant) || (int) $grant['site_id'] !== $site->id || (int) $grant['user_id'] !== (int) $request->user()->id) {
            return response()->json(['message' => 'ابتدا پیش‌نمایش را اجرا کنید؛ تأییدیه نامعتبر یا منقضی است.'], 422);
        }

        try {
            @set_time_limit(300);
            $log = $this->pruner->execute($site, $grant['profile'], TenantPruneAuditLog::TRIGGER_ADMIN, $request->user()->id);
        } catch (PruneInProgressException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $status = $log->status === TenantPruneAuditLog::STATUS_FAILED ? 422 : 200;

        if ($log->status !== TenantPruneAuditLog::STATUS_FAILED) {
            // Refresh the measurement so the matrix shows the reclaimed space.
            $this->governor->audit($site);
        }

        return response()->json([
            'message' => match ($log->status) {
                TenantPruneAuditLog::STATUS_COMPLETED => 'پاک‌سازی با موفقیت انجام شد.',
                TenantPruneAuditLog::STATUS_PARTIAL   => 'پاک‌سازی تا حدی انجام شد؛ جزئیات خطا را ببینید.',
                default                               => 'پاک‌سازی ناموفق بود: ' . $log->error,
            },
            'audit'   => $this->presentLog($log),
            'preview_audit_id' => $grant['audit_id'],
        ], $status);
    }

    public function updateQuota(Request $request, Site $site): JsonResponse
    {
        $data = $request->validate([
            'disk_soft_limit_gb' => ['required', 'numeric', 'min:0.1', 'max:10000'],
            'disk_hard_limit_gb' => ['nullable', 'numeric', 'max:10000', 'gte:disk_soft_limit_gb'],
            'cpu_cores_limit'    => ['nullable', 'numeric', 'min:0.1', 'max:256'],
            'memory_limit_mb'    => ['nullable', 'integer', 'min:64', 'max:1048576'],
        ], [], [
            'disk_soft_limit_gb' => 'سهمیهٔ نرم دیسک',
            'disk_hard_limit_gb' => 'سهمیهٔ سخت دیسک',
            'cpu_cores_limit'    => 'سقف هستهٔ پردازنده',
            'memory_limit_mb'    => 'سقف حافظه',
        ]);

        $this->governor->updateQuota($site, [
            'disk_soft_limit_bytes' => (int) round($data['disk_soft_limit_gb'] * 1073741824),
            'disk_hard_limit_bytes' => isset($data['disk_hard_limit_gb']) ? (int) round($data['disk_hard_limit_gb'] * 1073741824) : null,
            'cpu_cores_limit'       => $data['cpu_cores_limit'] ?? null,
            'memory_limit_bytes'    => isset($data['memory_limit_mb']) ? (int) $data['memory_limit_mb'] * 1048576 : null,
        ]);

        $row = collect($this->governor->tenantMatrix())->firstWhere('site_id', $site->id);

        return response()->json([
            'message' => "سهمیهٔ سایت {$site->title} به‌روزرسانی شد.",
            'tenant'  => $row ? $this->presentRow($row) : null,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function authorizePrune(Request $request): void
    {
        abort_unless(TenantResourceGovernor::canPrune($request->user()), 403, 'دسترسی پاک‌سازی فضای دیسک سایت‌ها را ندارید.');
    }

    private function validatedSearch(Request $request): ?string
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $q = trim((string) $request->query('q', ''));

        return $q === '' ? null : $q;
    }

    private function validatedProfile(Request $request): string
    {
        $data = $request->validate([
            'profile' => ['nullable', 'string', 'in:' . implode(',', TenantStoragePruner::profiles())],
        ]);

        return $data['profile'] ?? 'routine';
    }

    private function payload(?string $search, bool $fresh = false): array
    {
        $matrix = $this->governor->tenantMatrix($search);
        $pool   = $this->governor->poolSummary($search === null ? $matrix : $this->governor->tenantMatrix());

        return [
            'server'  => $this->presentServer($this->metrics->collect($fresh)),
            'pool'    => $this->presentPool($pool),
            'tenants' => array_map(fn ($row) => $this->presentRow($row), $matrix),
            'thresholds' => [
                'warning_percent'  => (int) config('resource_governance.alert_warning_percent', 80),
                'critical_percent' => (int) config('resource_governance.alert_critical_percent', 90),
            ],
            'auto_prune_enabled' => (bool) config('resource_governance.prune.auto_enabled', false),
            'generated_at'       => now()->toIso8601String(),
        ];
    }

    private function presentServer(array $m): array
    {
        $m['disk']['total_human']   = ByteFormatter::fa($m['disk']['total_bytes']);
        $m['disk']['used_human']    = ByteFormatter::fa($m['disk']['used_bytes']);
        $m['disk']['free_human']    = ByteFormatter::fa($m['disk']['free_bytes']);
        $m['memory']['total_human'] = ByteFormatter::fa($m['memory']['total_bytes']);
        $m['memory']['used_human']  = ByteFormatter::fa($m['memory']['used_bytes']);

        $m['disk']['percent_human']   = $this->percent($m['disk']['used_percent']);
        $m['memory']['percent_human'] = $this->percent($m['memory']['used_percent']);
        $m['cpu']['percent_human']    = $this->percent($m['cpu']['percent']);
        $m['cpu']['cores_human']      = $m['cpu']['cores'] !== null ? PersianFormatter::num($m['cpu']['cores']) : '—';
        $m['cpu']['load_human']       = $m['cpu']['load_1m'] !== null
            ? implode(' / ', array_map(fn ($v) => PersianFormatter::num((float) $v, 2), [$m['cpu']['load_1m'], $m['cpu']['load_5m'], $m['cpu']['load_15m']]))
            : '—';

        return $m;
    }

    private function percent(int|float|null $value): string
    {
        return $value === null ? '—' : PersianFormatter::num((float) $value, fmod((float) $value, 1.0) === 0.0 ? 0 : 1) . '٪';
    }

    private function presentPool(array $pool): array
    {
        foreach (['allocated_bytes', 'consumed_bytes', 'physical_bytes', 'physical_used_bytes', 'physical_free_bytes'] as $key) {
            $pool[str_replace('_bytes', '_human', $key)] = ByteFormatter::fa($pool[$key]);
        }

        $pool['overcommit_human'] = $pool['overcommit_ratio'] !== null
            ? PersianFormatter::num($pool['overcommit_ratio'], 2) . '×'
            : '—';
        $pool['levels_human'] = array_map(fn ($n) => PersianFormatter::num($n), $pool['levels']);
        $pool['sites_human']  = PersianFormatter::num($pool['sites_total']);
        $pool['servers_human'] = PersianFormatter::num($pool['servers_measured']) . ' از ' . PersianFormatter::num($pool['servers_total']);

        return $pool;
    }

    private function presentRow(array $row): array
    {
        $row['disk_usage_human'] = ByteFormatter::fa($row['disk_usage_bytes']);
        $row['soft_limit_human'] = ByteFormatter::fa($row['soft_limit_bytes']);
        $row['hard_limit_human'] = $row['hard_limit_bytes'] > 0 ? ByteFormatter::fa($row['hard_limit_bytes']) : null;
        $row['memory_limit_human'] = ByteFormatter::fa($row['memory_limit_bytes']);
        $row['cpu_cores_human']  = $row['cpu_cores_limit'] !== null ? PersianFormatter::num($row['cpu_cores_limit'], 1) : '—';
        $row['percent_human']    = $this->percent($row['percent']);
        $row['audited_human']    = $row['audited_at'] ? PersianFormatter::ago($row['audited_at']) : 'هنوز بررسی نشده';

        return $row;
    }

    private function presentLog(TenantPruneAuditLog $log, bool $withSamples = true): array
    {
        $categories = [];
        foreach (($log->details['categories'] ?? []) as $key => $category) {
            $categories[] = [
                'key'    => $key,
                'label'  => $category['label'],
                'count'  => (int) $category['count'],
                'bytes'  => (int) $category['bytes'],
                'human'  => ByteFormatter::fa((int) $category['bytes']),
                'count_human' => PersianFormatter::num((int) $category['count']),
                'sample' => $withSamples ? ($category['sample'] ?? []) : [],
            ];
        }

        return [
            'id'                 => $log->id,
            'mode'               => $log->mode,
            'profile'            => $log->profile,
            'status'             => $log->status,
            'triggered_by'       => $log->triggered_by,
            'reclaimed_bytes'    => (int) $log->reclaimed_bytes,
            'reclaimed_human'    => ByteFormatter::fa((int) $log->reclaimed_bytes),
            'pruned_items_count' => (int) $log->pruned_items_count,
            'categories'         => $categories,
            'capped'             => (bool) ($log->details['capped'] ?? false),
            'skipped'            => $log->details['skipped'] ?? [],
            'pruned_items_human' => PersianFormatter::num((int) $log->pruned_items_count),
            'error'              => $log->error,
            'created_at'         => $log->created_at?->toIso8601String(),
            'created_human'      => $log->created_at ? PersianFormatter::datetime($log->created_at) : '—',
        ];
    }
}
