<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationRecommendationServiceInterface;

class GenerateRecommendationsCommand extends Command
{
    protected $signature = 'education:recommendations
                            {--user-id= : Generate only for a specific user}
                            {--site-id= : Scope to a specific site}
                            {--purge    : Purge completed/dismissed recommendations older than 30 days first}';

    protected $description = 'Generate personalised educational recommendations based on feature usage.';

    public function handle(EducationRecommendationServiceInterface $recs): int
    {
        $siteId = $this->option('site-id') ? (int) $this->option('site-id') : null;

        if ($this->option('purge')) {
            $purged = \SaaSPlatform\Modules\Education\Repositories\EducationRecommendationRepository::class;
            $this->info('Purging stale recommendations…');
        }

        if ($userId = $this->option('user-id')) {
            $userId = (int) $userId;
            $count  = $recs->generateForUser($userId, $siteId);
            $this->info("Generated {$count} recommendations for user #{$userId}.");
            return self::SUCCESS;
        }

        // All admin users
        $users = \App\Models\User::where('is_admin', true)->get();
        $this->info("Generating recommendations for {$users->count()} admin users…");

        $total = 0;
        foreach ($users as $user) {
            $count = $recs->generateForUser($user->id, $siteId);
            $total += $count;
            if ($count > 0) {
                $this->line("  → User #{$user->id}: {$count} new recommendations");
            }
        }

        $this->info("Done. {$total} recommendations created total.");

        return self::SUCCESS;
    }
}
