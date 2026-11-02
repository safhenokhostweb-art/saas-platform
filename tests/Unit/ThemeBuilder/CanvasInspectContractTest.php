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

    public function test_the_preview_protocol_is_unchanged(): void
    {
        $this->assertNotContains('setInspect', PreviewProtocol::TO_PREVIEW);
        $this->assertCount(8, PreviewProtocol::TO_PREVIEW);
        $this->assertCount(9, PreviewProtocol::TO_EDITOR);
    }
}
