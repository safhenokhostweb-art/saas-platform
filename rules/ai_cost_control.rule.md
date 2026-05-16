AI Cost Control Rules

This project uses external AI models which incur usage costs.

AI assistants must follow strict cost efficiency rules when generating code or suggesting architecture.
1. Minimize AI Calls

AI calls must be minimized.

Multiple tasks should be combined into a single request when possible.

Example:

Bad:

    call AI to generate title
    call AI to generate description
    call AI to generate tags

Good:

    one call generating title, description, and tags.

2. Avoid Redundant Requests

AI must avoid repeated requests using the same data.

If a result can be reused, caching should be preferred.

Suggested mechanisms:

    Laravel Cache
    database storage
    response reuse

3. Centralized AI Gateway

All AI calls should go through a centralized service.

Example location:

app/Services/AI/

Direct AI calls from controllers or models are NOT allowed.

Controllers must call an AI service instead.
4. Model Selection Awareness

When multiple models exist:

    local models should be preferred for low‑importance tasks
    paid models should be used only when required

Example strategy:

low complexity → local model

high quality generation → external model
5. Prevent Infinite or Recursive AI Calls

AI must not create systems where:

    AI responses trigger additional AI calls automatically
    loops cause repeated AI usage

All AI requests must have clear stopping conditions.
6. Prompt Size Control

Prompts must be kept minimal.

Avoid sending:

    entire documents
    full database dumps
    large irrelevant contexts

Only required information should be sent to the model.
7. Logging and Monitoring

AI calls should be logged when possible.

Useful metrics include:

    model used
    token usage
    request frequency

This helps detect abnormal AI usage.
8. Failover Strategy

If an external AI provider fails or balance is insufficient, the system should fall back to a local model when possible.

Example:

DeepSeek unavailable → fallback to local model.

This prevents service disruption and uncontrolled retries.
