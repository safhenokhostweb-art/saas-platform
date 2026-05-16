Anti‑Hallucination Rules

This rule prevents AI assistants from inventing code, structures, or system components that do not exist in the repository.

AI must rely only on verified repository evidence or explicit developer instructions.
1. Repository Evidence Requirement

Before generating code, AI must verify that the required structures exist in the repository.

Examples of required evidence:

    existing models
    database tables
    services
    configuration files
    environment variables
    APIs
    directories

If repository evidence cannot be found, AI must state:

“Repository evidence not found.”

and request clarification.
2. No Invented Database Structures

AI must never invent:

    database tables
    database columns
    migrations
    relationships

unless:

    they already exist in the repository, or
    the developer explicitly asks to create them.

AI must not assume schema details.
3. No Invented Services or APIs

AI must not create or reference:

    services
    endpoints
    background jobs
    integrations

unless they exist in the repository or are explicitly requested.

If an integration is mentioned but not visible in the repository, AI must ask for confirmation.
4. No Fabricated Configuration

AI must not assume the presence of:

    environment variables
    API keys
    configuration entries
    feature flags

If configuration is required but not visible, AI must ask the developer.
5. Respect Existing Project Patterns

AI must follow the patterns already present in the repository.

Before creating a new:

    service
    controller
    model
    command

AI must verify whether similar functionality already exists.

Duplicate implementations must be avoided.
6. Evidence‑Based Code Generation

Generated code must be based on one of the following:

    existing repository structures
    rule files provided in context
    explicit developer instructions

If none of these provide sufficient information, AI must not guess.
7. Explicit Assumptions

If an assumption is unavoidable, AI must clearly label it.

Example:

“Assumption: The project contains a User model in app/Models/User.php.”

Assumptions must be minimal and clearly stated.
8. Stop on Uncertainty

When repository evidence is missing or conflicting, AI must stop and ask the developer for clarification instead of generating speculative code.

Generating uncertain code is prohibited.
9. Rule Priority

These anti‑hallucination rules override convenience.

It is better for AI to ask questions or stop than to generate incorrect or invented code.
