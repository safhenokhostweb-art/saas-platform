AI Agent Behavior Rules

This file defines the behavioral rules for AI assistants working in this repository.

These rules control how the AI must behave when analyzing or modifying the codebase.

All AI agents must follow these rules before generating code, modifying files, or suggesting architecture changes.
1. Repository Awareness

Before writing any code, the AI must:

    Inspect the existing repository structure
    Identify relevant modules, services, and architecture
    Follow existing patterns already used in the project

AI must never invent project structures that do not exist in the repository.

If the repository does not contain enough information, the AI must ask for clarification.
2. No Hallucinated Architecture

AI must NOT invent:

    database tables
    migrations
    services
    APIs
    models
    configuration files
    environment variables

unless they already exist in the repository or are explicitly requested by the developer.

If unsure, the AI must say:

“Repository evidence not found.”
3. Rule Compliance

This project uses an AI Rule System.

AI must enforce all rule files that are provided in the context.

Rule files may originate from:

    /rules/*.md
    /rules/*.mdc
    *.rule.md

These rules define architecture and engineering constraints.

AI must never generate code that violates these rules.
4. Safe File Modification

AI must follow these file safety rules:

AI must NOT:

    delete existing files
    overwrite large files
    refactor multiple modules at once
    perform destructive operations

unless the developer explicitly requests it.

When modifying files, AI should:

    modify only the necessary section
    preserve existing code style
    avoid unrelated changes

5. Incremental Development

All development must be done step by step.

AI should:

    analyze the task
    check repository structure
    apply rules
    propose minimal changes

Large refactors must be split into smaller safe steps.
6. Laravel Architecture Rules

When working with Laravel projects, AI must follow Laravel conventions:

    Controllers handle HTTP logic
    Services contain business logic
    Models represent database entities
    Config files store configuration
    Commands are used for automation tasks

AI must avoid placing business logic in controllers.
7. AI Cost Awareness

This project uses external AI models.

AI must avoid patterns that can increase cost unnecessarily.

Examples:

    generating large unnecessary prompts
    redundant API calls
    repeated model requests
    unnecessary data processing

Efficiency should always be preferred.
8. Ask When Uncertain

If the AI cannot determine the correct action from repository evidence, it must ask the developer instead of guessing.

Examples:

    missing model
    missing service
    unclear architecture
    conflicting rules

9. No Hidden Behavior

AI must not introduce:

    hidden background tasks
    undocumented services
    unexpected API calls
    silent external dependencies

All generated code must be transparent and understandable.
10. Developer Authority

The developer always has final authority.

If the developer explicitly requests an action that conflicts with a rule, the AI should warn about the conflict but still follow the developer’s instruction.
Summary

AI assistants working in this repository must:

    follow repository architecture
    enforce rule files
    avoid hallucination
    modify code safely
    work incrementally
    prioritize clarity and safety
