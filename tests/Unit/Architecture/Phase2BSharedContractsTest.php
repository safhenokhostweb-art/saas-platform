<?php

namespace Tests\Unit\Architecture;

use SaaSPlatform\ThemeRuntime\Canvas\PreviewProtocol;
use SaaSPlatform\ThemeRuntime\Inspector\InspectorFieldRegistry;
use SaaSPlatform\ThemeRuntime\Layout\DirectionCompatibilityAdapter;
use SaaSPlatform\ThemeRuntime\Layout\LockedStructureGuard;
use PHPUnit\Framework\TestCase;

/**
 * Phase 2B-R — verification layer for the Codex-authored shared contracts
 * (Inspector field registry, preview protocol, direction compatibility,
 * locked-structure guard). The shared classes are the authority; these
 * tests only pin their behavior.
 */
final class Phase2BSharedContractsTest extends TestCase
{
    // ── InspectorFieldRegistry ──────────────────────────────────────────

    public function test_field_vocabulary_is_pinned(): void
    {
        $this->assertSame([
            'text', 'textarea', 'rich_text', 'number', 'range', 'toggle',
            'select', 'color', 'link', 'media', 'icon', 'repeater', 'array',
            'spacing', 'dimensions', 'typography', 'alignment', 'responsive',
            'module_entity',
            'shadow', // ADR-082: structured box shadow
        ], InspectorFieldRegistry::TYPES);
    }

    public function test_definition_normalizes_schema_editor_and_capabilities(): void
    {
        $definition = InspectorFieldRegistry::normalizeDefinition([
            'schema' => [
                'title' => ['control' => 'text'],
                'gallery' => ['type' => 'repeater', 'maxDepth' => 9, 'fields' => [
                    'image' => ['control' => 'media'],
                    'caption' => ['control' => 'text'],
                ]],
            ],
            'editor' => ['lockedStructure' => true, 'uiDirection' => 'ltr'],
        ]);

        $this->assertSame('repeater', $definition['schema']['gallery']['control']);
        $this->assertSame(3, $definition['schema']['gallery']['maxDepth']);
        $this->assertTrue($definition['schema']['gallery']['stableIdentity']);
        $this->assertSame('media', $definition['schema']['gallery']['fields']['image']['control']);
        $this->assertTrue($definition['editor']['lockedStructure']);
        $this->assertTrue($definition['editor']['allowCustomCss']);
        $this->assertSame('ltr', $definition['editor']['uiDirection']);
    }

    public function test_unknown_field_type_fails_closed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InspectorFieldRegistry::normalizeField('x', ['control' => 'hologram']);
    }

    public function test_aliases_fold_legacy_type_names(): void
    {
        $this->assertSame('text', InspectorFieldRegistry::normalizeField('t', ['type' => 'string'])['control']);
        $this->assertSame('toggle', InspectorFieldRegistry::normalizeField('t', ['type' => 'boolean'])['control']);
        $this->assertSame('media', InspectorFieldRegistry::normalizeField('t', ['type' => 'image'])['control']);
        $this->assertSame('repeater', InspectorFieldRegistry::normalizeField('t', ['type' => 'blocks'])['control']);
        $this->assertSame('text', InspectorFieldRegistry::normalizeField('t', ['type' => 'motion'])['control']);
        $this->assertSame('string', InspectorFieldRegistry::normalizeField('t', ['type' => 'string'])['legacy_type']);
    }

    public function test_link_contract_accepts_structured_and_legacy_string(): void
    {
        $field = ['control' => 'link'];
        $this->assertSame(
            ['url' => 'https://example.com', 'target' => '_self', 'nofollow' => false, 'sponsored' => false, 'ariaLabel' => null],
            InspectorFieldRegistry::normalizeValue($field, ['url' => 'https://example.com'])
        );
        $this->assertSame(
            ['type' => 'internal_page', 'page_id' => 3],
            InspectorFieldRegistry::normalizeValue($field, ['type' => 'internal_page', 'page_id' => '3'])
        );
        $this->assertSame('https://example.com', InspectorFieldRegistry::normalizeValue($field, 'https://example.com'));
    }

    public function test_link_rejects_javascript_scheme(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InspectorFieldRegistry::normalizeValue(['control' => 'link'], ['url' => 'javascript:alert(1)']);
    }

    public function test_link_target_is_allowlisted(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InspectorFieldRegistry::normalizeValue(['control' => 'link'], ['url' => 'https://example.com', 'target' => '_top']);
    }

    public function test_media_v2_contract(): void
    {
        $field = ['control' => 'media'];
        $this->assertSame(
            ['media_id' => 'm1', 'kind' => 'image', 'alt' => 'سلام', 'focalPoint' => ['x' => 0, 'y' => 100]],
            InspectorFieldRegistry::normalizeValue($field, [
                'media_id' => 'm1', 'kind' => 'image', 'alt' => 'سلام', 'title' => null,
                'focalPoint' => ['x' => -5, 'y' => 150],
            ])
        );
        // {media_id} legacy string preserved verbatim.
        $this->assertSame('m1', InspectorFieldRegistry::normalizeValue($field, 'm1'));
    }

    public function test_media_rejects_filesystem_paths(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InspectorFieldRegistry::normalizeValue(['control' => 'media'], 'C:\\server\\secret.png');
    }

    public function test_spacing_is_logical_only(): void
    {
        $field = ['control' => 'spacing'];
        $this->assertSame(
            ['unit' => 'px', 'linked' => false, 'inline-start' => 1.0, 'inline-end' => 2.0, 'block-start' => 3.0, 'block-end' => 4.0],
            InspectorFieldRegistry::normalizeValue($field, [
                'inline-start' => 1, 'inline-end' => 2, 'block-start' => 3, 'block-end' => 4,
            ])
        );
        // Physical left/right is not part of the logical spacing vocabulary.
        $this->expectException(\InvalidArgumentException::class);
        InspectorFieldRegistry::normalizeValue($field, ['left' => 1, 'right' => 2]);
    }

    public function test_alignment_normalizes_logical_with_direction(): void
    {
        $field = ['control' => 'alignment'];
        $this->assertSame('start', InspectorFieldRegistry::normalizeValue($field, 'right', ['direction' => 'rtl']));
        $this->assertSame('start', InspectorFieldRegistry::normalizeValue($field, 'left', ['direction' => 'ltr']));
        $this->assertSame('end', InspectorFieldRegistry::normalizeValue($field, 'left', ['direction' => 'rtl']));
        $this->assertSame('end', InspectorFieldRegistry::normalizeValue($field, 'right', ['direction' => 'ltr']));
        $this->assertSame('center', InspectorFieldRegistry::normalizeValue($field, 'center'));
    }

    public function test_module_entity_provider_allowlist(): void
    {
        $field = InspectorFieldRegistry::normalizeField('products', ['control' => 'module_entity', 'providers' => ['products', 'menus']]);
        $this->assertSame(
            ['provider' => 'products', 'id' => 7],
            InspectorFieldRegistry::normalizeValue($field, ['provider' => 'products', 'id' => 7])
        );
        $this->expectException(\InvalidArgumentException::class);
        InspectorFieldRegistry::normalizeValue($field, ['provider' => 'db_users', 'id' => 1]);
    }

    public function test_global_token_semantics(): void
    {
        $color = InspectorFieldRegistry::normalizeField('accent', ['control' => 'color']);
        $text = InspectorFieldRegistry::normalizeField('title', ['control' => 'text']);
        $this->assertSame(
            ['mode' => 'global', 'token' => 'global.primary'],
            InspectorFieldRegistry::normalizeValue($color, ['mode' => 'global', 'token' => 'global.primary'])
        );
        // Non-tokenizable fields reject global tokens.
        $this->expectException(\InvalidArgumentException::class);
        InspectorFieldRegistry::normalizeValue($text, ['mode' => 'global', 'token' => 'global.heading']);
    }

    public function test_rich_text_is_sanitized_not_raw(): void
    {
        $field = ['control' => 'rich_text'];
        $clean = InspectorFieldRegistry::normalizeValue($field, '<p>سلام</p><script>alert(1)</script>');
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringContainsString('سلام', $clean);
    }

    public function test_repeater_new_item_identity_and_legacy_compatibility(): void
    {
        $fields = ['title' => ['control' => 'text']];
        $item = InspectorFieldRegistry::newRepeaterItem($fields);
        $this->assertMatchesRegularExpression('/^item_[0-9a-f]{16}$/', $item['_id']);
        $this->assertSame('', $item['title']);

        // Legacy items get a deterministic compatibility id — never rewritten in storage.
        $legacy = ['title' => 'old item'];
        $this->assertSame(
            'legacy_' . substr(hash('sha256', '0|' . json_encode($legacy, JSON_UNESCAPED_UNICODE)), 0, 16),
            InspectorFieldRegistry::compatibilityItemId($legacy, 0)
        );
    }

    // ── PreviewProtocol ─────────────────────────────────────────────────

    public function test_protocol_actions_and_version_are_pinned(): void
    {
        $this->assertSame(1, PreviewProtocol::VERSION);
        $this->assertSame(
            // updateHeaderLogoAnim: additive (live logo-animation preview); like
            // setColumnWidths before it, it keeps protocol version 1.
            ['select', 'updateBlock', 'setProp', 'showDropZones', 'setDropTarget', 'setContainerTarget', 'getDropIndex', 'setStructuralDrop', 'setColumnWidths', 'updateHeaderLogoAnim'],
            PreviewProtocol::TO_PREVIEW
        );
        $this->assertSame(
            ['ready', 'iframeHeight', 'blockSelected', 'dropIndexResult', 'inlinePropChange', 'dragStart', 'dragOver', 'dropNode', 'dragCancel', 'columnResize'],
            PreviewProtocol::TO_EDITOR
        );
    }

    public function test_protocol_payload_validation(): void
    {
        $this->assertTrue(PreviewProtocol::validate(['action' => 'updateBlock', 'blockId' => 'b1', 'html' => '<p>x</p>'], 'to-preview'));
        $this->assertTrue(PreviewProtocol::validate(['action' => 'setProp', 'blockId' => 'b1', 'propKey' => 'title', 'value' => 'New title'], 'to-preview'));
        $this->assertTrue(PreviewProtocol::validate([
            'action' => 'setColumnWidths', 'blockId' => 'cols1',
            'columns' => [['width' => 60], ['width' => 40]],
        ], 'to-preview'));
        $this->assertFalse(PreviewProtocol::validate([
            'action' => 'setColumnWidths', 'blockId' => '', 'columns' => [['width' => 60]],
        ], 'to-preview'));
        $this->assertFalse(PreviewProtocol::validate([
            'action' => 'setColumnWidths', 'blockId' => 'cols1', 'columns' => [],
        ], 'to-preview'));
        $this->assertFalse(PreviewProtocol::validate([
            'action' => 'setColumnWidths', 'blockId' => 'cols1', 'columns' => [['width' => 'x']],
        ], 'to-preview'));
        $this->assertFalse(PreviewProtocol::validate([
            'action' => 'setColumnWidths', 'blockId' => 'cols1', 'columns' => [['width' => 60]],
        ], 'to-editor'));
        $this->assertTrue(PreviewProtocol::validate(['action' => 'ready'], 'to-editor'));
        $this->assertTrue(PreviewProtocol::validate(['action' => 'getDropIndex', 'clientX' => 1, 'clientY' => 2], 'to-preview'));
        $this->assertTrue(PreviewProtocol::validate([
            'action' => 'columnResize', 'blockId' => 'cols1',
            'columns' => [['width' => 60], ['width' => 40]],
        ], 'to-editor'));
        $this->assertFalse(PreviewProtocol::validate([
            'action' => 'columnResize', 'blockId' => 'cols1', 'columns' => [['width' => 100]],
        ], 'to-editor'));
        $this->assertFalse(PreviewProtocol::validate([
            'action' => 'columnResize', 'blockId' => 'cols1', 'columns' => [['width' => 60], ['width' => 'x']],
        ], 'to-editor'));
        // Wrong direction, bad payload, unsupported version → rejected.
        $this->assertFalse(PreviewProtocol::validate(['action' => 'ready'], 'to-preview'));
        $this->assertFalse(PreviewProtocol::validate(['action' => 'select'], 'to-editor'));
        $this->assertFalse(PreviewProtocol::validate(['action' => 'updateBlock', 'blockId' => 4, 'html' => '<p>x</p>'], 'to-preview'));
        $this->assertFalse(PreviewProtocol::validate(['action' => 'setProp', 'blockId' => '', 'propKey' => 'title'], 'to-preview'));
        $this->assertFalse(PreviewProtocol::validate(['action' => 'setProp', 'blockId' => 'b1', 'propKey' => 4], 'to-preview'));
        $this->assertFalse(PreviewProtocol::validate(['protocolVersion' => 2, 'action' => 'ready'], 'to-editor'));
    }

    public function test_live_preview_routes_set_prop_messages_to_the_block_sdk(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/resources/views/admin/theme-builder/canvas-live-preview.blade.php');

        $this->assertIsString($source);
        $this->assertStringContainsString("case 'setProp':", $source);
        $this->assertStringContainsString('window.ThemeBlockSDK.setProp(d.blockId, d.propKey, d.value);', $source);
        $this->assertStringContainsString("console.error('[CanvasPreview] setProp failed:', error);", $source);
        $this->assertStringNotContainsString('window.blockSDK.setProp(', $source);
    }

    // ── DirectionCompatibilityAdapter ───────────────────────────────────

    public function test_document_direction_wins_and_legacy_projects(): void
    {
        $this->assertSame(
            ['direction' => 'ltr', 'locale' => 'en', 'source' => 'document'],
            DirectionCompatibilityAdapter::resolve(['direction' => 'ltr', 'locale' => 'en'])
        );
        // Legacy global_styles.direction is compatibility input.
        $this->assertSame(
            ['direction' => 'rtl', 'locale' => 'fa', 'source' => 'legacy_global_styles'],
            DirectionCompatibilityAdapter::resolve([], ['direction' => 'rtl'])
        );
        $this->assertSame(
            ['direction' => 'ltr', 'locale' => 'en', 'source' => 'locale'],
            DirectionCompatibilityAdapter::resolve(['locale' => 'en'])
        );
        // No data at all → host decides (nulls), provenance reported.
        $this->assertSame(
            ['direction' => null, 'locale' => null, 'source' => 'host_default'],
            DirectionCompatibilityAdapter::resolve()
        );
    }

    public function test_fa_ar_rtl_and_en_ltr_resolution(): void
    {
        $this->assertSame('rtl', DirectionCompatibilityAdapter::resolve(['locale' => 'fa'])['direction']);
        $this->assertSame('rtl', DirectionCompatibilityAdapter::resolve(['locale' => 'ar'])['direction']);
        $this->assertSame('ltr', DirectionCompatibilityAdapter::resolve(['locale' => 'en'])['direction']);
    }

    // ── LockedStructureGuard ────────────────────────────────────────────

    public function test_locked_node_allows_props_but_blocks_structure_change(): void
    {
        $metadataForType = fn (string $type) => $type === 'foodmart_hero' ? ['lockedStructure' => true] : [];
        $before = [['id' => 'h1', 'type' => 'foodmart_hero', 'props' => ['title' => 'old'], 'children' => [['id' => 'c1', 'type' => 'text']]]];

        // Props edit allowed.
        $propsEdit = [json_decode(json_encode($before[0]), true)];
        $propsEdit[0]['props']['title'] = 'new';
        $this->assertTrue(LockedStructureGuard::validate($before, $propsEdit, $metadataForType)['ok']);

        // Delete rejected.
        $this->assertSame(
            ["locked node 'h1' cannot be deleted"],
            LockedStructureGuard::validate($before, [], $metadataForType)['errors']
        );

        // Child mutation rejected.
        $childSwap = [json_decode(json_encode($before[0]), true)];
        $childSwap[0]['children'][0]['type'] = 'button';
        $this->assertSame(
            ["locked node 'h1' children cannot change"],
            LockedStructureGuard::validate($before, $childSwap, $metadataForType)['errors']
        );
    }

    public function test_unlocked_nodes_remain_fully_editable(): void
    {
        $metadataForType = fn (string $type) => [];
        $before = [['id' => 'a', 'type' => 'section', 'children' => [['id' => 'w1', 'type' => 'heading']]]];
        $after = [['id' => 'a', 'type' => 'section', 'children' => [], 'props' => ['changed' => true]]];
        $this->assertTrue(LockedStructureGuard::validate($before, $after, $metadataForType)['ok']);
    }
}
