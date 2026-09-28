<?php

namespace SaaSPlatform\ThemeRuntime\Widget;

/**
 * Phase 3A — the Theme Presentation Adapter contract.
 *
 * A module/semantic widget must NOT inject generic presentation that
 * destroys a commercial theme. The contract:
 *
 *   semantic widget output  +  theme presentation adapter  =  themed render
 *
 * Mechanism: hosts REGISTER which presentation owns a semantic type
 * (server-side, boot time — never from client payloads), and the renderer
 * resolves through its normal method dispatch (theme traits override
 * render methods; the shared base is the default/generic adapter). This
 * class is the declarative authority FOR that resolution: it answers
 * "which presentation renders this semantic type" and fail-closed reports
 * when a required theme adapter is missing — without Canvas core ever
 * containing theme names.
 */
final class PresentationAdapter
{
    /** semantic type => presentation id (e.g. 'heading' => 'foodmart'). */
    private static array $overrides = [];

    /** Register a presentation override for a semantic type (host boot only). */
    public static function register(string $semanticType, string $presentationId): void
    {
        if (!preg_match('/^[a-z0-9_:\-]+$/', $semanticType) || !preg_match('/^[a-z0-9_\-]+$/', $presentationId)) {
            throw new \InvalidArgumentException('PresentationAdapter registration requires safe identifiers.');
        }
        self::$overrides[$semanticType] = $presentationId;
    }

    /** Which presentation owns this semantic type, or null (= generic default). */
    public static function presentationFor(string $semanticType): ?string
    {
        return self::$overrides[$semanticType] ?? null;
    }

    /** All registered overrides (introspection/docs). */
    public static function all(): array
    {
        return self::$overrides;
    }

    /** Test isolation. */
    public static function flush(): void
    {
        self::$overrides = [];
    }
}
