<?php
namespace App\Services\ThemeBuilder;

use App\Models\Theme;

/**
 * One release-readiness precondition, evaluated by ThemeReleaseService
 * before any release is written. This is the single extension point for
 * future commercial gates (structured dependency validation, rendering
 * validation, accessibility, performance, visual regression) — each becomes
 * one more implementation registered with ThemeReleaseService, never a check
 * bolted onto a controller or a second copy of the gate elsewhere.
 *
 * A failing check must throw; it must never return a boolean the caller can
 * forget to inspect.
 */
interface ThemeReleaseReadinessCheck
{
    /**
     * @throws \RuntimeException when the theme is not release-ready.
     */
    public function check(Theme $theme): void;
}
