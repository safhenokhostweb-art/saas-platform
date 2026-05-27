# Advanced Form Builder Report — Phase 9.4

## New Capabilities

### Section Builder (`_partials/section-builder.blade.php`)

- **Collapsible sections**: Fields can be grouped into labeled, toggleable sections
- **Multi-column layouts**: Per-section column count selector (1/2/3 columns)
- **Section templates**: Pre-built section kits (Contact, Address, Survey, Identity) via modal picker
- **Drag-and-drop between sections**: Sortable.js handles inter-section field movement

### Builder Intelligence

- **AI field suggestions**: `AiFormBuilderAssistant::suggestFields()` — calls prompt `form.suggest_fields` with form title/description
- **UX issue detection**: `detectWeakUx()` flags overloaded forms, required-field overload, missing labels — shown as amber banner in canvas
- **Accessibility warnings**: Missing labels, color fields without aria-label surface in the section-builder partial
- **Automation flow suggestions**: `SmartAutomationAdvisor::analyzeForOpportunities('form', ...)` generates rule-based + AI workflow ideas

### Runtime UX

- **Undo/redo stack**: History managed in `window.formBuilderSectionExt.history[]` (50-step limit)
- **Keyboard shortcuts**: `Ctrl+Z` (undo), `Ctrl+Y`/`Ctrl+Shift+Z` (redo)
- **Mobile preview toggle**: Switches canvas wrapper to `max-w-sm` constrained view
- **Draft timeline**: Autosave indicator already present; history index shows `N/M` breadcrumb

### Advanced Input Types

| Type | Notes |
|---|---|
| `signature` | Placeholder — requires canvas-based signature pad library |
| `rating` | Star rating via CSS radio pattern (already in builder CSS) |
| `matrix` | Architecture-ready via `repeater` field type |
| `repeater/group` | Field grouping with add/remove row support |
| `rich_text` | Textarea variant with TipTap/Quill hook |
| `file_gallery` | Multi-file upload with preview grid |

## Parity

The form builder is served exclusively from `forms::admin.forms.builder` — the package view. Customer-site tenant admins use the same view via the shared package path. No view duplication.
