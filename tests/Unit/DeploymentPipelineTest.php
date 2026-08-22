<?php

namespace Tests\Unit;

use Tests\TestCase;

/** Regression guards for Deployment System artifact authority. */
class DeploymentPipelineTest extends TestCase
{
    public function test_legacy_deploy_script_is_fail_closed(): void
    {
        $script = file_get_contents(base_path('deploy.sh'));

        $this->assertStringContainsString('Legacy direct deployment is blocked', $script);
        $this->assertStringContainsString('exit 1', $script);
        $this->assertStringNotContainsString('git pull ', $script);
        $this->assertStringNotContainsString('composer install ', $script);
        $this->assertStringNotContainsString('npm ci ', $script);
    }

    public function test_artifact_runbook_reloads_cached_and_long_running_code(): void
    {
        $runbook = file_get_contents(base_path('docs/phase-8.2/deployment-runbook.md'));

        $this->assertStringContainsString('php artisan optimize', $runbook);
        $this->assertStringContainsString('php artisan queue:restart', $runbook);
        $this->assertStringContainsString('/health/ready', $runbook);
    }

    public function test_rollback_runbook_uses_validated_artifacts_not_production_git(): void
    {
        $runbook = file_get_contents(base_path('docs/phase-8.2/rollback-runbook.md'));

        $this->assertStringContainsString('validated artifact', $runbook);
        $this->assertStringContainsString('SHA-256 checksum', $runbook);
        $this->assertStringNotContainsString('git checkout ', $runbook);
        $this->assertStringNotContainsString('composer install --', $runbook);
        $this->assertStringNotContainsString('npm ci ', $runbook);
    }
}
