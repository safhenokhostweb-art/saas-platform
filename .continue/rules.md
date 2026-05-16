Authoritative Engineering Rules (Dynamic Loader)

This repository contains mandatory engineering and architectural rules.

All AI assistants working in this project MUST follow these rules before generating or modifying any code.
Rule Injection

Rule files may be injected into the conversation context by an external Rule Agent.

These files may originate from:

    rules/*.md
    rules/*.mdc
    *.rule.md
    rule files in the project root

All injected rules are authoritative constraints.

AI must treat them as mandatory architecture requirements.
Dynamic Rule Discovery

The rule system supports dynamic rule discovery.

All rule files matching the following patterns are considered part of the rule system:

    rules/*.md
    rules/*.mdc

New rules can be added without modifying this file.
Enforcement Policy

    These rules are mandatory constraints, not documentation.

    AI must:

    Load and check relevant rules before writing code
    Merge and apply all matching rules
    Stop and request clarification when repository evidence is missing
    Never invent fields, migrations, services, or architecture

    global-rules.mdc applies to all modules and all code.

    If rules conflict:

    User clarification is required
    AI must NOT assume

    Any output violating these rules is invalid for this project.

Developer Notes

Adding a new rule:

    Create a file inside /rules/
    Use .md or .mdc
    The Rule Agent will inject it automatically into the AI context

## منوی پنل ادمین (ساختار ثابت)

منوی اصلی سایدبار ادمین باید به ترتیب زیر باشد:

- داشبورد
- مشتریان / کاربران
- سایت‌ها / پروژه‌ها
- فروش / صورتحساب
- پشتیبانی / تیکت‌ها
- فرم‌ها / برگه‌ها
- محتوا / مقالات
- تنظیمات سیستم
- بازاریابی / دانش‌محور
- گزارش‌ها / آمار
