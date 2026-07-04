<?php

namespace Tests\Unit\CustomerDeletion;

use App\Jobs\CustomerDeletionPipelineJob;
use App\Services\CustomerDeletion\Dto\DeletePolicy;
use PHPUnit\Framework\TestCase;

class DeleteEngineSafetyTest extends TestCase
{
    public function test_delete_policies_target_expected_recovery_repositories(): void
    {
        $this->assertFalse(DeletePolicy::NEVER_DELETE_BACKUP->shouldDeleteLocalBackups());
        $this->assertFalse(DeletePolicy::NEVER_DELETE_BACKUP->shouldDeleteRemoteBackups());

        $this->assertTrue(DeletePolicy::DELETE_LOCAL_BACKUPS->shouldDeleteLocalBackups());
        $this->assertFalse(DeletePolicy::DELETE_LOCAL_BACKUPS->shouldDeleteRemoteBackups());

        $this->assertFalse(DeletePolicy::DELETE_REMOTE_BACKUPS->shouldDeleteLocalBackups());
        $this->assertTrue(DeletePolicy::DELETE_REMOTE_BACKUPS->shouldDeleteRemoteBackups());

        $this->assertTrue(DeletePolicy::DELETE_EVERYTHING->shouldDeleteLocalBackups());
        $this->assertTrue(DeletePolicy::DELETE_EVERYTHING->shouldDeleteRemoteBackups());
        $this->assertTrue(DeletePolicy::DELETE_EVERYTHING->isDestructive());
    }

    public function test_delete_pipeline_job_has_retry_and_backoff_guards(): void
    {
        $job = new CustomerDeletionPipelineJob(42);

        $this->assertSame(42, $job->deletionId);
        $this->assertSame(3, $job->tries);
        $this->assertSame(1800, $job->timeout);
        $this->assertSame([60, 300], $job->backoff());
    }
}
