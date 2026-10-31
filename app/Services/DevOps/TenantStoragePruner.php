<?php

namespace App\Services\DevOps;

use App\Models\Site;
use App\Models\TenantPruneAuditLog;
use App\Services\CustomerInstallation\Contracts\DeploymentDriverInterface;
use App\Services\DevOps\Exceptions\PruneInProgressException;
use App\Services\DevOps\Exceptions\UnsafePrunePathException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Audited cleanup of regenerable files inside a tenant's web root, over the
 * site's own deployment driver.
 *
 * Safety model:
 *  - Only the fixed categories in CATEGORIES are ever touched; each is a
 *    storage/ subdirectory that holds regenerable data (logs, framework
 *    cache, compiled views, expired sessions, upload temp, old DB dumps).
 *    Media, public/ and storage/app/public are protected by PrunePathGuard.
 *    The system /tmp is shared between tenants and is never touched.
 *  - The web root and every target are resolved with `realpath` on the host;
 *    a symlinked target is skipped rather than followed.
 *  - Every listed path is re-validated in PHP before deletion; execution
 *    deletes only files that pass, with explicit `rm -f -- <path>` batches.
 *  - A run writes its audit row before anything is deleted, and holds a
 *    per-site lock so two runs never overlap.
 */
class TenantStoragePruner
{
    /**
     * dirs:     storage-relative directories to scan
     * pattern:  basename regex a file must match
     * depth:    find -maxdepth (relative to each dir)
     * age:      profile key holding the minimum age
     * unit:     'days' | 'minutes'
     * keep:     profile key holding "always keep the newest N" (optional)
     */
    public const CATEGORIES = [
        'logs' => [
            'label'   => 'لاگ‌های برنامه',
            'dirs'    => ['storage/logs'],
            'pattern' => '/\.log(\.\d+)?(\.gz)?$/',
            'depth'   => 2,
            'age'     => 'logs_days',
            'unit'    => 'days',
        ],
        'cache' => [
            'label'   => 'کش فریم‌ورک',
            'dirs'    => ['storage/framework/cache/data'],
            'pattern' => '/^[^.]/',
            'depth'   => 3,
            'age'     => 'cache_days',
            'unit'    => 'days',
        ],
        'views' => [
            'label'   => 'ویوهای کامپایل‌شده',
            'dirs'    => ['storage/framework/views'],
            'pattern' => '/\.php$/',
            'depth'   => 1,
            'age'     => 'views_days',
            'unit'    => 'days',
        ],
        'sessions' => [
            'label'   => 'نشست‌های منقضی',
            'dirs'    => ['storage/framework/sessions'],
            'pattern' => '/^[A-Za-z0-9]+$/',
            'depth'   => 1,
            'age'     => 'sessions_minutes',
            'unit'    => 'minutes',
        ],
        'temp' => [
            'label'   => 'فایل‌های موقت آپلود',
            'dirs'    => ['storage/app/livewire-tmp', 'storage/app/tmp'],
            'pattern' => '/^[^.]/',
            'depth'   => 2,
            'age'     => 'temp_days',
            'unit'    => 'days',
        ],
        'dumps' => [
            'label'   => 'نسخه‌های قدیمی پایگاه داده',
            'dirs'    => ['storage/backups'],
            'pattern' => '/^backup_.+\.sql(\.gz)?$/',
            'depth'   => 1,
            'age'     => 'dumps_days',
            'unit'    => 'days',
            'keep'    => 'dumps_keep_newest',
        ],
    ];

    private const SAMPLE_SIZE = 25;

    public function __construct(
        private readonly TenantTargetResolver $targets,
        private readonly PrunePathGuard       $guard,
    ) {}

    public function preview(Site $site, string $profile = 'routine', string $triggeredBy = TenantPruneAuditLog::TRIGGER_ADMIN, ?int $userId = null): TenantPruneAuditLog
    {
        return $this->run($site, $profile, true, $triggeredBy, $userId);
    }

    public function execute(Site $site, string $profile = 'routine', string $triggeredBy = TenantPruneAuditLog::TRIGGER_ADMIN, ?int $userId = null): TenantPruneAuditLog
    {
        return $this->run($site, $profile, false, $triggeredBy, $userId);
    }

    public static function profiles(): array
    {
        return array_keys(config('resource_governance.prune.profiles', []));
    }

    /**
     * @throws \InvalidArgumentException   unknown profile or trigger
     * @throws PruneInProgressException    another run holds this site's lock
     */
    public function run(Site $site, string $profile, bool $dryRun, string $triggeredBy, ?int $userId = null): TenantPruneAuditLog
    {
        $policy = config("resource_governance.prune.profiles.{$profile}");
        if (!is_array($policy)) {
            throw new \InvalidArgumentException("Unknown prune profile: {$profile}");
        }
        if (!in_array($triggeredBy, [TenantPruneAuditLog::TRIGGER_AUTO, TenantPruneAuditLog::TRIGGER_ADMIN], true)) {
            throw new \InvalidArgumentException("Unknown prune trigger: {$triggeredBy}");
        }

        $lock = Cache::lock("devops:prune:site:{$site->id}", 900);
        if (!$lock->get()) {
            throw new PruneInProgressException('پاک‌سازی دیگری برای این سایت در حال اجراست.');
        }

        $log = TenantPruneAuditLog::create([
            'site_id'      => $site->id,
            'triggered_by' => $triggeredBy,
            'user_id'      => $userId,
            'mode'         => $dryRun ? TenantPruneAuditLog::MODE_DRY_RUN : TenantPruneAuditLog::MODE_EXECUTE,
            'profile'      => $profile,
            'status'       => TenantPruneAuditLog::STATUS_RUNNING,
            'started_at'   => now(),
        ]);

        $driver = null;

        try {
            $target = $this->targets->resolve($site);
            $driver = $target->driver;
            $driver->connect();

            $root  = $this->resolveRoot($driver, $target->webRoot);
            $scan  = $this->scan($driver, $root, $policy);

            $result = $dryRun
                ? ['status' => TenantPruneAuditLog::STATUS_COMPLETED, 'reclaimed' => $scan['bytes'], 'count' => $scan['count'], 'errors' => []]
                : $this->delete($driver, $scan);

            $log->update([
                'status'             => $result['status'],
                'reclaimed_bytes'    => $result['reclaimed'],
                'pruned_items_count' => $result['count'],
                'details'            => [
                    'web_root'   => $root,
                    'categories' => $scan['summary'],
                    'skipped'    => $scan['skipped'],
                    'rejected'   => $scan['rejected'],
                    'capped'     => $scan['capped'],
                    'errors'     => $result['errors'],
                ],
                'error'              => $result['errors'] ? mb_substr(implode(' | ', $result['errors']), 0, 1000) : null,
                'finished_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('devops: prune failed', ['site_id' => $site->id, 'audit_id' => $log->id, 'error' => $e->getMessage()]);

            $log->update([
                'status'      => TenantPruneAuditLog::STATUS_FAILED,
                'error'       => mb_substr($e->getMessage(), 0, 1000),
                'finished_at' => now(),
            ]);
        } finally {
            try {
                $driver?->disconnect();
            } catch (\Throwable) {
                // Disconnect failures must not mask the run's outcome.
            }
            $lock->release();
        }

        return $log->fresh();
    }

    /**
     * Resolve the web root on the host and require it to be a Laravel app.
     * A symlinked root (e.g. a release "current" link) is accepted, but the
     * resolved real path must pass the same validation as the configured one.
     */
    private function resolveRoot(DeploymentDriverInterface $driver, string $webRoot): string
    {
        $configured = $this->guard->assertSafeWebRoot($webRoot);
        $q = PosixShell::quote($configured);

        $probe = $driver->execute("realpath -e {$q} && test -f {$q}/artisan && test -d {$q}/storage && echo LARAVEL_OK");
        $lines = array_values(array_filter(array_map('trim', explode("\n", $probe->stdout))));

        if ($probe->failed() || end($lines) !== 'LARAVEL_OK' || count($lines) < 2) {
            throw new UnsafePrunePathException("Web root is missing or is not a Laravel application: {$configured}");
        }

        return $this->guard->assertSafeWebRoot($lines[0]);
    }

    /**
     * @return array{candidates: array<int, array{path:string,size:int,category:string}>, bytes:int, count:int, summary:array, skipped:array, rejected:array, capped:bool}
     */
    private function scan(DeploymentDriverInterface $driver, string $root, array $policy): array
    {
        $cap        = max(1, (int) config('resource_governance.prune.max_files_per_run', 20000));
        $timeout    = max(5, (int) config('resource_governance.disk.remote_timeout_seconds', 120));
        $candidates = [];
        $summary    = [];
        $skipped    = [];
        $rejected   = [];
        $capped     = false;

        foreach (self::CATEGORIES as $key => $category) {
            $summary[$key] = ['label' => $category['label'], 'count' => 0, 'bytes' => 0, 'sample' => []];
            $files = [];

            foreach ($category['dirs'] as $relative) {
                $dir = $this->guard->targetDirectory($root, $relative);
                $q   = PosixShell::quote($dir);

                $real = $driver->execute("test -d {$q} && realpath -e {$q}");
                if ($real->failed() || trim($real->stdout) === '') {
                    continue; // directory not present on this site
                }
                if (trim($real->stdout) !== $dir) {
                    $skipped[] = ['dir' => $dir, 'reason' => 'symlinked_target'];
                    continue;
                }

                $files = array_merge($files, $this->listFiles($driver, $dir, $category, $policy, $root, $timeout, $cap, $rejected));
            }

            $selected = $this->applyRetention($files, $category, $policy);

            foreach ($selected as $file) {
                if (count($candidates) >= $cap) {
                    $capped = true;
                    break 2;
                }
                $candidates[] = ['path' => $file['path'], 'size' => $file['size'], 'category' => $key];
                $summary[$key]['count']++;
                $summary[$key]['bytes'] += $file['size'];
                if (count($summary[$key]['sample']) < self::SAMPLE_SIZE) {
                    $summary[$key]['sample'][] = substr($file['path'], strlen($root) + 1);
                }
            }
        }

        return [
            'candidates' => $candidates,
            'bytes'      => array_sum(array_column($candidates, 'size')),
            'count'      => count($candidates),
            'summary'    => $summary,
            'skipped'    => $skipped,
            'rejected'   => array_slice($rejected, 0, self::SAMPLE_SIZE),
            'capped'     => $capped,
        ];
    }

    /**
     * List regular files (no symlinks followed, one filesystem) as
     * NUL-separated "mtime<TAB>size<TAB>path" records, then validate each.
     *
     * @return array<int, array{path:string,size:int,mtime:float}>
     */
    private function listFiles(DeploymentDriverInterface $driver, string $dir, array $category, array $policy, string $root, int $timeout, int $cap, array &$rejected): array
    {
        $ageFilter = '';
        if (empty($category['keep'])) {
            // Without a keep-newest rule the age filter can run on the host.
            $minutes   = $this->minAgeMinutes($category, $policy);
            $ageFilter = '-mmin +' . $minutes . ' ';
        }

        $q       = PosixShell::quote($dir);
        $limit   = $cap + 1;
        $command = "timeout {$timeout} find {$q} -mindepth 1 -maxdepth {$category['depth']} -xdev -type f ! -name .gitignore "
                 . $ageFilter . "-printf '%T@\\t%s\\t%p\\0' 2>/dev/null | head -z -n {$limit}";

        $out   = $driver->execute($command);
        $files = [];

        foreach (explode("\0", $out->stdout) as $record) {
            if ($record === '') {
                continue;
            }

            $parts = explode("\t", $record, 3);
            if (count($parts) !== 3 || !is_numeric($parts[0]) || !ctype_digit($parts[1])) {
                $rejected[] = ['path' => mb_substr($record, 0, 200), 'reason' => 'unparseable'];
                continue;
            }

            [$mtime, $size, $path] = $parts;

            $reason = $this->guard->rejectionReason($path, $dir, $root);
            if ($reason !== null) {
                $rejected[] = ['path' => mb_substr($path, 0, 200), 'reason' => $reason];
                continue;
            }

            if (!preg_match($category['pattern'], basename($path))) {
                continue;
            }

            $files[] = ['path' => $path, 'size' => (int) $size, 'mtime' => (float) $mtime];
        }

        return $files;
    }

    /**
     * Apply the age threshold (again, in PHP, so host clocks or a missing
     * -mmin cannot widen the set) and the optional keep-newest rule.
     */
    private function applyRetention(array $files, array $category, array $policy): array
    {
        if (!empty($category['keep'])) {
            usort($files, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);
            $files = array_slice($files, max(0, (int) ($policy[$category['keep']] ?? 0)));
        }

        $cutoff = now()->getTimestamp() - $this->minAgeMinutes($category, $policy) * 60;

        return array_values(array_filter($files, fn ($f) => $f['mtime'] < $cutoff));
    }

    private function minAgeMinutes(array $category, array $policy): int
    {
        $value = max(1, (int) ($policy[$category['age']] ?? 0));

        return $category['unit'] === 'minutes' ? $value : $value * 1440;
    }

    /**
     * @return array{status:string, reclaimed:int, count:int, errors:array}
     */
    private function delete(DeploymentDriverInterface $driver, array $scan): array
    {
        $batchSize = max(1, (int) config('resource_governance.prune.delete_batch_size', 200));
        $reclaimed = 0;
        $count     = 0;
        $errors    = [];

        foreach (array_chunk($scan['candidates'], $batchSize) as $batch) {
            $args   = implode(' ', array_map(fn ($c) => PosixShell::quote($c['path']), $batch));
            $result = $driver->execute("rm -f -- {$args}");

            if ($result->failed()) {
                $errors[] = 'rm exit ' . $result->exitCode . ': ' . mb_substr($result->output(), 0, 200);
                continue;
            }

            $reclaimed += array_sum(array_column($batch, 'size'));
            $count     += count($batch);
        }

        $status = match (true) {
            $errors === []         => TenantPruneAuditLog::STATUS_COMPLETED,
            $count > 0             => TenantPruneAuditLog::STATUS_PARTIAL,
            default                => TenantPruneAuditLog::STATUS_FAILED,
        };

        return ['status' => $status, 'reclaimed' => $reclaimed, 'count' => $count, 'errors' => $errors];
    }
}
