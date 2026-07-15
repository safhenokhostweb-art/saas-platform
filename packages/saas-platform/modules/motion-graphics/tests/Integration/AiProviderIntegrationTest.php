<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Integration;

use Illuminate\Validation\ValidationException;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAiProviderContract;
use SaaSPlatform\Modules\MotionGraphics\Data\MotionAiResult;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionAiEditingService;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionLibraryService;
use SaaSPlatform\Modules\MotionGraphics\Tests\Fakes\FakeAiProvider;
use SaaSPlatform\Modules\MotionGraphics\Tests\TestCase;

final class AiProviderIntegrationTest extends TestCase
{
    public function test_fake_provider_creates_provider_neutral_encrypted_draft(): void
    {
        $motion = app(MotionLibraryService::class)->createFromPipeline($this->motionData('ai'), $this->svgInput(), $this->admin);
        $provider = app(MotionAiProviderContract::class);
        $this->assertInstanceOf(FakeAiProvider::class, $provider);
        $source = $this->svgInput()['source_definition'];
        $source['style']['color'] = '#d4af37';
        $provider->next = MotionAiResult::success($source, ['model' => 'agnostic-1']);
        $version = app(MotionAiEditingService::class)->edit($motion, 'make gold', $this->svgInput(), $this->admin);
        $this->assertSame(2, $version->version);
        $this->assertSame('#d4af37', $version->source_snapshot['style']['color']);
        $this->assertSame('fake-provider', $version->ai_metadata_json['provider_name']);
    }

    public function test_incomplete_or_malicious_ai_output_is_rejected(): void
    {
        $motion = app(MotionLibraryService::class)->createFromPipeline($this->motionData('ai-bad'), $this->svgInput(), $this->admin);
        $provider = app(MotionAiProviderContract::class);
        $provider->next = MotionAiResult::success(['title' => 'Incomplete', 'script' => 'evil']);
        $this->expectException(ValidationException::class);
        app(MotionAiEditingService::class)->edit($motion, 'bad', $this->svgInput(), $this->admin);
    }

    public function test_ai_provider_failure_does_not_create_version(): void
    {
        $motion = app(MotionLibraryService::class)->createFromPipeline($this->motionData('ai-fail'), $this->svgInput(), $this->admin);
        app(MotionAiProviderContract::class)->next = MotionAiResult::failure('offline');
        try {
            app(MotionAiEditingService::class)->edit($motion, 'fail', $this->svgInput(), $this->admin);
            $this->fail('Expected AI failure.');
        } catch (ValidationException) {
            $this->assertSame(1, $motion->versions()->count());
        }
    }
}
