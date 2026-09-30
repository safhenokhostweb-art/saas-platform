<?php

namespace Tests\Unit\Architecture;

use DomainException;
use PHPUnit\Framework\TestCase;
use SaaSPlatform\ThemeRuntime\Canvas\CanvasTreeOperations;
use SaaSPlatform\ThemeRuntime\Canvas\PreviewProtocol;

final class CanvasTreeOperationsTest extends TestCase
{
    private function service(array $metadata = []): CanvasTreeOperations
    {
        return new CanvasTreeOperations(static fn (array $node): array => $metadata[$node['type'] ?? ''] ?? []);
    }

    private function page(): array
    {
        return ['id' => 'page', 'kind' => 'page', 'children' => [
            ['id' => 'section-a', 'type' => 'section', 'kind' => 'section', 'children' => [
                ['id' => 'column-a', 'type' => 'column', 'kind' => 'column', 'children' => [
                    ['id' => 'widget-a', 'type' => 'heading', 'kind' => 'widget', 'props' => ['text' => 'A'], 'responsive' => ['mobile' => ['fontSize' => 18]], 'dataBinding' => ['provider' => 'products']],
                    ['id' => 'widget-b', 'type' => 'image', 'kind' => 'widget', 'props' => ['src' => 'b.jpg']],
                ]],
                ['id' => 'column-b', 'type' => 'column', 'kind' => 'column', 'children' => []],
            ]],
        ]];
    }

    public function test_move_same_parent_and_cross_column_preserve_payloads(): void
    {
        $service = $this->service();
        $moved = $service->moveNode($this->page(), 'widget-b', 'column-a', 0);
        $this->assertSame(['widget-b', 'widget-a'], array_column($moved['children'][0]['children'][0]['children'], 'id'));

        $moved = $service->moveNode($moved, 'widget-a', 'column-b', 0);
        $widget = $moved['children'][0]['children'][1]['children'][0];
        $this->assertSame(['mobile' => ['fontSize' => 18]], $widget['responsive']);
        $this->assertSame(['provider' => 'products'], $widget['dataBinding']);
        $this->assertSame('A', $widget['props']['text']);
    }

    public function test_nested_move_and_undo_coordinates_are_stable(): void
    {
        $service = $this->service();
        $page = $service->moveNode($this->page(), 'widget-a', 'column-b', 0);
        $page = $service->moveNode($page, 'widget-a', 'column-a', 0);
        $this->assertSame('widget-a', $page['children'][0]['children'][0]['children'][0]['id']);
    }

    /** @dataProvider invalidMoveProvider */
    public function test_invalid_moves_are_rejected(string $nodeId, string $parentId, int $index, string $message): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage($message);
        $this->service()->moveNode($this->page(), $nodeId, $parentId, $index);
    }

    public static function invalidMoveProvider(): array
    {
        return [
            'self parent' => ['column-a', 'column-a', 0, 'itself'],
            'cycle' => ['section-a', 'column-a', 0, 'descendants'],
            'widget parent' => ['widget-a', 'widget-b', 0, 'does not accept'],
            'unknown parent' => ['widget-a', 'missing', 0, 'does not exist'],
            'bad index' => ['widget-a', 'column-b', 4, 'out of bounds'],
        ];
    }

    public function test_max_children_and_locked_structure_are_enforced(): void
    {
        $max = $this->service(['column' => ['maxChildren' => 1]]);
        try {
            $page = $max->moveNode($this->page(), 'widget-a', 'column-b', 0);
            $max->moveNode($page, 'widget-b', 'column-b', 1);
            $this->fail('Expected maxChildren rejection.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('maxChildren', $e->getMessage());
        }

        $locked = $this->service(['column' => ['lockedStructure' => true]]);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Locked structure');
        $locked->deleteNode($this->page(), 'widget-a');
    }

    public function test_insert_delete_and_recursive_duplicate_ids(): void
    {
        $service = $this->service();
        $node = ['id' => 'container-new', 'type' => 'container', 'kind' => 'container', 'children' => [
            ['id' => 'widget-new', 'type' => 'button', 'kind' => 'widget', 'props' => ['label' => 'Buy']],
        ]];
        $page = $service->insertNode($this->page(), $node, 'column-b', 0);
        $this->assertSame('container-new', $page['children'][0]['children'][1]['children'][0]['id']);

        $ids = ['container-copy', 'widget-copy'];
        $nextId = static function () use (&$ids): string { return (string) array_shift($ids); };
        $page = $service->duplicateNode($page, 'container-new', 'column-b', 1, $nextId);
        $copy = $page['children'][0]['children'][1]['children'][1];
        $this->assertSame('container-copy', $copy['id']);
        $this->assertSame('widget-copy', $copy['children'][0]['id']);
        $this->assertSame('Buy', $copy['children'][0]['props']['label']);

        $deleted = $service->deleteNode($page, 'container-new');
        $this->assertSame('container-new', $deleted['deleted']['id']);
        $this->assertSame('column-b', $deleted['parent_id']);
    }

    public function test_server_transition_replay_rejects_forged_or_missing_commands(): void
    {
        $service = $this->service();
        $before = $this->page();
        $after = $service->moveNode($before, 'widget-a', 'column-b', 0);

        // Legacy clients without an operation log remain compatible: the
        // server infers and replays the same canonical moves itself.
        $this->assertTrue($service->validateTransition($before, $after, [])['ok']);
        $this->assertFalse($service->validateTransition($before, $after, [[
            'operation' => 'move', 'node_id' => 'widget-a', 'target_parent_id' => 'widget-b', 'target_index' => 0,
        ]])['ok']);
        $this->assertTrue($service->validateTransition($before, $after, [[
            'operation' => 'move', 'node_id' => 'widget-a', 'target_parent_id' => 'column-b', 'target_index' => 0,
        ]])['ok']);
    }

    public function test_preview_protocol_pins_pointer_drag_messages(): void
    {
        $this->assertTrue(PreviewProtocol::validate(['action' => 'dragStart', 'nodeId' => 'widget-a'], 'to-editor'));
        $this->assertTrue(PreviewProtocol::validate([
            'action' => 'dropNode', 'nodeId' => 'widget-a', 'targetParentId' => 'column-b', 'targetIndex' => 0, 'position' => 'inside',
        ], 'to-editor'));
        $this->assertFalse(PreviewProtocol::validate([
            'action' => 'dropNode', 'nodeId' => 'widget-a', 'targetParentId' => 'column-b', 'targetIndex' => -1, 'position' => 'sideways',
        ], 'to-editor'));
    }
}
