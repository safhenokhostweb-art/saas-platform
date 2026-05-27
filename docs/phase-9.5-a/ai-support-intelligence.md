# AI Support Intelligence — Phase 9.5-A

## Overview

`AiSupportReplyAssistant` is upgraded to a production support intelligence layer. It handles reply generation, ticket analysis, sentiment detection, thread summarization, and operational risk assessment — all with rule-based fallbacks.

---

## Feature Matrix

| Method | Input | Output | Fallback |
|---|---|---|---|
| `suggestReply()` | Subject, message, context | Reply suggestions array | Keyword-based replies |
| `rewriteReply()` | Reply text, tone, style | Rewritten reply text | Original reply |
| `generateInternalNote()` | Subject, action, context | Internal note text | Simple prefix |
| `generateEscalationSummary()` | Subject, priority, age, issue | Escalation JSON | Basic summary |
| `categorizeTicket()` | Ticket content | Category + priority + sentiment | Keyword rules |
| `predictSlaRisk()` | Priority, ages, status, category | Risk level + score | Time-based math |
| `analyzeSentiment()` | Customer message | Sentiment + frustration + churn risk | Keyword rules |
| `summarizeThread()` | Subject, thread array | Thread summary JSON | Empty |
| `checkDuplicate()` | Two ticket arrays | Duplicate + confidence | Always false |
| `assessChurnRisk()` | Ticket history array | Churn score + recommendation | Math-based |

---

## Reply Generation

**Persian-aware:** Prompts explicitly ask AI to match the language of the customer's message.

**Tone/style rewrite:**
```php
$rewritten = $assistant->rewriteReply(
    $originalReply,
    $ticket->subject,
    tone: 'empathetic',
    style: 'detailed',
    tenantId: $tenantId
);
```

Supported tones: `professional`, `empathetic`, `formal`, `friendly`
Supported styles: `concise`, `detailed`, `bullet_points`

---

## Ticket Intelligence

### Categorization
Returns `{category, subcategory, priority, urgency, sentiment, confidence, suggested_tags}`.
Categories: `billing`, `technical`, `account`, `feature_request`, `general`, `bug_report`, `security`

### SLA Risk Prediction
Returns `{risk_level, risk_score, recommended_action, escalate, estimated_breach_in_hours}`.
Rule-based thresholds: `critical`=4h, `high`=8h, `normal`=24h, `low`=72h

### Sentiment Analysis
Returns `{sentiment, frustration_level, urgency_signals, churn_risk, recommended_tone}`.
Frustration levels: `low`, `medium`, `high`

---

## Thread Summarization

```php
$summary = $assistant->summarizeThread(
    $ticket->subject,
    $ticket->replies->map(fn ($r) => ['role' => $r->is_customer ? 'user' : 'assistant', 'content' => $r->body])->toArray(),
    $tenantId
);
// {summary, current_status, customer_sentiment, unresolved_issues, next_action}
```

---

## Canned Responses

5 built-in canned responses: `greet`, `need_info`, `resolved`, `escalating`, `apology`

Register custom:
```php
$assistant->registerCannedResponse(
    'billing_faq',
    'پاسخ سوالات مالی',
    "سلام،\nتمام صورتحساب‌ها از طریق...",
    'billing'
);
```

---

## UX Integration

```blade
<x-core-ui::ai-assistant-panel
    domain="tickets"
    endpoint="/api/ai/ticket-reply-suggestions"
    :context="['ticket_subject' => $ticket->subject, 'ticket_id' => $ticket->id]"
    title="دستیار پشتیبانی"
/>
```

Events emitted:
- `ai-response` — AI response received
- `ai-insert` — use response text in reply editor
