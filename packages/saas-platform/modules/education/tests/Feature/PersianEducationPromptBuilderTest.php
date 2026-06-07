<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Ai\Prompts\PersianEducationPromptBuilder;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Providers\EducationServiceProvider;

/**
 * EDU-17 Phase 5 — PersianEducationPromptBuilder unit tests.
 *
 * Verifies prompt structure, content-type routing, and language directives.
 * No HTTP calls, no database, no cache.
 */
class PersianEducationPromptBuilderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [EducationServiceProvider::class];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function builder(): PersianEducationPromptBuilder
    {
        return new PersianEducationPromptBuilder();
    }

    private function makeJob(
        string  $contentType = 'articles',
        string  $moduleSlug  = 'invoices',
        array   $metadata    = [],
    ): ContentGenerationJobDto {
        return new ContentGenerationJobDto(
            moduleSlug:   $moduleSlug,
            contentType:  $contentType,
            priority:     'high',
            siteId:       null,
            sourceReason: 'content_deficit',
            metadata:     $metadata,
        );
    }

    // ─── Structure ───────────────────────────────────────────────────────────

    public function test_build_returns_array_with_system_and_user_keys(): void
    {
        $prompt = $this->builder()->build($this->makeJob());

        $this->assertArrayHasKey('system', $prompt);
        $this->assertArrayHasKey('user', $prompt);
    }

    public function test_system_and_user_values_are_non_empty_strings(): void
    {
        $prompt = $this->builder()->build($this->makeJob());

        $this->assertIsString($prompt['system']);
        $this->assertIsString($prompt['user']);
        $this->assertNotEmpty($prompt['system']);
        $this->assertNotEmpty($prompt['user']);
    }

    // ─── Content-type routing ────────────────────────────────────────────────

    public function test_articles_content_type_returns_article_prompt(): void
    {
        $user = $this->builder()->build($this->makeJob('articles'))['user'];
        $this->assertStringContainsString('مقاله آموزشی', $user);
    }

    public function test_walkthroughs_content_type_returns_walkthrough_prompt(): void
    {
        $user = $this->builder()->build($this->makeJob('walkthroughs'))['user'];
        $this->assertStringContainsString('گام‌به‌گام', $user);
    }

    public function test_learning_path_content_type_returns_learning_path_prompt(): void
    {
        $user = $this->builder()->build($this->makeJob('learning_path'))['user'];
        $this->assertStringContainsString('مسیر یادگیری', $user);
    }

    public function test_remediation_content_type_returns_remediation_prompt(): void
    {
        $user = $this->builder()->build($this->makeJob('remediation'))['user'];
        $this->assertStringContainsString('شکاف دانشی', $user);
    }

    public function test_unknown_content_type_falls_back_to_article_prompt(): void
    {
        $user = $this->builder()->build($this->makeJob('unknown_type'))['user'];
        $this->assertStringContainsString('مقاله آموزشی', $user);
    }

    // ─── JSON output instructions ─────────────────────────────────────────────

    public function test_user_prompt_contains_json_schema_with_title_field(): void
    {
        $user = $this->builder()->build($this->makeJob())['user'];
        $this->assertStringContainsString('"title"', $user);
    }

    public function test_user_prompt_contains_json_schema_with_confidence_score(): void
    {
        $user = $this->builder()->build($this->makeJob())['user'];
        $this->assertStringContainsString('confidence_score', $user);
    }

    public function test_user_prompt_contains_json_only_output_instruction(): void
    {
        $user = $this->builder()->build($this->makeJob())['user'];
        $this->assertStringContainsString('JSON', $user);
    }

    // ─── Persian + RTL directives ─────────────────────────────────────────────

    public function test_system_prompt_contains_persian_language_directive(): void
    {
        $system = $this->builder()->build($this->makeJob())['system'];
        $this->assertStringContainsString('فارسی', $system);
    }

    public function test_system_prompt_contains_rtl_directive(): void
    {
        $system = $this->builder()->build($this->makeJob())['system'];
        $this->assertStringContainsString('RTL', $system);
    }

    // ─── Module label translation ─────────────────────────────────────────────

    public function test_known_module_slug_is_translated_to_persian_in_user_prompt(): void
    {
        $user = $this->builder()->build($this->makeJob('articles', 'invoices'))['user'];
        $this->assertStringContainsString('فاکتور', $user);
    }

    public function test_unknown_module_slug_is_used_as_is(): void
    {
        $user = $this->builder()->build($this->makeJob('articles', 'custom-module'))['user'];
        $this->assertStringContainsString('custom-module', $user);
    }

    // ─── Remediation metadata ─────────────────────────────────────────────────

    public function test_remediation_includes_gap_score_when_provided(): void
    {
        $job  = $this->makeJob('remediation', 'invoices', ['gap_score' => 75]);
        $user = $this->builder()->build($job)['user'];
        $this->assertStringContainsString('75', $user);
    }

    public function test_remediation_omits_gap_score_when_not_in_metadata(): void
    {
        $user = $this->builder()->build($this->makeJob('remediation'))['user'];
        // Should still generate a valid prompt without crashing
        $this->assertStringContainsString('شکاف دانشی', $user);
    }
}
