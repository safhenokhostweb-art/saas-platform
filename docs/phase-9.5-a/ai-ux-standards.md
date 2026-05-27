# AI UX Standards — Phase 9.5-A

## New Core-UI Components

All 5 components are in `packages/saas-platform/modules/forms/resources/views/core-ui/components/`.
Available as `<x-core-ui::component-name />` on both saas-platform and customer-site.

---

## ai-assistant-panel

Full-featured conversational AI assistant sidebar/modal.

```blade
<x-core-ui::ai-assistant-panel
    domain="forms"                          {{-- forms | tickets | general --}}
    endpoint="/api/ai/form-suggestions"
    :context="['form_title' => $form->title]"
    panel-id="my-panel"
    title="دستیار فرم‌ساز"
/>
```

**Features:**
- Conversational history (configurable max)
- Quick prompt chips per domain
- Generation progress indicator (3 animated steps)
- Auto-resize textarea
- `Shift+Enter` for newline, `Enter` to submit
- `ai-response` event dispatched on completion
- `ai-insert` event dispatched when user clicks "استفاده"
- Per-message accept/use buttons

---

## ai-suggestion-card

Reviewable AI suggestion with accept/edit/reject/undo workflow.

```blade
<x-core-ui::ai-suggestion-card
    :suggestion="['text' => $text, 'confidence' => 0.9, 'source' => 'claude']"
    domain="tickets"
    action-key="reply_suggestion"
/>
```

**Events dispatched:**
- `ai-suggestion-accepted` — text ready to insert
- `ai-suggestion-rejected`
- `ai-suggestion-undone`

**Approval workflow:** inline edit before accepting. User can modify AI output before use.

**Feedback recorded:** POSTs to `/api/ai/feedback` (outcome: accepted/rejected/edited)

---

## ai-confidence-badge

Visual confidence indicator with arc animation.

```blade
<x-core-ui::ai-confidence-badge :score="0.87" source="claude" />
<x-core-ui::ai-confidence-badge :score="0.3" source="rule_based" compact />
```

Score ranges:
- ≥85% → emerald "بالا"
- 60-84% → amber "متوسط"
- 30-59% → orange "پایین"
- <30% → gray "قانونی" (rule-based)

---

## ai-stream-output

Animated output panel with loading skeleton and copy button.

```blade
<x-core-ui::ai-stream-output
    x-bind:content="aiResponse"
    x-bind:loading="isLoading"
    :show-copy="true"
    placeholder="منتظر پاسخ..."
    min-height="80px"
/>
```

Shows animated skeleton + bouncing dots while loading. Shows copy button on content.

---

## ai-generation-progress

Multi-step progress bar for AI generation workflows.

```blade
<x-core-ui::ai-generation-progress
    x-bind:steps="generationSteps"
    x-bind:current-step="currentStep"
    x-bind:loading="isGenerating"
/>
```

---

## UX Standards Enforced

1. **Loading states** — every AI call shows skeleton/spinner; no blank UI
2. **Confidence transparency** — every AI suggestion shows its confidence score
3. **Approval workflow** — users can edit before accepting; never forced to use as-is
4. **Undo support** — all accept/reject decisions can be undone
5. **Fallback states** — if AI fails, rule-based fallback appears transparently
6. **RTL safe** — all text uses `dir="auto"` or explicit RTL
7. **Keyboard accessible** — all buttons/inputs reachable via keyboard
8. **ARIA labels** — all interactive elements have `aria-label`
9. **Focus ring** — all focusable elements have visible focus indicators
10. **Feedback recorded** — acceptance/rejection tracked for effectiveness analytics
