<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Regression guard for the 2026-08-06 "validation bypass" incident: the
 * RestoreService/RestoreWizardController fix was correctly written to disk
 * and traced end-to-end (single route -> single controller -> validateBackup()
 * hard-fail -> single job -> executeTenantDatabaseRestore()'s own independent
 * gate), yet production kept executing the pre-patch behavior. deploy.sh's
 * `optimize:clear`/`optimize` only refresh Laravel's own config/route/view
 * caches — neither touches OPcache nor restarts any long-lived PHP process
 * (PHP-FPM workers, the restore-worker service), so a file being updated on
 * disk does not guarantee the code actually being *executed* changes. This
 * test just asserts the deploy script still contains the reload steps added
 * to close that gap — it does not (and cannot) verify OPcache/systemd
 * behavior itself.
 */
class DeploymentPipelineTest extends TestCase
{
    public function test_deploy_script_restarts_queue_workers_unconditionally(): void
    {
        $script = file_get_contents(base_path('deploy.sh'));

        // Must be an unconditional artisan call, not just a printed reminder
        // gated behind `command -v supervisorctl` (which is what the
        // pre-incident script did — a warning nobody has to act on).
        $this->assertMatchesRegularExpression(
            '/run_step\s+"php artisan queue:restart"\s+php artisan queue:restart/',
            $script,
            'deploy.sh must unconditionally run `php artisan queue:restart` so queue/restore workers reload new code after every deploy.'
        );
    }

    public function test_deploy_script_attempts_to_invalidate_opcache_via_fpm_reload(): void
    {
        $script = file_get_contents(base_path('deploy.sh'));

        $this->assertStringContainsString('systemctl reload', $script);
        $this->assertStringContainsString('fpm', $script);
    }

    public function test_deploy_script_restarts_the_dedicated_restore_worker_service(): void
    {
        $script = file_get_contents(base_path('deploy.sh'));

        $this->assertStringContainsString('restore-worker', $script);
    }
}
