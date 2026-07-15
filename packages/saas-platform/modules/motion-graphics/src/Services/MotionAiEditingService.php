<?php

namespace SaaSPlatform\Modules\MotionGraphics\Services;

use Illuminate\Validation\ValidationException;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAiProviderContract;
use SaaSPlatform\Modules\MotionGraphics\Data\MotionAiRequest;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphicVersion;

final class MotionAiEditingService
{
    public function __construct(
        private readonly MotionAiProviderContract $provider,
        private readonly EditableSchemaValidator $schemas,
        private readonly MotionLibraryService $library,
    ) {}

    public function edit(MotionGraphic $motion, string $instruction, array $pipelineInput, mixed $actor): MotionGraphicVersion
    {
        if (! config('motion-graphics.ai_enabled', false)) {
            throw ValidationException::withMessages(['ai' => 'AI editing is disabled.']);
        }
        $result = $this->provider->generate(new MotionAiRequest(
            prompt: $instruction,
            sourceDefinition: $motion->source_definition_json,
            editableSchema: $motion->editable_schema_json,
            metadata: $motion->ai_metadata_json ?? [],
        ));
        if (! $result->success) {
            throw ValidationException::withMessages(['ai' => $result->error ?? 'AI generation failed.']);
        }
        $source = $this->schemas->validateComplete(
            $motion->source_definition_json,
            $result->sourceDefinition,
            $motion->editable_schema_json,
        );

        return $this->library->createVersionFromPipeline(
            $motion,
            [...$pipelineInput, 'source_definition' => $source],
            $instruction,
            $actor,
            [
                'provider_name' => $this->provider->providerName(),
                'model_name' => $result->responseMetadata['model'] ?? null,
                'original_prompt' => $instruction,
                'generation_metadata' => $result->responseMetadata,
                'generated_at' => now()->toIso8601String(),
                'source_checksum' => hash('sha256', json_encode($source, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
            ],
        );
    }
}
