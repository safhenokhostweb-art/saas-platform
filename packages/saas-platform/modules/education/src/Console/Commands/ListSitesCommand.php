<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lists all customer sites with their IDs, display names, domains, and status.
 * Required for EDU-12 administration — use site_id values with sync/verify commands.
 */
class ListSitesCommand extends Command
{
    protected $signature = 'education:list-sites
                            {--active   : Show only active sites}
                            {--inactive : Show only inactive sites}
                            {--json     : Output as JSON}';

    protected $description = 'List all customer sites with IDs, names, domains, and education sync status.';

    public function handle(): int
    {
        $query = DB::table('sites')
            ->leftJoin('edu_sync_logs', function ($join) {
                $join->on('sites.id', '=', 'edu_sync_logs.site_id')
                     ->whereRaw('edu_sync_logs.id = (SELECT MAX(id) FROM edu_sync_logs WHERE site_id = sites.id)');
            })
            ->whereNull('sites.deleted_at')
            ->select([
                'sites.id',
                'sites.title',
                'sites.domain',
                'sites.is_active',
                DB::raw("COALESCE(edu_sync_logs.ran_at, '—') as last_synced"),
                DB::raw("COALESCE(edu_sync_logs.articles_synced, 0) as articles_synced"),
                DB::raw("COALESCE(edu_sync_logs.status, '—') as last_sync_status"),
            ])
            ->orderBy('sites.id');

        if ($this->option('active')) {
            $query->where('sites.is_active', true);
        } elseif ($this->option('inactive')) {
            $query->where('sites.is_active', false);
        }

        $sites = $query->get();

        if ($sites->isEmpty()) {
            $this->warn('No sites found.');
            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line(json_encode($sites->map(fn($s) => [
                'id'             => $s->id,
                'title'          => $s->title,
                'domain'         => $s->domain,
                'is_active'      => (bool) $s->is_active,
                'last_synced'    => $s->last_synced,
                'articles_synced'=> $s->articles_synced,
                'sync_status'    => $s->last_sync_status,
            ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->line('');
        $this->info('Customer Sites — Education Sync Status');
        $this->line('');

        $rows = $sites->map(fn($s) => [
            $s->id,
            $s->title,
            $s->domain ?? '—',
            $s->is_active ? '<fg=green>active</>' : '<fg=red>inactive</>',
            $s->articles_synced > 0 ? $s->articles_synced : '—',
            $s->last_synced !== '—' ? substr($s->last_synced, 0, 16) : '—',
            $s->last_sync_status !== '—' ? $s->last_sync_status : '—',
        ]);

        $this->table(
            ['ID', 'Title', 'Domain', 'Status', 'Articles Synced', 'Last Sync', 'Sync Status'],
            $rows
        );

        $this->line('');
        $this->line('<fg=gray>Usage examples:</>');
        $this->line('  php artisan education:sync-customer <ID>');
        $this->line('  php artisan education:verify-sync <ID>');
        $this->line('  php artisan education:verify-sync <ID> --fix');
        $this->line('  php artisan education:sync-all-customers --active-only');
        $this->line('');

        return self::SUCCESS;
    }
}
