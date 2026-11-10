import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../', import.meta.url));
const themeRuntime = path.join(root, 'packages/saas-platform/modules/theme-runtime');

function scriptSource(file) {
    return fs.readFileSync(file, 'utf8').replaceAll('</script', '<\\/script');
}

const runtimeSource = scriptSource(path.join(themeRuntime, 'resources/views/canvas/runtime.blade.php'))
    .replace(/^\s*<script>\s*/, '')
    .replace(/\s*<\\\/script>\s*$/, '')
    .replace(/const TO_PREVIEW = new Set\(@json\([^)]*\)\);/,
        "const TO_PREVIEW = new Set(['select','updateBlock','setProp','showDropZones','setDropTarget','setContainerTarget','getDropIndex','setStructuralDrop','setColumnWidths']);")
    .replace(/const TO_EDITOR = new Set\(@json\([^)]*\)\);/,
        "const TO_EDITOR = new Set(['ready','iframeHeight','blockSelected','dropIndexResult','inlinePropChange','dragStart','dragOver','dropNode','dragCancel']);");

const sdkSource = scriptSource(path.join(themeRuntime, 'resources/js/block-sdk.js'));
const counterSource = scriptSource(path.join(themeRuntime, 'resources/js/blocks/reference-counter.js'));
const previewSource = fs.readFileSync(
    path.join(root, 'resources/views/admin/theme-builder/canvas-live-preview.blade.php'),
    'utf8',
);
const editorSource = fs.readFileSync(
    path.join(root, 'resources/views/admin/theme-builder/canvas.blade.php'),
    'utf8',
);

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

const listenerStart = previewSource.indexOf("window.addEventListener('message', function (e) {");
const listenerEnd = previewSource.indexOf("notifyParent({ action: 'ready' });", listenerStart);
const listenerSource = previewSource.slice(listenerStart, listenerEnd);
const updatePropSource = objectMethod(editorSource, 'updateProp(propKey, value)');
const pushFastPropUpdateSource = objectMethod(editorSource, 'pushFastPropUpdate(blockId, propKey, value)');
const executablePath = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH;

test.use({ launchOptions: executablePath ? { executablePath } : {} });

test('reference counter repaints through the iframe setProp message without render-block I/O', async ({ page }) => {
    expect(listenerStart).toBeGreaterThan(-1);
    expect(listenerEnd).toBeGreaterThan(listenerStart);

    const renderRequests = [];
    page.on('request', (request) => {
        if (request.url().includes('/canvas/render-block')) renderRequests.push(request.url());
    });

    await page.setContent('<iframe id="preview"></iframe>');
    await page.locator('#preview').evaluate((iframe, sources) => {
        iframe.srcdoc = `<!doctype html><html><body>
            <div data-tb-block-id="rc-fast" data-tb-block-type="reference_counter"
                 data-tb-block-props='{"step":2,"min":0,"max":10,"value":4}'>
                <span class="tb-ref-counter__value">4</span>
                <button class="tb-ref-counter__decrease"></button>
                <button class="tb-ref-counter__increase"></button>
                <button class="tb-ref-counter__reset"></button>
            </div>
            <script>${sources.runtime}<\/script>
            <script>${sources.sdk}<\/script>
            <script>${sources.counter}<\/script>
            <script>${sources.listener}<\/script>
        </body></html>`;
    }, {
        runtime: runtimeSource,
        sdk: sdkSource,
        counter: counterSource,
        listener: listenerSource,
    });

    const preview = page.frameLocator('#preview');
    const block = preview.locator('[data-tb-block-id="rc-fast"]');
    await expect(block).toHaveClass(/tb-block-ready/);

    await page.locator('#preview').evaluate((iframe, methods) => {
        const fastPathTypes = new Set(['reference_counter']);
        const editorMethods = new Function(
            'FAST_PATH_BLOCK_TYPES',
            'FAST_PATH_PROP_KEYS',
            `return ({${methods.updateProp},${methods.pushFastPropUpdate}});`,
        )(fastPathTypes, new Map([['header_modern_v1', new Set(['bgColor', 'textColor', 'ctaColor'])]]));
        const editor = Object.assign({
            selectedBlock: { id: 'rc-fast', type: 'reference_counter', props: { value: 4 } },
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

        editor.updateProp('value', 8);
    }, { updateProp: updatePropSource, pushFastPropUpdate: pushFastPropUpdateSource });

    await expect(preview.locator('.tb-ref-counter__value')).toHaveText('8');
    expect(renderRequests).toEqual([]);
});
