import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../', import.meta.url));
const themeRuntime = path.join(root, 'packages/saas-platform/modules/theme-runtime');
const blockDir = path.join(themeRuntime, 'resources/js/blocks/header-modern');

function scriptSource(file) {
    return fs.readFileSync(file, 'utf8').replaceAll('</script', '<\\/script');
}

function objectMethod(source, signature) {
    const start = source.indexOf(signature);
    const bodyStart = source.indexOf('{', start);
    let depth = 0;
    for (let i = bodyStart; i < source.length; i += 1) {
        if (source[i] === '{') depth += 1;
        if (source[i] === '}') depth -= 1;
        if (depth === 0) return source.slice(start, i + 1);
    }
    throw new Error(`Unclosed method: ${signature}`);
}

const runtimeSource = scriptSource(path.join(themeRuntime, 'resources/views/canvas/runtime.blade.php'))
    .replace(/^\s*<script>\s*/, '')
    .replace(/\s*<\\\/script>\s*$/, '')
    .replace(/const TO_PREVIEW = new Set\(@json\([^)]*\)\);/,
        "const TO_PREVIEW = new Set(['select','updateBlock','setProp','showDropZones','setDropTarget','setContainerTarget','getDropIndex','setStructuralDrop']);")
    .replace(/const TO_EDITOR = new Set\(@json\([^)]*\)\);/,
        "const TO_EDITOR = new Set(['ready','iframeHeight','blockSelected','dropIndexResult','inlinePropChange','dragStart','dragOver','dropNode','dragCancel']);");
const sdkSource = scriptSource(path.join(themeRuntime, 'resources/js/block-sdk.js'));
const headerSource = scriptSource(path.join(blockDir, 'header-modern.js'));
const headerCss = fs.readFileSync(path.join(blockDir, 'header-modern.css'), 'utf8').replaceAll('</style', '<\\/style');
const previewSource = fs.readFileSync(path.join(root, 'resources/views/admin/theme-builder/canvas-live-preview.blade.php'), 'utf8');
const editorSource = fs.readFileSync(path.join(root, 'resources/views/admin/theme-builder/canvas.blade.php'), 'utf8');
const listenerStart = previewSource.indexOf("window.addEventListener('message', function (e) {");
const listenerEnd = previewSource.indexOf("notifyParent({ action: 'ready' });", listenerStart);
const listenerSource = previewSource.slice(listenerStart, listenerEnd);
const updatePropSource = objectMethod(editorSource, 'updateProp(propKey, value)');
const pushFastPropUpdateSource = objectMethod(editorSource, 'pushFastPropUpdate(blockId, propKey, value)');

test('modern RTL header repaints colors without render I/O and exposes a working mobile menu', async ({ page }) => {
    const renderRequests = [];
    page.on('request', (request) => {
        if (request.url().includes('/canvas/render-block')) renderRequests.push(request.url());
    });
    await page.route('https://example.test/canvas/render-block', (route) => route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: '{}',
    }));

    await page.setViewportSize({ width: 1280, height: 900 });
    await page.setContent('<iframe id="preview" style="width:1100px;height:500px;border:0"></iframe>');
    await page.locator('#preview').evaluate((iframe, sources) => {
        iframe.srcdoc = `<!doctype html><html dir="rtl"><head><style>${sources.css}</style></head><body>
            <div data-tb-block-id="header-fast" data-tb-block-type="header_modern_v1"
                 data-tb-block-props='{"bgColor":"#ffffff","textColor":"#172033","ctaColor":"#4f46e5"}'>
                <div class="tb-header-modern">
                    <div class="tb-header-modern__inner">
                        <a class="tb-header-modern__brand" href="/">برند شما</a>
                        <nav id="mobile-menu" class="tb-header-modern__menu" aria-label="منوی اصلی">
                            <a class="tb-header-modern__link" href="#features">امکانات</a>
                        </nav>
                        <div class="tb-header-modern__actions">
                            <a class="tb-header-modern__cta" href="#start">شروع کنید</a>
                            <button class="tb-header-modern__toggle" aria-expanded="false" aria-controls="mobile-menu" aria-label="باز کردن منو">
                                <span class="tb-header-modern__toggle-lines"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <script>${sources.runtime}<\/script>
            <script>${sources.sdk}<\/script>
            <script>${sources.header}<\/script>
            <script>${sources.listener}<\/script>
        </body></html>`;
    }, { runtime: runtimeSource, sdk: sdkSource, header: headerSource, listener: listenerSource, css: headerCss });

    const preview = page.frameLocator('#preview');
    const block = preview.locator('[data-tb-block-id="header-fast"]');
    const header = preview.locator('.tb-header-modern');
    await expect(block).toHaveClass(/tb-block-ready/);
    await expect(preview.locator('.tb-header-modern__toggle')).toBeHidden();

    await page.locator('#preview').evaluate((iframe, methods) => {
        const editorMethods = new Function(
            'FAST_PATH_BLOCK_TYPES',
            'FAST_PATH_PROP_KEYS',
            `return ({${methods.updateProp},${methods.pushFastPropUpdate}});`,
        )(
            new Set(['reference_counter', 'header_modern_v1']),
            new Map([['header_modern_v1', new Set(['bgColor', 'textColor', 'ctaColor'])]]),
        );
        const editor = Object.assign({
            selectedBlock: {
                id: 'header-fast',
                type: 'header_modern_v1',
                props: { bgColor: '#ffffff', textColor: '#172033', ctaColor: '#4f46e5' },
            },
            historyEngine: null,
            historyVersion: 0,
            isDirty: false,
            iframeReady: true,
            pendingFastPropUpdates: [],
            sendToIframe(message) {
                iframe.contentWindow.postMessage({ protocolVersion: 1, ...message }, '*');
            },
            schedulePropUpdate() {
                fetch('https://example.test/canvas/render-block').catch(() => {});
            },
        }, editorMethods);
        window.__headerModernEditor = editor;
        editor.updateProp('bgColor', '#0f172a');
    }, { updateProp: updatePropSource, pushFastPropUpdate: pushFastPropUpdateSource });

    await expect(header).toHaveCSS('background-color', 'rgb(15, 23, 42)');
    expect(renderRequests).toEqual([]);

    await page.evaluate(() => window.__headerModernEditor.updateProp('logoUrl', '/brand.svg'));
    await expect.poll(() => renderRequests.length).toBe(1);

    await page.locator('#preview').evaluate((iframe) => { iframe.style.width = '390px'; });
    const toggle = preview.locator('.tb-header-modern__toggle');
    const menu = preview.locator('.tb-header-modern__menu');
    await expect(toggle).toBeVisible();
    await expect(menu).toHaveCSS('visibility', 'hidden');
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(toggle).toHaveAttribute('aria-label', 'بستن منو');
    await expect(menu).toHaveClass(/is-open/);
    await expect(menu).toHaveCSS('visibility', 'visible');
    await toggle.press('Escape');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(toggle).toHaveAttribute('aria-label', 'باز کردن منو');
});
