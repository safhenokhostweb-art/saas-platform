<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\Modules\Forms\Models\Form;

class FormsVersionHistoryService
{
    private const CACHE_PREFIX  = 'forms_version_history_';
    private const MAX_VERSIONS  = 20;
    private const CACHE_TTL_SEC = 86400 * 7; // 7 days

    /**
     * Take a versioned snapshot of a form and its fields.
     */
    public function snapshot(Form $form, string $label = '', ?int $actorId = null): string
    {
        $versionId  = uniqid('v_', true);
        $historyKey = self::CACHE_PREFIX . $form->id;

        $snapshot = [
            'version_id'  => $versionId,
            'label'       => $label ?: ('Snapshot ' . now()->format('Y-m-d H:i')),
            'actor_id'    => $actorId ?? auth()->id(),
            'created_at'  => now()->toIso8601String(),
            'form'        => [
                'title'     => $form->title,
                'slug'      => $form->slug,
                'is_active' => $form->is_active,
                'settings'  => $form->settings,
            ],
            'fields' => $form->fields()
                ->orderBy('position')
                ->get(['id', 'name', 'label', 'type', 'options', 'is_required', 'position',
                       'is_active', 'placeholder', 'help_text', 'conditions', 'column_span',
                       'default_value', 'css_class', 'validation_rules'])
                ->toArray(),
        ];

        $history = Cache::get($historyKey, []);
        array_unshift($history, $snapshot);

        // Trim to max versions
        if (count($history) > self::MAX_VERSIONS) {
            $history = array_slice($history, 0, self::MAX_VERSIONS);
        }

        Cache::put($historyKey, $history, self::CACHE_TTL_SEC);

        return $versionId;
    }

    /**
     * List version history for a form (metadata only, no field data).
     */
    public function list(Form $form): array
    {
        $history = Cache::get(self::CACHE_PREFIX . $form->id, []);

        return array_map(fn($v) => [
            'version_id' => $v['version_id'],
            'label'      => $v['label'],
            'actor_id'   => $v['actor_id'],
            'created_at' => $v['created_at'],
            'field_count' => count($v['fields'] ?? []),
        ], $history);
    }

    /**
     * Retrieve a specific snapshot by version ID.
     */
    public function get(Form $form, string $versionId): ?array
    {
        $history = Cache::get(self::CACHE_PREFIX . $form->id, []);

        foreach ($history as $snapshot) {
            if ($snapshot['version_id'] === $versionId) {
                return $snapshot;
            }
        }

        return null;
    }

    /**
     * Restore a form to a specific version.
     * Returns the number of fields restored.
     */
    public function restore(Form $form, string $versionId): int
    {
        $snapshot = $this->get($form, $versionId);

        if (!$snapshot) {
            throw new \InvalidArgumentException("Version {$versionId} not found for form {$form->id}");
        }

        // Take a recovery snapshot before overwriting
        $this->snapshot($form, 'Pre-restore backup (auto)', auth()->id());

        // Restore form settings
        $form->update([
            'title'     => $snapshot['form']['title'],
            'is_active' => $snapshot['form']['is_active'],
            'settings'  => $snapshot['form']['settings'],
        ]);

        // Delete current fields and restore from snapshot
        $form->fields()->delete();

        $restoredCount = 0;
        foreach ($snapshot['fields'] as $fieldData) {
            $form->fields()->create([
                'name'             => $fieldData['name'],
                'label'            => $fieldData['label'],
                'type'             => $fieldData['type'],
                'options'          => $fieldData['options'],
                'is_required'      => $fieldData['is_required'],
                'position'         => $fieldData['position'],
                'is_active'        => $fieldData['is_active'],
                'placeholder'      => $fieldData['placeholder'],
                'help_text'        => $fieldData['help_text'],
                'conditions'       => $fieldData['conditions'],
                'column_span'      => $fieldData['column_span'],
                'default_value'    => $fieldData['default_value'],
                'css_class'        => $fieldData['css_class'],
                'validation_rules' => $fieldData['validation_rules'],
            ]);
            $restoredCount++;
        }

        return $restoredCount;
    }

    /**
     * Clear all version history for a form.
     */
    public function clear(Form $form): void
    {
        Cache::forget(self::CACHE_PREFIX . $form->id);
    }

    /**
     * Persist draft autosave for recovery.
     */
    public function saveDraft(Form $form, array $draftData): void
    {
        Cache::put(
            'forms_draft_' . $form->id,
            ['data' => $draftData, 'saved_at' => now()->toIso8601String()],
            3600 // 1 hour TTL for drafts
        );
    }

    /**
     * Retrieve a pending draft for recovery.
     */
    public function getDraft(Form $form): ?array
    {
        return Cache::get('forms_draft_' . $form->id);
    }

    /**
     * Clear draft after successful save.
     */
    public function clearDraft(Form $form): void
    {
        Cache::forget('forms_draft_' . $form->id);
    }
}
