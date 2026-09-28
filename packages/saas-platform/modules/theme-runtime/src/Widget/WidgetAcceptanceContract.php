<?php

namespace SaaSPlatform\ThemeRuntime\Widget;

/**
 * Phase 3A — the Widget ACCEPTANCE contract (Step 5).
 *
 * The reusable matrix every commercial widget must satisfy to be
 * "commercial-ready". Structural/editor capabilities (add, delete,
 * duplicate, drag, reorder, live edit, undo/redo, responsive, RTL, LTR,
 * save, reload) are provided UNIFORMLY by the Canvas pipeline — a widget
 * qualifies when its DEFINITION carries the data that pipeline needs and
 * its type is actually rendered. This helper evaluates definitions so the
 * matrix is testable in bulk, and future widgets are checked against the
 * same gate as the foundation set.
 *
 * Interactive matrix proof is executed per-widget by the browser
 * acceptance layer (see phase3a docs); this is the definitional gate.
 */
final class WidgetAcceptanceContract
{
    /** Keys every widget definition must carry for the pipeline. */
    public const REQUIRED_KEYS = ['name', 'category', 'schema', 'defaultProps', 'icon'];

    /**
     * Evaluate a set of definitions (type => definition).
     *
     * @return array{
     *   missing: string[],           // types missing required keys
     *   containers_without_flag: string[], // structural types missing is_container
     *   types: array<string, array>  // per-type evaluation row
     * }
     */
    public static function evaluate(array $definitions): array
    {
        $missing = [];
        $badContainers = [];
        $rows = [];

        foreach ($definitions as $type => $definition) {
            if (!is_array($definition)) {
                $missing[] = (string) $type;
                continue;
            }
            $absent = [];
            foreach (self::REQUIRED_KEYS as $key) {
                if (!array_key_exists($key, $definition)) {
                    $absent[] = $key;
                }
            }
            if ($absent !== []) {
                $missing[] = (string) $type;
            }

            $isContainer = (bool) ($definition['is_container'] ?? false);
            if (in_array($type, ['section', 'container', 'grid', 'columns', 'flex_container', 'tabs', 'tab_panel', 'modal'], true) && !$isContainer) {
                $badContainers[] = (string) $type;
            }

            $rows[$type] = [
                'category' => $definition['category'] ?? null,
                'schema' => is_array($definition['schema'] ?? null),
                'defaultProps' => is_array($definition['defaultProps'] ?? null),
                'icon' => (string) ($definition['icon'] ?? ''),
                'is_container' => $isContainer,
                'responsive' => true, // pipeline-level: every node accepts responsive bags
                'dnd' => true,        // pipeline-level: every node is draggable/insertable
                'history' => true,    // pipeline-level: every op flows through the engine
            ];
        }

        return ['missing' => $missing, 'containers_without_flag' => $badContainers, 'types' => $rows];
    }
}
