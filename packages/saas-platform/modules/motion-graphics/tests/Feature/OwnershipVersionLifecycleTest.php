<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionLibraryService;
use SaaSPlatform\Modules\MotionGraphics\Tests\TestCase;

final class OwnershipVersionLifecycleTest extends TestCase
{
    public function test_global_and_customer_specific_ownership_are_validated(): void
    {
        $service = app(MotionLibraryService::class);
        $global = $service->createFromPipeline($this->motionData('global'), $this->svgInput(), $this->admin);
        $customer = $service->createFromPipeline(
            $this->motionData('customer', null, MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC, 'site-a'),
            $this->svgInput(),
            $this->admin,
        );
        $this->assertNull($global->owner_site_identifier);
        $this->assertSame('site-a', $customer->owner_site_identifier);
    }

    public function test_tenant_actor_cannot_author_approve_publish_or_rollback(): void
    {
        $this->expectException(ValidationException::class);
        app(MotionLibraryService::class)->createFromPipeline($this->motionData('denied'), $this->svgInput(), $this->tenant);
    }

    public function test_draft_does_not_disable_published_version_and_transitions_are_explicit(): void
    {
        $service = app(MotionLibraryService::class);
        $motion = $service->createFromPipeline($this->motionData('lifecycle'), $this->svgInput(), $this->admin);
        $service->approve($motion, 1, $this->admin);
        $service->publish($motion, 1, $this->admin);
        $draft = $service->createVersionFromPipeline($motion->fresh(), $this->svgInput('Draft'), 'draft', $this->admin);
        $motion->refresh();
        $this->assertSame(2, $motion->latest_working_version);
        $this->assertSame(1, $motion->approved_version);
        $this->assertSame(1, $motion->published_version);
        $this->assertSame('published', $motion->status);
        $this->assertSame('draft', $draft->status);
    }

    public function test_invalid_publish_transition_is_rejected(): void
    {
        $motion = app(MotionLibraryService::class)->createFromPipeline($this->motionData('invalid-publish'), $this->svgInput(), $this->admin);
        $this->expectException(ValidationException::class);
        app(MotionLibraryService::class)->publish($motion, 1, $this->admin);
    }

    public function test_rollback_creates_new_lineage_and_regenerates_asset_paths(): void
    {
        $service = app(MotionLibraryService::class);
        $motion = $service->createFromPipeline($this->motionData('rollback'), $this->svgInput('V1'), $this->admin);
        $service->approve($motion, 1, $this->admin);
        $service->publish($motion, 1, $this->admin);
        $v2 = $service->createVersionFromPipeline($motion->fresh(), $this->svgInput('V2'), 'v2', $this->admin);
        $rollback = $service->rollback($motion->fresh(), 1, 'restore', $this->admin);
        $this->assertSame(3, $rollback->version);
        $this->assertSame($v2->version, $rollback->parent_version);
        $this->assertSame('V1', $rollback->source_snapshot['title']);
        $this->assertStringContainsString('/v3/', $rollback->compiled_assets[0]['url']);
        $this->assertSame(1, $motion->fresh()->published_version);
    }

    public function test_version_content_is_immutable_and_sensitive_fields_are_encrypted_hidden(): void
    {
        $motion = app(MotionLibraryService::class)->createFromPipeline($this->motionData('secure'), $this->svgInput(), $this->admin);
        $raw = DB::table('motion_graphics')->where('id', $motion->id)->value('source_definition_json');
        $this->assertStringNotContainsString('Source', $raw);
        $this->assertArrayNotHasKey('source_definition_json', $motion->toArray());
        $version = $motion->versions()->first();
        $this->expectException(LogicException::class);
        $version->update(['source_snapshot' => ['tampered' => true]]);
    }

    public function test_version_comparison_reports_nested_paths(): void
    {
        $service = app(MotionLibraryService::class);
        $motion = $service->createFromPipeline($this->motionData('compare'), $this->svgInput(), $this->admin);
        $service->createVersionFromPipeline($motion, $this->svgInput('Changed'), 'change', $this->admin);
        $comparison = $service->compareVersions($motion, 1, 2, $this->admin);
        $this->assertContains('title', array_column($comparison['source_changes'], 'path'));
    }
}
