<?php

namespace Tests\Unit\ThemeBuilder;

use SaaSPlatform\ThemeRuntime\Canvas\PreviewProtocol;
use SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider;
use Tests\TestCase;

/**
 * WU-1 Inspect Mode: one shared script (theme-runtime::canvas.inspect) in
 * the editor and in its preview, joined through window.TbInspectHost, so
 * the versioned preview protocol does not change.
 */
class CanvasInspectContractTest extends TestCase
{
    public function test_the_shared_script_is_served_from_the_package(): void
    {
        $js = (string) file_get_contents(ThemeRuntimeServiceProvider::canvasInspectPath());
        $this->assertStringContainsString("root.TbInspect = api;", $js);
        $this->assertStringContainsString("e.code === 'KeyI'", $js, 'Ctrl+I is matched on the physical key (Persian layout)');
        $this->assertStringContainsString('attachShadow', $js, 'theme CSS cannot reach the overlay');
        foreach (["id: 'copy-type'", "id: 'copy-schema'", "id: 'copy-prompt'"] as $action) {
            $this->assertStringContainsString($action, $js);
        }
        $this->assertStringNotContainsString('https://', $js, 'no external origin (CSP)');

        $view = view('theme-runtime::canvas.inspect')->render();
        $this->assertStringContainsString('root.TbInspect = api;', $view);
        $this->assertStringContainsString('root.TbAudit = api;', $view, 'one include ships Inspect and the Design HUD');
    }

    public function test_the_design_hud_speaks_the_sentinel_vocabulary(): void
    {
        $hud = (string) file_get_contents(ThemeRuntimeServiceProvider::canvasAuditPath());
        $sentinel = (string) file_get_contents(base_path('tests/sentinel/support/findings.ts'));
        foreach (['WIDGET_OVERFLOW', 'WIDGET_CONTENT_OVERFLOW', 'TEXT_CLIPPED', 'TAP_TARGET_SMALL', 'BROKEN_IMAGE', 'RTL_MISSING'] as $code) {
            $this->assertStringContainsString("{$code}:", $hud, "{$code} has a HUD remedy");
            $this->assertStringContainsString("{$code}:", $sentinel, "{$code} is a Sentinel code");
        }
        $this->assertStringContainsString("'DEAD_LINK'", $hud);
        $this->assertStringContainsString('requestIdleCallback', $hud, 'checks run off the frame');
        $this->assertStringContainsString('ResizeObserver', $hud);
        $this->assertStringContainsString(".tb-drag-handle, .tb-block-badge, .tb-drop-zone", $hud, 'editor chrome is never audited');
        $this->assertStringNotContainsString('https://', $hud, 'no external origin (CSP)');
    }

    public function test_the_platform_editor_and_its_preview_both_load_it(): void
    {
        $editor = (string) file_get_contents(resource_path('views/admin/theme-builder/canvas.blade.php'));
        $this->assertStringContainsString("@include('theme-runtime::canvas.inspect')", $editor);
        $this->assertStringContainsString('window.TbInspect?.attachHost({', $editor);
        $this->assertStringContainsString('data-tb-inspect-toggle', $editor);

        $preview = (string) file_get_contents(resource_path('views/admin/theme-builder/canvas-live-preview.blade.php'));
        $this->assertStringContainsString("@include('theme-runtime::canvas.inspect')", $preview);
    }

    public function test_the_health_tab_is_one_shared_include_in_both_editors(): void
    {
        $js = (string) file_get_contents(ThemeRuntimeServiceProvider::canvasHealthPath());
        $this->assertStringContainsString('root.tbHealthPanel = panel;', $js);
        $this->assertStringContainsString('h.setAudit(on);', $js, 'the tab holds the checks while it is open');
        $this->assertStringContainsString('h.fixTask(row.key)', $js, 'the copy button uses the HUD fix prompt');
        $this->assertStringNotContainsString('https://', $js, 'no external origin (CSP)');

        $audit = (string) file_get_contents(ThemeRuntimeServiceProvider::canvasAuditPath());
        $this->assertStringContainsString("I.on('toggle', function (on) { hold('inspect', on); });", $audit, 'Inspect off does not stop a held audit');
        $this->assertStringContainsString("h.auditWanted() && doc.querySelector(WIDGET)) hold('host', true);", $audit, 'the tab survives a preview reload');

        $view = view('theme-runtime::canvas.health', ['active' => "leftPanelTab === 'health'"])->render();
        $this->assertStringContainsString('root.tbHealthPanel = panel;', $view);
        $this->assertStringContainsString('x-data="tbHealthPanel()"', $view);
        $this->assertStringContainsString('x-effect="setActive(leftPanelTab === &#039;health&#039;)"', $view);
        $this->assertStringContainsString('کپی پرامپت اصلاحی', $view);

        $editor = (string) file_get_contents(resource_path('views/admin/theme-builder/canvas.blade.php'));
        $this->assertSame(1, substr_count($editor, "@include('theme-runtime::canvas.health', ['active' => \"leftPanelTab === 'health'\"])"));
        $this->assertStringContainsString('data-testid="tb-canvas-health-tab"', $editor);
        $this->assertStringContainsString('select: (blockId) =>', $editor);
    }

    public function test_the_preview_protocol_is_unchanged(): void
    {
        $this->assertNotContains('setInspect', PreviewProtocol::TO_PREVIEW);
        $this->assertCount(8, PreviewProtocol::TO_PREVIEW);
        $this->assertCount(9, PreviewProtocol::TO_EDITOR);
    }
}
