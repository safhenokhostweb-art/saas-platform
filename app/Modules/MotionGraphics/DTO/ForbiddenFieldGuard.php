<?php

namespace App\Modules\MotionGraphics\DTO;

use InvalidArgumentException;

/**
 * Shared allowlist guard for adapter-facing DTOs.
 *
 * The runtime projection and package DTOs must never carry authoring
 * internals (source definitions, AI metadata, prompts, ownership/audit
 * detail) across the Laravel <-> Motion Graphics boundary — see
 * docs/motion-saas-integration/RUNTIME_PROJECTION.md. This guard rejects
 * any array payload that contains one of those keys, regardless of where
 * in the DTO tree it appears, so a forbidden field fails fast at
 * construction instead of leaking downstream.
 */
final class ForbiddenFieldGuard
{
    private const FORBIDDEN_KEYS = [
        'source', 'source_definition', 'source_definition_json', 'source_snapshot',
        'editable_schema', 'editable_schema_json', 'editable_schema_snapshot',
        'ai_metadata', 'ai_metadata_json', 'prompt', 'provider_payload',
        'approval', 'approvals', 'audit', 'ownership', 'owner_site_identifier',
        'credentials', 'storage_path', 'draft', 'history', 'internal_customer_id',
    ];

    /**
     * @param array<string, mixed> $payload
     */
    public static function assertClean(array $payload, string $field): void
    {
        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN_KEYS, true)) {
                throw new InvalidArgumentException(
                    "Field [{$field}] contains a forbidden key [{$key}] and cannot cross the Motion Graphics adapter boundary."
                );
            }
            if (is_array($value)) {
                self::assertClean($value, $field);
            }
        }
    }
}
