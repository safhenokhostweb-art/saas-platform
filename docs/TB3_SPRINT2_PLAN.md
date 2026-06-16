# TB3 Sprint 2 Implementation Plan
> Date: 2026-06-16
> Scope: TB3-04, TB3-05, TB3-07
> Goal: Elementor-level editing experience

---

## Context

Sprint 1 shipped: unified block.props format, live iframe canvas, drag-and-drop from library.
Previous sprint (also called Sprint 2 in HANDOVER) shipped: undo/redo, block duplication, per-block style (bgColor, textColor, paddingY, paddingX, borderRadius, opacity).

This sprint ships three major UX improvements that complete the Elementor-parity roadmap:

| Feature | Goal |
|---------|------|
| TB3-04 Inline Editing | Click headings/text/buttons directly on canvas and type |
| TB3-05 Professional Panel | 3-tab panel with Typography, Colors, Spacing, Border, Shadow, Advanced |
| TB3-07 Media Manager | Upload → Library → Select → Replace cycle using StorageManager |

---

## TB3-04 — Inline Editing

### How it works
1. When a block is selected, editable elements (`data-tb-editable`) get `contenteditable=true` via JS
2. User types directly on canvas (no modal, no side panel for text)
3. Each keystroke sends `inlinePropChange` postMessage to parent window
4. Parent updates JS model (`blocks[i].props[key]`) instantly — no iframe reload
5. On blur (click elsewhere), parent schedules auto-save

### Elements that become inline-editable (editor_mode only)
| Block type | Prop key | Inline type |
|------------|----------|-------------|
| heading | text | text |
| text | content | html |
| button | text | text |
| hero | title | text |
| hero | subtitle | text |
| hero | button_text | text |
| cta | title | text |
| cta | subtitle | text |
| hero_split | title | text |
| hero_split | subtitle | text |
| hero_gradient | title | text |
| hero_gradient | subtitle | text |
| image | caption | text |

### PHP changes (BlockRenderer.php)
- Add `data-tb-editable="propKey"` and `data-tb-inline-type="text|html"` to rendered HTML
- Only emitted when `$this->context['editor_mode'] === true`
- Never emitted in production rendering (ThemeCanvasRenderer context)

### JS changes (canvas-live-preview.blade.php)
- `enableInlineEditing(blockEl)` — sets `contenteditable=true`, styles outline
- `disableInlineEditing()` — removes contenteditable, cleans up listeners
- `onInlineInput(e)` — debounced 200ms → sends `inlinePropChange` 
- `onInlineBlur(e)` — immediate send with `final:true` flag
- `onInlineKeydown(e)` — Escape/Enter exits text editing
- Click handler: if clicking on an already-editing contenteditable, don't re-select block
- `updateBlock` message: re-enables inline editing on the re-rendered block

### JS changes (canvas.blade.php)
- New message case `inlinePropChange` → update props, mark dirty
- New `scheduleAutoSave()` — debounced 1.5s auto-save (no iframe reload)

---

## TB3-05 — Professional Property Panel

### 3-tab structure
| Tab | Persian | Content |
|-----|---------|---------|
| Content | محتوا | Existing prop schema form (unchanged) |
| Style | استایل | 6 collapsible sections |
| Advanced | پیشرفته | CSS class, visibility, animation |

### Style tab sections
1. **تایپوگرافی** — font-size, font-weight, line-height, text-align (4-button toggle), letter-spacing
2. **رنگ‌ها** — background color + gradient toggle (from/to/direction), text color
3. **فاصله‌گذاری** — individual padding (T/R/B/L) with link-all toggle, margin top/bottom
4. **حاشیه** — border-width, border-style (none/solid/dashed/dotted), border-color, border-radius
5. **سایه** — preset buttons (هیچ/کوچک/متوسط/بزرگ/خیلی بزرگ) + custom text
6. **شفافیت** — range slider 0–100%

### Advanced tab
- **کلاس CSS سفارشی** — text input (sanitized, multi-class with spaces)
- **نمایش در دستگاه‌ها** — 3 checkboxes: دسکتاپ / تبلت / موبایل (hide toggle)
- **انیمیشن ورود** — select (none/fade-in/slide-up/slide-right/zoom-in/bounce-in) + duration ms

### PHP changes (BlockRenderer.applyBlockStyle)
New CSS properties supported:
- Typography: `font-size`, `font-weight`, `line-height`, `text-align`, `letter-spacing`
- Gradient: `background-image: linear-gradient(dir, from, to)`
- Individual padding: `padding-top`, `padding-right`, `padding-bottom`, `padding-left`
- Margin: `margin-top`, `margin-bottom`
- Border: `border-width`, `border-style`, `border-color`
- Shadow presets: `box-shadow` with named presets
- Custom class: appended to wrapper div
- Visibility: `tb-hide-desktop`, `tb-hide-tablet`, `tb-hide-mobile` CSS classes
- Animation: `tb-animate-*` CSS class + `--tb-animate-duration` CSS var

### CSS additions (canvas-live-preview.blade.php)
- `.tb-hide-desktop` → `@media (min-width: 1024px) { display: none !important; }`
- `.tb-hide-tablet` → `@media (min-width: 768px) and (max-width: 1023px) { display: none !important; }`
- `.tb-hide-mobile` → `@media (max-width: 767px) { display: none !important; }`
- `@keyframes` for: tb-fade-in, tb-slide-up, tb-slide-right, tb-zoom-in, tb-bounce-in
- `.tb-animate-*` classes referencing animations with `--tb-animate-duration` var

### Panel UI improvements
- Collapsible sections with chevron (open by default: typography, colors; closed: spacing, border, shadow)
- Alpine `styleSection` data: `{ typography: true, colors: true, spacing: false, border: false, shadow: false }`
- Compact label system (10px text-slate-500 above inputs)
- Reset button per section and "پاک کردن همه" at bottom

---

## TB3-07 — Media Manager

### Architecture
- Storage: `public` disk, directory `theme-media/{themeId}/` (saas-platform)
- Storage: `public` disk, directory `theme-media/{siteId}/` (customer-site)
- All file operations via `StorageManager::storeUploadedFile()` (saas-platform)
- File types: jpg, jpeg, png, gif, webp, svg (max 5MB)

### New server endpoints (saas-platform)
| Method | URL | Action |
|--------|-----|--------|
| GET | `/admin/theme-builder/{theme}/media` | List images (JSON) |
| POST | `/admin/theme-builder/{theme}/media/upload` | Upload image |
| DELETE | `/admin/theme-builder/{theme}/media` | Delete image (path param) |

### New server endpoints (customer-site)
| Method | URL | Action |
|--------|-----|--------|
| GET | `/admin/theme-design/canvas/media` | List images |
| POST | `/admin/theme-design/canvas/media/upload` | Upload image |
| DELETE | `/admin/theme-design/canvas/media` | Delete image |

### UI — Media picker modal
- Opens when: user clicks camera/image icon button next to any image-type prop field
- Two tabs in modal: **کتابخانه** (Library grid) | **آپلود** (Upload area)
- Library: responsive 3-col image grid with hover overlay (select + delete buttons)
- Upload: drag-and-drop zone + file input button, progress indicator
- On select: injects URL into the prop and closes modal

### Schema type `image`
- Add `type: 'image'` to BlockRegistry for image URL fields:
  - `image` block: `url` prop
  - `hero_split` block: `image_url` prop
- Content tab: if `schemaDef.type === 'image'`, renders thumbnail + URL text input + camera button

---

## Files Modified

### saas-platform
| File | Change |
|------|--------|
| `app/Services/ThemeBuilder/BlockRenderer.php` | `data-tb-editable` on elements + expanded `applyBlockStyle()` |
| `app/Http/Controllers/Admin/MediaController.php` | NEW — index/upload/delete |
| `resources/views/admin/theme-builder/canvas.blade.php` | 3-tab panel + media modal + inline edit handler |
| `resources/views/admin/theme-builder/canvas-live-preview.blade.php` | Inline editing bridge + animation/visibility CSS |
| `app/Services/ThemeBuilder/BlockRegistry.php` | `image` type for url fields |
| `routes/web.php` | 3 new media routes |

### customer-site
| File | Change |
|------|--------|
| `app/Services/ThemeBuilder/BlockRenderer.php` | Same as saas-platform |
| `app/Http/Controllers/Admin/ThemeDesignController.php` | 3 new media methods |
| `resources/views/admin/theme-design/canvas-editor.blade.php` | 3-tab panel + media modal + inline edit handler |
| `resources/views/admin/theme-design/canvas-live-preview.blade.php` | Inline editing bridge + animation/visibility CSS |
| `routes/web.php` | 3 new media routes |

---

## Acceptance Criteria

### TB3-04
- [ ] Click on heading in canvas → cursor appears, user can type
- [ ] Typing updates JS model (panel Content tab field updates in sync)
- [ ] Click elsewhere → editing stops, auto-save triggered
- [ ] No full iframe reload while typing
- [ ] Escape key exits inline editing

### TB3-05
- [ ] 3 tabs present: محتوا / استایل / پیشرفته
- [ ] All typography controls work and apply immediately via pushPropUpdate
- [ ] Gradient toggle shows from/to color pickers
- [ ] Individual padding per side
- [ ] Shadow presets (sm/md/lg/xl) apply correct box-shadow
- [ ] Animation select → block animates on next reload
- [ ] Hide on mobile/tablet/desktop toggles work

### TB3-07
- [ ] Camera icon visible next to image-type prop fields
- [ ] Clicking opens media modal
- [ ] Upload tab: can drag/drop or click to upload image
- [ ] Library tab: shows thumbnails of uploaded images
- [ ] Clicking image selects it and closes modal
- [ ] Delete button removes image from library
