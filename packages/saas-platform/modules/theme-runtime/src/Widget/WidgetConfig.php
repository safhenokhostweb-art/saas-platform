<?php

namespace SaaSPlatform\ThemeRuntime\Widget;

use InvalidArgumentException;

/** WidgetDefinition extension that validates optional data binding metadata. */
final class WidgetConfig
{
    private const MODES = ['static', 'api', 'internal'];

    public static function normalize(string $type, array $definition): array
    {
        $normalized = WidgetDefinition::normalize($type, $definition);
        if (!array_key_exists('data_source', $normalized)) {
            return $normalized;
        }
        $dataSource = $normalized['data_source'];
        if (!is_array($dataSource) || ($dataSource['property'] ?? null) !== 'data_source') {
            throw new InvalidArgumentException("Widget '{$type}' has an invalid data_source property contract.");
        }
        $modes = $dataSource['modes'] ?? null;
        if (!is_array($modes) || $modes === [] || array_values(array_unique($modes)) !== array_values($modes)) {
            throw new InvalidArgumentException("Widget '{$type}' data_source modes must be a unique list.");
        }
        foreach ($modes as $mode) {
            if (!is_string($mode) || !in_array($mode, self::MODES, true)) {
                throw new InvalidArgumentException("Widget '{$type}' declares an unsupported data-source mode.");
            }
        }
        foreach (['source_type', 'endpoint_url', 'query_params'] as $field) {
            if (!isset($normalized['schema'][$field]) || !array_key_exists($field, $normalized['defaultProps'] ?? [])) {
                throw new InvalidArgumentException("Widget '{$type}' data_source is missing '{$field}'.");
            }
        }
        return $normalized;
    }
}
