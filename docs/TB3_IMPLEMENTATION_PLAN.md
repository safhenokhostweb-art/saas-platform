# TB3 SPRINT 1 — IMPLEMENTATION PLAN
> Produced: 2026-06-16  
> Phases: TB3-01, TB3-02, TB3-03  
> Goal: 80% Elementor-like UX — Live iframe canvas, unified block format, drag-from-library

---

## PHASES IN SCOPE

| Phase | Name | Risk | Effort |
|---|---|---|---|
| TB3-01 | Unified Block Format (`block.props` everywhere) | MEDIUM | 0.5d |
| TB3-02 | Live Iframe Canvas | HIGH | 3–4d |
| TB3-03 | Drag from Library to Canvas | MEDIUM | 1.5d |

---

## TB3-01 — UNIFIED BLOCK FORMAT

### Problem
- Platform editor (`canvas.blade.php`): blocks stored with `block.data`
- Customer-site editor (`canvas-editor.blade.php`): blocks stored with `block.props`
- Platform `normalizeBlock()` actually converts `props → data` (WRONG direction)
- Creates fragility when themes move between platform and customer-site

### Solution
Standardize on `block.props` everywhere. Auto-migrate `block.data → block.props` on load.

### Files Affected

**Platform (`saas-platform`):**
- `app/Services/ThemeBuilder/BlockRenderer.php` — add `editor_mode` wrapping (no prop key change needed, already uses `$block['props'] ?? $block['data']`)
- `resources/views/admin/theme-builder/canvas.blade.php` — fix JS:
  - `normalizeBlock()`: reverse direction (`data → props`)
  - `addBlock()`: create with `props` not `data`
  - All prop panel bindings: `selectedBlock.data[propKey]` → `selectedBlock.props[propKey]`
  - `blockPreview()`: read from `props` first

**Customer Site (`customer-site`):**
- `app/Services/ThemeBuilder/BlockRenderer.php` — add `editor_mode` wrapping
- `app/Services/ThemeCanvas/ThemeCanvasRenderer.php` — add `editor_mode` wrapping for `module:*` blocks
- `resources/views/admin/theme-design/canvas-editor.blade.php` — already uses `props`; only need iframe upgrade

### DB Changes
None — block JSON is stored in JSON columns; normalization happens in JS on load.

### Backward Compatibility
- `BlockRenderer::renderBlock()` already does `$block['props'] ?? $block['data'] ?? []` — no change
- JS normalizer on load migrates old `data` to `props` transparently
- Themes exported in old format (with `data`) will be auto-migrated on next canvas open

---

## TB3-02 — LIVE IFRAME CANVAS

### Problem
Center panel shows a "block card list" — users cannot see how the page actually looks while editing.

### Solution
Replace center block-list with a live `<iframe>` that renders the actual page HTML via `BlockRenderer`, using a `postMessage` bridge for bi-directional communication.

### Architecture

```
Outer Editor (Alpine.js)          postMessage          Inner iframe (Rendered Page)
─────────────────────────────   ←──────────────→   ──────────────────────────────
selectedIndex, blocks[]              bridge              data-tb-block-id attrs
Props panel inputs                blockSelected          click → notify parent
schedulePropUpdate() ──AJAX──▶  updateBlock(id, html)    DOM update (no reload)
addBlock() / removeBlock()       ─────reload──────▶   full iframe reload after save
device toggle (desktop/tablet/mobile)                  tb-canvas-block CSS
drag overlay over iframe         getDropIndex(y)       returns block at Y pos
```

### New Endpoints (Platform)
```
GET  /admin/theme-builder/{theme}/canvas/live-preview    → livePreview()
POST /admin/theme-builder/{theme}/canvas/render-block    → renderBlockAjax()
```

### New Endpoints (Customer Site)
```
GET  /admin/theme-design/canvas/live-preview             → canvasLivePreview()
POST /admin/theme-design/canvas/render-block             → canvasRenderBlock()
```

### Live Update Flow
1. User edits prop in right panel → `schedulePropUpdate()` called (debounced 400ms)
2. AJAX POST to `render-block` with current block JSON
3. Server renders block HTML via `BlockRenderer` (editor_mode=true)
4. Response HTML sent via `postMessage({action:'updateBlock', blockId, html})` to iframe
5. iframe JS swaps the block's DOM content (no page reload)

### Structural Change Flow (add/remove/reorder)
1. Action performed in outer editor (state updated)
2. Auto-save via `quickSave()` (silent PUT, no toast)
3. `reloadIframe()` → updates `iframeTimestamp` → iframe src changes → reload

### Editor Mode in BlockRenderer
When `editor_mode` is true in `$context`, each `renderBlock()` wraps output in:
```html
<div class="tb-canvas-block" data-tb-block-id="{id}" data-tb-block-type="{type}">
    <div class="tb-block-badge">{type}</div>
    <!-- actual block HTML -->
</div>
```

### Drop Zone Rendering
Between blocks in the iframe, invisible `<div class="tb-drop-zone" data-before="{nextBlockId}">` elements are rendered. During drag, these become visible (8px blue bars).

### Device Preview
Toolbar adds Desktop / Tablet / Mobile toggle. The iframe container gets a max-width constraint:
- Desktop: 100%
- Tablet: 768px
- Mobile: 375px

### Files Affected

**Platform:**
- `app/Services/ThemeBuilder/BlockRenderer.php` — add editor_mode wrapping
- `app/Http/Controllers/Admin/ThemeCanvasController.php` — add `livePreview()`, `renderBlockAjax()`
- `routes/web.php` — 2 new routes
- `resources/views/admin/theme-builder/canvas-live-preview.blade.php` — NEW (standalone iframe page)
- `resources/views/admin/theme-builder/canvas.blade.php` — replace center panel, add bridge JS

**Customer Site:**
- `app/Services/ThemeBuilder/BlockRenderer.php` — add editor_mode wrapping
- `app/Services/ThemeCanvas/ThemeCanvasRenderer.php` — editor_mode for module blocks
- `app/Http/Controllers/Admin/ThemeDesignController.php` — add `canvasLivePreview()`, `canvasRenderBlock()`
- `routes/web.php` — 2 new routes
- `resources/views/admin/theme-design/canvas-live-preview.blade.php` — NEW
- `resources/views/admin/theme-design/canvas-editor.blade.php` — replace center panel

### DB Changes
None.

### Risk
HIGH — largest architectural change. Mitigation: keep the old preview (new tab) as fallback. The iframe can be disabled via `?mode=classic` query param for debugging.

---

## TB3-03 — DRAG FROM LIBRARY TO CANVAS

### Problem
Block library requires click to add — no drag-and-drop to canvas position.

### Solution
Library items become draggable. An invisible overlay div sits on top of the iframe and captures drag events. Drag enters overlay → postMessage to iframe to show drop zones → drop at calculated position.

### Implementation

**Library items:**
```html
<button draggable="true"
        @dragstart="startDragBlock($event, '{{ $blockType }}')"
        @dragend="endDragBlock()">
```

**Iframe overlay (over iframe):**
```html
<div id="drag-overlay"
     x-show="isDraggingBlock"
     @dragover.prevent="onDragOverCanvas($event)"
     @dragleave="onDragLeaveCanvas()"
     @drop.prevent="onDropToCanvas($event)">
```

**Drop index calculation:**
1. `dragover` → `postMessage({action:'getDropIndex', clientY: yRelativeToIframe})` to iframe
2. iframe iterates `.tb-canvas-block` elements, finds which midpoint the Y crosses
3. Returns `{action:'dropIndexResult', index:N, beforeId:'blk_xxx'}` to parent
4. Parent highlights the active drop zone: `postMessage({action:'setDropTarget', beforeId})`
5. On `drop` → `addBlockAtIndex(type, dropTargetIndex)`

### Intra-Canvas Reorder
SortableJS removed from the outer block-list (no longer a block-list). Reordering is now done via Up/Down buttons in the props panel (same as before) OR by dragging in the iframe (future TB3-10). For Sprint 1: Up/Down buttons remain available via the props panel.

### Files Affected
- `resources/views/admin/theme-builder/canvas.blade.php` — draggable attrs, overlay, handlers
- `resources/views/admin/theme-design/canvas-editor.blade.php` — same

### DB Changes
None.

---

## RISK ANALYSIS

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| iframe cross-origin block | LOW | HIGH | Both iframe and parent are same origin (same domain) |
| postMessage timing | MEDIUM | MEDIUM | `ready` event from iframe before sending messages; queue pending messages |
| Drag-drop cross-iframe | HIGH | MEDIUM | Use overlay div on TOP of iframe (avoids cross-iframe drag boundary) |
| BlockRenderer editor_mode regression | MEDIUM | HIGH | `editor_mode` is only enabled when context is explicitly set; default is false |
| Auto-save on structural change | LOW | LOW | Only saves current page blocks; existing save endpoint used |
| Customer-site draft system | LOW | MEDIUM | Live preview reads from `canvas_draft_data`; render-block is stateless |

---

## BACKWARD COMPATIBILITY STRATEGY

1. **`block.data` never deleted from DB** — only JS runtime normalizes on load
2. **BlockRenderer fallback** — `$block['props'] ?? $block['data'] ?? []` preserved
3. **Export/Import** — both formats work on import; saved format is `props`
4. **No migration needed** — JSON columns are read on load and normalized in-memory

---

## DEFINITION OF DONE

- [ ] TB3-01: Platform canvas opens any existing theme without visual regression
- [ ] TB3-01: Saving a block preserves data in `props` key in DB JSON
- [ ] TB3-02: Platform canvas editor shows live iframe in center panel
- [ ] TB3-02: Clicking a block in iframe selects it and shows its props
- [ ] TB3-02: Editing a prop updates the block in iframe within 500ms
- [ ] TB3-02: Adding a block auto-saves and reloads iframe
- [ ] TB3-02: Device toggle constrains iframe width correctly
- [ ] TB3-03: Dragging a block from library into iframe canvas adds it at drop position
- [ ] TB3-03: Drop zone indicators visible during drag
- [ ] Customer-site canvas editor has same upgrades as platform
- [ ] No regression in ThemeCanvasPageController (front-end rendering unaffected)
- [ ] Deploy successful

---

*End of TB3_IMPLEMENTATION_PLAN.md*
