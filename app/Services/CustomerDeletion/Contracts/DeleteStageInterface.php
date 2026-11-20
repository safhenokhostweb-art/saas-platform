<?php

namespace App\Services\CustomerDeletion\Contracts;

use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Dto\DeleteStageResult;

interface DeleteStageInterface
{
    /** Machine-readable stage identifier (e.g. REMOVE_DATABASE). */
    public function name(): string;

    /** Human-readable Persian label shown in UI. */
    public function label(): string;

    /** If true, pipeline aborts on failure (no retry, no continue). */
    public function isCritical(): bool;

    /** Estimated execution time in seconds. */
    public function estimatedSeconds(): int;

    /**
     * Capture any state that exists BEFORE this stage executes so that
     * rollback has actionable data even if the stage fails midway.
     */
    public function buildPreSnapshot(DeleteContext $ctx): array;

    /** Execute the deletion stage. */
    public function execute(DeleteContext $ctx): DeleteStageResult;

    /**
     * Verify the deletion was successful.
     * Returns an array of check results:
     *   [['check' => '...', 'passed' => bool, 'detail' => '...'], ...]
     */
    public function verify(DeleteContext $ctx): array;

    /**
     * Attempt to reverse this stage if the pipeline aborts after it ran.
     * Rollback on a delete stage typically means RE-CREATING what was removed
     * (used when the overall delete operation itself is being cancelled).
     * Most stages leave rollback as a no-op because re-creation from scratch
     * is not feasible without the original installation context.
     */
    public function rollback(DeleteContext $ctx, array $snapshot): void;

    /**
     * ADR-090 — true for a stage that must not run once any earlier stage
     * has failed (REMOVE_PLATFORM_RECORDS): the site row stays in the panel,
     * the deletion is marked failed, and Retry resumes from the failure.
     */
    public function requiresCleanRun(): bool;
}
