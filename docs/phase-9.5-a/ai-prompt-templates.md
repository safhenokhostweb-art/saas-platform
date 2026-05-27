# AI Prompt Templates — Phase 9.5-A

## Template Registry

All templates registered in `PromptTemplateRegistry` with `{{variable}}` substitution syntax.

### Forms Domain

| Key | Variables | Output |
|---|---|---|
| `form.generate_from_prompt` | `prompt` | JSON form structure |
| `form.generate_section` | `section_type`, `form_purpose` | JSON section |
| `form.suggest_fields` | `title`, `description` | JSON field array |
| `form.suggest_validation` | `label`, `type` | JSON rules array |
| `form.optimize_fields` | `form_json` | JSON optimization report |
| `form.accessibility_check` | `form_json` | JSON issues array |
| `form.analyze_submissions` | `form_title`, `submission_count`, `sample_data` | JSON analysis |
| `form.summarize_submissions` | `form_title`, `submissions_json` | JSON summary report |

### Tickets Domain

| Key | Variables | Output |
|---|---|---|
| `ticket.suggest_reply` | `subject`, `message`, `context`, `reply_count` | Text reply |
| `ticket.rewrite_reply` | `reply`, `subject`, `tone`, `style` | Text reply |
| `ticket.categorize` | `content` | JSON category |
| `ticket.sla_risk` | `priority`, `age_hours`, `last_response_hours`, `status`, `category` | JSON risk |
| `ticket.duplicate_check` | `ticket_a`, `ticket_b` | JSON duplicate |
| `ticket.summarize_thread` | `subject`, `thread_json` | JSON summary |
| `ticket.sentiment` | `message` | JSON sentiment |
| `ticket.generate_internal_note` | `subject`, `action`, `context` | Text note |
| `ticket.generate_escalation_summary` | `subject`, `priority`, `age_hours`, `issue` | JSON summary |

### Automation Domain

| Key | Variables | Output |
|---|---|---|
| `automation.suggest_workflow` | `config` | JSON workflow array |

---

## Registering Custom Templates

```php
app(PromptTemplateRegistry::class)->register(
    'my_module.analyze_data',
    'Analyze this data: {{data}}. Return JSON: {summary, key_points, action_items}.',
    ['domain' => 'my_module', 'output' => 'json', 'capability' => 'json_structured']
);
```

---

## Template Security Notes

- Variables are substituted via `str_replace` (not eval)
- All variable values pass through `SensitiveDataRedactor` before substitution
- Templates themselves are never user-controlled (registered only in code)
- `TenantSafePromptBuilder` wraps the resolved template in a secure system context
