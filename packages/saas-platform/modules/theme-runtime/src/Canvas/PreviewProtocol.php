<?php

namespace SaaSPlatform\ThemeRuntime\Canvas;

final class PreviewProtocol
{
    public const VERSION = 1;
    public const TO_PREVIEW = ['select', 'updateBlock', 'setProp', 'showDropZones', 'setDropTarget', 'setContainerTarget', 'getDropIndex', 'setStructuralDrop', 'setColumnWidths'];
    public const TO_EDITOR = [
        'ready', 'iframeHeight', 'blockSelected', 'dropIndexResult', 'inlinePropChange',
        'dragStart', 'dragOver', 'dropNode', 'dragCancel', 'columnResize',
    ];

    public static function validate(array $message, string $direction): bool
    {
        if (($message['protocolVersion'] ?? self::VERSION) !== self::VERSION || !is_string($message['action'] ?? null)) return false;
        $allowed = $direction === 'to-preview' ? self::TO_PREVIEW : ($direction === 'to-editor' ? self::TO_EDITOR : []);
        if (!in_array($message['action'], $allowed, true)) return false;
        return match ($message['action']) {
            'select' => !isset($message['blockId']) || is_string($message['blockId']),
            'updateBlock' => is_string($message['blockId'] ?? null) && is_string($message['html'] ?? null),
            'setProp' => is_string($message['blockId'] ?? null) && $message['blockId'] !== ''
                && is_string($message['propKey'] ?? null) && $message['propKey'] !== '',
            'blockSelected' => is_string($message['blockId'] ?? null),
            'showDropZones' => is_bool($message['visible'] ?? null),
            'getDropIndex' => is_numeric($message['clientY'] ?? null) && is_numeric($message['clientX'] ?? null),
            'iframeHeight' => is_numeric($message['height'] ?? null),
            'inlinePropChange' => is_string($message['propKey'] ?? null) && is_bool($message['final'] ?? null),
            // 2026-11-10: a finished Canvas column drag. Percentages only —
            // the editor clamps and persists them; the renderer re-validates.
            // 2026-11-10: Inspector column-width input → Canvas. Percentages
            // are clamped by the editor; this only proves the shape so a
            // malformed message never reaches the preview DOM.
            'setColumnWidths' => is_string($message['blockId'] ?? null)
                && $message['blockId'] !== ''
                && is_array($message['columns'] ?? null)
                && count($message['columns']) >= 1
                && count(array_filter(
                    $message['columns'],
                    static fn ($column): bool => is_array($column) && is_numeric($column['width'] ?? null)
                )) === count($message['columns']),
            'columnResize' => is_string($message['blockId'] ?? null)
                && is_array($message['columns'] ?? null)
                && count($message['columns']) >= 2
                && count(array_filter(
                    $message['columns'],
                    static fn ($column): bool => is_array($column) && is_numeric($column['width'] ?? null)
                )) === count($message['columns']),
            'dragStart', 'dragCancel' => is_string($message['nodeId'] ?? null),
            'dragOver', 'dropNode' => is_string($message['nodeId'] ?? null)
                && is_string($message['targetParentId'] ?? null)
                && is_int($message['targetIndex'] ?? null)
                && in_array($message['position'] ?? null, ['before', 'after', 'inside'], true),
            'setDropTarget' => !isset($message['beforeId']) || is_string($message['beforeId']),
            'setContainerTarget' => !isset($message['containerId']) || is_string($message['containerId']),
            'setStructuralDrop' => (!isset($message['targetId']) || is_string($message['targetId']))
                && (!isset($message['position']) || in_array($message['position'], ['before', 'after', 'inside'], true))
                && is_bool($message['valid'] ?? null),
            default => true,
        };
    }
}
