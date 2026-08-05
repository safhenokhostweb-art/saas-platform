<?php

namespace Tests\Unit\Modules\SeoAdvanced;

use SaaSPlatform\Modules\SeoAdvanced\Contracts\AiSeoProviderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Services\AiSeo\AiSeoAssistantService;
use SaaSPlatform\Modules\SeoAdvanced\Services\AiSeo\NullAiSeoProvider;
use Tests\TestCase;

class AiSeoAssistantServiceTest extends TestCase
{
    public function test_null_provider_reports_unavailable_with_empty_suggestions(): void
    {
        $service = new AiSeoAssistantService(new NullAiSeoProvider());

        $result = $service->analyzePage('some page content', 'old title');

        $this->assertFalse($result['available']);
        $this->assertSame('none', $result['provider']);
        $this->assertNull($result['title_suggestion']);
        $this->assertSame([], $result['content_improvements']);
    }

    public function test_a_real_provider_populates_every_suggestion_field(): void
    {
        $fake = new class implements AiSeoProviderInterface {
            public function name(): string { return 'fake-ai'; }
            public function isAvailable(): bool { return true; }
            public function suggestTitle(string $c, ?string $t, array $x = []): ?string { return 'عنوان بهتر'; }
            public function suggestDescription(string $c, ?string $d, array $x = []): ?string { return 'توضیح بهتر'; }
            public function suggestHeadingStructure(string $c, array $x = []): array { return [['level' => 1, 'text' => 'H1']]; }
            public function suggestContentImprovements(string $c, array $x = []): array { return ['بهبود ۱']; }
            public function suggestKeywords(string $c, array $x = []): array { return ['کلمه۱', 'کلمه۲']; }
        };

        $result = (new AiSeoAssistantService($fake))->analyzePage('content', 'title', 'description');

        $this->assertTrue($result['available']);
        $this->assertSame('fake-ai', $result['provider']);
        $this->assertSame('عنوان بهتر', $result['title_suggestion']);
        $this->assertSame('توضیح بهتر', $result['description_suggestion']);
        $this->assertCount(1, $result['heading_suggestions']);
        $this->assertSame(['بهبود ۱'], $result['content_improvements']);
        $this->assertCount(2, $result['keyword_suggestions']);
    }

    public function test_default_container_binding_is_the_null_provider(): void
    {
        $this->assertInstanceOf(NullAiSeoProvider::class, app(AiSeoProviderInterface::class));
    }
}
