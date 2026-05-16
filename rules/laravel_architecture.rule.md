Laravel Architecture Rules

This rule defines the mandatory architecture structure for Laravel projects in this repository.

AI assistants must follow Laravel best practices and existing repository patterns.
1. Controller Responsibility

Controllers are responsible only for:

    handling HTTP requests
    validating input
    calling services
    returning responses

Controllers must NOT contain:

    business logic
    database logic
    complex processing

All business logic must be placed in Service classes.
2. Service Layer

Business logic must be implemented inside:

app/Services/

Services are responsible for:

    application workflows
    external API communication
    orchestration between models and repositories

Controllers must call Services instead of implementing logic directly.
3. Model Responsibility

Models represent database entities.

Models may contain:

    relationships
    simple attribute helpers

Models must NOT contain:

    complex business workflows
    external API calls
    heavy logic

4. Database Changes

AI must NOT create or modify migrations unless the developer explicitly requests it.

Database schema changes require explicit developer approval.

AI must never invent database tables or columns.
5. File Placement Rules

AI must place files only in appropriate Laravel directories.

Examples:

Controllers → app/Http/Controllers

Services → app/Services

Models → app/Models

Console Commands → app/Console/Commands

Configuration → config/

AI must never create arbitrary directories.
6. Dependency Injection

Services must be injected via constructor injection.

Example:

Controller → inject Service

Service → inject required dependencies

Avoid static calls when dependency injection is possible.
7. Repository Awareness

Before creating new classes, AI must check whether similar functionality already exists in the repository.

Duplicate services or duplicate logic must be avoided.
8. Minimal Changes

When modifying existing code, AI must:

    modify only the necessary section
    preserve existing architecture
    avoid unrelated refactors

Large refactors require explicit developer approval.
