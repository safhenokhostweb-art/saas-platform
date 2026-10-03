<?php

namespace App\Services\ThemeBuilder;

use App\Models\ThemeTemplate;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ThemeTemplateService
{
    /**
     * Search/filter templates for the library picker.
     * Used by canvas AJAX and admin index.
     */
    public function search(
        ?string $type     = null,
        ?string $category = null,
        ?string $term     = null,
        ?int    $siteId   = null,
        int     $perPage  = 30,
    ): LengthAwarePaginator {
        $q = ThemeTemplate::query()->active();

        if ($siteId !== null) {
            $q->forSite($siteId);
        } else {
            $q->platform();
        }

        if ($type)     $q->byType($type);
        if ($category) $q->byCategory($category);
        if ($term)     $q->search($term);

        return $q->orderByDesc('usage_count')->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * Return all templates as a lightweight collection for the canvas picker
     * (no pagination, returns only metadata — no blocks).
     */
    /**
     * Phase 3A — extended to accept the canvas picker's full filter set.
     * The controller was shipped calling this with named arguments
     * (type/category/term/siteId) the signature never had — every canvas
     * template-list request failed with a PHP Error before this fix. New
     * optional params keep existing positional callers working.
     */
    public function listForCanvas(?int $siteId = null, ?string $type = null, ?string $category = null, ?string $term = null): Collection
    {
        $q = ThemeTemplate::query()
            ->active()
            ->select(['id', 'uuid', 'type', 'category', 'name', 'description',
                       'tags', 'thumbnail_url', 'usage_count', 'version']);

        if ($siteId !== null) {
            $q->forSite($siteId);
        } else {
            $q->platform();
        }

        if ($type) $q->byType($type);
        if ($category) $q->where('category', $category);
        if ($term) {
            $q->where(function ($qq) use ($term) {
                $qq->where('name', 'like', "%{$term}%")
                   ->orWhere('description', 'like', "%{$term}%");
            });
        }

        return $q->orderByDesc('usage_count')->orderByDesc('created_at')->get();
    }

    /**
     * Save an arbitrary block array as a named template.
     */
    public function saveTemplate(array $data, ?int $siteId = null, ?int $userId = null): ThemeTemplate
    {
        return ThemeTemplate::create([
            'type'              => $data['type']        ?? 'section',
            'category'          => $data['category']    ?? 'hero',
            'name'              => $data['name'],
            'description'       => $data['description'] ?? null,
            'tags'              => $data['tags']         ?? [],
            'author'            => $data['author']       ?? null,
            'version'           => $data['version']      ?? '1.0.0',
            'license_type'      => $data['license_type'] ?? 'free',
            'blocks'            => $data['blocks'],
            'global_styles'     => $data['global_styles']     ?? null,
            'demo_data'         => $data['demo_data']         ?? null,
            'thumbnail_url'     => $data['thumbnail_url']     ?? null,
            'preview_image_url' => $data['preview_image_url'] ?? null,
            'is_public'         => $data['is_public']  ?? false,
            'site_id'           => $siteId,
            'created_by'        => $userId,
        ]);
    }

    /**
     * Save the currently selected block(s) from a canvas as a template.
     * $blocks may be a single block or an array of blocks (wraps in array if needed).
     */
    public function saveFromCanvas(array $blocks, array $meta, ?int $siteId = null, ?int $userId = null): ThemeTemplate
    {
        // Normalize: if user saved a single block object (not array of blocks)
        if (isset($blocks['id']) && isset($blocks['type'])) {
            $blocks = [$blocks];
        }

        // Re-generate IDs so inserted template doesn't collide with originals
        $blocks = $this->regenIds($blocks);

        return $this->saveTemplate(array_merge($meta, ['blocks' => $blocks]), $siteId, $userId);
    }

    /**
     * Insert template blocks into a page at a given position.
     * Returns the merged blocks array.
     *
     * @deprecated  Use the canonical CanvasTreeOperations::insertNode() operation through the Canvas editor structural-operation path. This method is retained temporarily for backward compatibility and must not be used for new code.
     */
    public function insertIntoPage(ThemeTemplate $template, array $currentBlocks, int $position = -1): array
    {
        $newBlocks = $this->regenIds($template->blocks ?? []);

        if ($position < 0 || $position >= count($currentBlocks)) {
            return array_merge($currentBlocks, $newBlocks);
        }

        array_splice($currentBlocks, $position, 0, $newBlocks);
        return $currentBlocks;
    }

    /**
     * Recursively re-generate all block IDs (preserves structure, avoids ID collisions).
     */
    public function regenIds(array $blocks): array
    {
        return array_map(function (array $block) {
            $block['id'] = 'blk_' . Str::random(8);
            if (!empty($block['children']) && is_array($block['children'])) {
                $block['children'] = $this->regenIds($block['children']);
            }
            return $block;
        }, $blocks);
    }

    /**
     * Update an existing template.
     */
    public function update(ThemeTemplate $template, array $data): ThemeTemplate
    {
        $template->update(array_intersect_key($data, array_flip([
            'type', 'category', 'name', 'description', 'tags',
            'author', 'version', 'license_type', 'blocks',
            'global_styles', 'demo_data', 'thumbnail_url',
            'preview_image_url', 'is_public', 'is_active',
        ])));

        return $template->fresh();
    }

    /**
     * Get all available types and categories for filter UI.
     */
    public function filterOptions(): array
    {
        return [
            'types'      => ThemeTemplate::TYPES,
            'categories' => ThemeTemplate::CATEGORIES,
        ];
    }
}
