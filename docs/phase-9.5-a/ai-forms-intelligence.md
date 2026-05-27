# AI Forms Intelligence — Phase 9.5-A

## Overview

`AiFormBuilderAssistant` is upgraded from rule-based suggestions to full real-AI form intelligence, with graceful fallback to rule-based behavior when AI is unavailable.

---

## Feature Matrix

| Method | Input | Output | Fallback |
|---|---|---|---|
| `generateFromPrompt()` | Persian/English prompt | Complete form JSON | Rule-based template |
| `generateSection()` | Section type, form purpose | Section with fields | Built-in templates |
| `suggestFields()` | Title, description | Field array | Keyword-based rules |
| `optimizeForm()` | Form JSON | Score + issues + optimizations | Empty analysis |
| `detectAccessibilityIssues()` | Form JSON | Issues array | Label check only |
| `detectWeakUx()` | Fields array | Issues array | Always rule-based |
| `analyzeSubmissions()` | Title, count, sample | Patterns + anomalies + insights | Count summary |
| `summarizeSubmissions()` | Title, submissions | Summary report | Empty |
| `suggestAutomation()` | Form config | Workflow suggestions | Empty |
| `suggestValidation()` | Label, type | Validation rules | Type-based rules |

---

## AI Form Generation

**Prompt template:** `form.generate_from_prompt`

Generates complete form structure from natural language:
```php
$form = $assistant->generateFromPrompt('یک فرم ثبت‌نام رویداد بساز', $tenantId);
// Returns: {title, description, fields:[...], settings}
```

**Section generation:**
```php
$section = $assistant->generateSection('personal_info', 'رویداد ورزشی', $tenantId);
// Returns: {title, description, fields:[...]}
```

---

## AI Submission Intelligence

**Submission analysis** (requires ≥5 submissions):
```php
$analysis = $assistant->analyzeSubmissions(
    $form->title,
    $form->submissions()->count(),
    $form->submissions()->latest()->take(20)->get()->toArray(),
    $tenantId
);
// Returns: {patterns, anomalies, spam_likelihood, insights, recommendations}
```

**Submission summarization** (uses advanced model):
```php
$summary = $assistant->summarizeSubmissions($form->title, $submissions, $tenantId);
// Returns: {summary_text, key_findings, action_items, sentiment_distribution}
```

---

## Caching

- Form generation: 30-minute cache per (tenant + prompt hash)
- Validation suggestions: delegated to `AiSuggestionEngine` (1-hour cache)
- All cache keys are tenant-namespaced

---

## UX Integration

The `ai-assistant-panel` core-ui component connects to `/api/ai/form-suggestions`:
```blade
<x-core-ui::ai-assistant-panel
    domain="forms"
    endpoint="/api/ai/form-suggestions"
    :context="['form_title' => $form->title, 'field_count' => $form->fields->count()]"
/>
```
