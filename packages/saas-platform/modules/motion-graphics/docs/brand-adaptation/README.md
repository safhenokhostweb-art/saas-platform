# Brand Adaptation Layer

Status: **Architecture only — not implemented or connected.**

## Purpose

The Brand Adaptation Layer is a private Platform Admin authoring workflow that derives customer-branded variants from approved global assets. It supports icons, objects, illustrations, and motion graphics without changing the master asset.

The layer extends the existing centralized master-library rules:

- the main platform remains the only creator and authoring authority;
- tenants remain consumers;
- global masters and all historical versions are immutable;
- AI prepares proposals but never approves or publishes;
- customer packages contain only sanitized, approved outputs;
- Laravel, persistence, routes, providers, storage adapters, and runtime integration are outside this architecture-only phase.

## Core model

```text
Approved global master
        |
        | selected by Platform Admin
        v
Private adaptation request + sanitized logo
        |
        | provider-neutral AI proposal
        v
Validated customer-specific draft
        |
        | Platform Admin review and approval
        v
Immutable customer variant version
        |
        v
Published customer asset package
```

| Concept | Owner | Mutable? | Tenant visibility |
|---|---|---:|---|
| Global master | Platform | No, versioned | Published runtime asset only |
| Logo upload | Customer/site, platform-custodied | Replace by new upload | No authoring access |
| Adaptation request | Platform | Append-only history | No |
| Variant draft | Customer/site | No in-place edits | No |
| Published variant | Customer/site | No | Approved compiled asset only |
| Reuse template | Platform | Versioned | No direct access |

## Required documents

- [ASSET_INHERITANCE.md](ASSET_INHERITANCE.md)
- [VERSIONING.md](VERSIONING.md)
- [AI_EDITING_HISTORY.md](AI_EDITING_HISTORY.md)
- [CUSTOMER_OWNERSHIP_RULES.md](CUSTOMER_OWNERSHIP_RULES.md)
- [THEME_TOKEN_CONTRACT.md](THEME_TOKEN_CONTRACT.md)
- [AI_PROVIDER_WORKFLOW.md](AI_PROVIDER_WORKFLOW.md)
- [CUSTOMER_ASSET_PACKAGE.md](CUSTOMER_ASSET_PACKAGE.md)

## Non-goals

This phase does not define database migrations, Laravel models, controllers, routes, queues, policies, provider SDKs, storage infrastructure, customer-site components, or deployment automation. Those require a separately approved implementation phase and must bind through the module's existing contracts.

