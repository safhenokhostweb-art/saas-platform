<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Ai\Providers\CircuitBreakerAiContentProvider;
use SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Exceptions\AiProviderException;
use SaaSPlatform\Modules\Education\Providers\EducationServiceProvider;

/**
 * EDU-17 Phase 5 — CircuitBreakerAiContentProvider state machine tests.
 *
 * Uses array cache driver and config threshold of 3 to keep tests fast
 * and deterministic.
 */
class CircuitBreakerProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [EducationServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('education.ai.circuit_breaker.enabled', true);
        $app['config']->set('education.ai.circuit_breaker.failure_threshold', 3);
        $app['config']->set('education.ai.circuit_breaker.open_ttl_seconds', 300);
        $app['config']->set('education.ai.circuit_breaker.recovery_window_seconds', 60);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeJob(): ContentGenerationJobDto
    {
        return new ContentGenerationJobDto(
            moduleSlug:   'invoices',
            contentType:  'articles',
            priority:     'high',
            siteId:       null,
            sourceReason: 'content_deficit',
        );
    }

    private function successDto(): GeneratedContentDto
    {
        return new GeneratedContentDto(
            title:           'عنوان تست',
            summary:         'خلاصه تست',
            body:            '<p>' . str_repeat('محتوا ', 20) . '</p>',
            tags:            ['تست'],
            confidenceScore: 80.0,
            provider:        'mock',
        );
    }

    /** Inner that always succeeds. */
    private function successInner(): AiContentProviderInterface
    {
        $dto = $this->successDto();
        return new class($dto) implements AiContentProviderInterface {
            public function __construct(private GeneratedContentDto $dto) {}
            public function generate(ContentGenerationJobDto $job): GeneratedContentDto { return $this->dto; }
            public function isEnabled(): bool { return true; }
            public function providerName(): string { return 'mock'; }
        };
    }

    /** Inner that always fails. */
    private function failInner(): AiContentProviderInterface
    {
        return new class implements AiContentProviderInterface {
            public function generate(ContentGenerationJobDto $job): GeneratedContentDto {
                throw new AiProviderException('Mock provider failure');
            }
            public function isEnabled(): bool { return true; }
            public function providerName(): string { return 'mock'; }
        };
    }

    /** Wraps the given inner in a circuit breaker. */
    private function cb(AiContentProviderInterface $inner): CircuitBreakerAiContentProvider
    {
        return new CircuitBreakerAiContentProvider($inner);
    }

    /** Hit the circuit with failures until threshold is reached. */
    private function hitThreshold(CircuitBreakerAiContentProvider $cb, int $times = 3): void
    {
        for ($i = 0; $i < $times; $i++) {
            try { $cb->generate($this->makeJob()); } catch (AiProviderException) {}
        }
    }

    // ─── State machine ───────────────────────────────────────────────────────

    public function test_initial_state_is_closed(): void
    {
        $cb = $this->cb($this->successInner());
        $this->assertSame('CLOSED', $cb->getState());
    }

    public function test_single_failure_does_not_open_circuit(): void
    {
        $cb = $this->cb($this->failInner());
        try { $cb->generate($this->makeJob()); } catch (AiProviderException) {}

        $this->assertSame('HALF_OPEN', $cb->getState());
        $this->assertFalse($cb->isOpen());
    }

    public function test_circuit_opens_after_threshold_failures(): void
    {
        $cb = $this->cb($this->failInner());
        $this->hitThreshold($cb);

        $this->assertTrue($cb->isOpen());
        $this->assertSame('OPEN', $cb->getState());
    }

    public function test_open_circuit_throws_without_calling_inner(): void
    {
        $callCount = 0;
        $inner = new class($callCount) implements AiContentProviderInterface {
            public function __construct(private int &$count) {}
            public function generate(ContentGenerationJobDto $job): GeneratedContentDto {
                $this->count++;
                throw new AiProviderException('Inner called — should not happen');
            }
            public function isEnabled(): bool { return true; }
            public function providerName(): string { return 'mock'; }
        };

        $cb = new CircuitBreakerAiContentProvider($inner);
        $this->hitThreshold($cb); // opens circuit — inner called 3 times during warm-up

        $callsBefore = $callCount;

        $this->expectException(AiProviderException::class);
        $cb->generate($this->makeJob()); // OPEN — inner must NOT be called again
    }

    public function test_open_circuit_exception_message_contains_provider_name(): void
    {
        $cb = $this->cb($this->failInner());
        $this->hitThreshold($cb);

        try {
            $cb->generate($this->makeJob());
            $this->fail('Expected AiProviderException not thrown');
        } catch (AiProviderException $e) {
            $this->assertStringContainsString('mock', $e->getMessage());
        }
    }

    public function test_success_resets_failure_counter(): void
    {
        $cb = $this->cb($this->successInner());
        // Trigger a single failure via a temporary fail inner to prime the counter
        $fail = $this->cb($this->failInner());
        try { $fail->generate($this->makeJob()); } catch (AiProviderException) {}

        // Successful call through a success inner on the SAME cache keys
        $cb->generate($this->makeJob());

        $this->assertSame('CLOSED', $cb->getState());
    }

    public function test_success_clears_open_flag(): void
    {
        $cb = $this->cb($this->failInner());
        $this->hitThreshold($cb);
        $this->assertTrue($cb->isOpen());

        // Expire the open flag manually (simulate TTL)
        Cache::forget('edu:ai:cb:mock:open');

        // Now re-wire to a success inner and probe
        $successCb = $this->cb($this->successInner());
        $successCb->generate($this->makeJob());

        $this->assertSame('CLOSED', $successCb->getState());
        $this->assertFalse($successCb->isOpen());
    }

    public function test_half_open_allows_probe_request_through(): void
    {
        $cb = $this->cb($this->failInner());
        $this->hitThreshold($cb); // → OPEN

        Cache::forget('edu:ai:cb:mock:open'); // simulate TTL expiry → HALF_OPEN

        // A success inner can now probe
        $successCb = $this->cb($this->successInner());
        $dto       = $successCb->generate($this->makeJob());

        $this->assertSame('عنوان تست', $dto->title);
    }

    public function test_half_open_failure_reopens_circuit(): void
    {
        $cb = $this->cb($this->failInner());
        $this->hitThreshold($cb); // → OPEN
        Cache::forget('edu:ai:cb:mock:open'); // → HALF_OPEN

        // Probe fails → should reopen
        $failCb = $this->cb($this->failInner());
        try { $failCb->generate($this->makeJob()); } catch (AiProviderException) {}

        $this->assertTrue($failCb->isOpen());
    }

    public function test_disabled_circuit_bypasses_all_state_logic(): void
    {
        config(['education.ai.circuit_breaker.enabled' => false]);

        $cb = $this->cb($this->successInner());
        $dto = $cb->generate($this->makeJob());

        $this->assertSame('عنوان تست', $dto->title);
    }

    public function test_provider_name_delegates_to_inner(): void
    {
        $cb = $this->cb($this->successInner());
        $this->assertSame('mock', $cb->providerName());
    }

    public function test_is_enabled_delegates_to_inner(): void
    {
        $disabled = new class implements AiContentProviderInterface {
            public function generate(ContentGenerationJobDto $job): GeneratedContentDto {
                throw new AiProviderException('disabled');
            }
            public function isEnabled(): bool { return false; }
            public function providerName(): string { return 'disabled'; }
        };

        $cb = $this->cb($disabled);
        $this->assertFalse($cb->isEnabled());
    }

    public function test_reset_clears_both_cache_keys(): void
    {
        $cb = $this->cb($this->failInner());
        $this->hitThreshold($cb);
        $this->assertTrue($cb->isOpen());

        $cb->reset();

        $this->assertFalse($cb->isOpen());
        $this->assertSame('CLOSED', $cb->getState());
    }
}
