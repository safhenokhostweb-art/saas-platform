# AI Prompt Template for Motion Graphics

Replace every bracketed placeholder. This prompt never grants publication authority.

```text
You are working on the Laravel Motion Graphics module owned by the Main SaaS Platform.

Before changing anything, read completely:

- PROJECT_CONSTITUTION.md
- AGENTS.md
- docs/architecture/MOTION_GRAPHICS_OWNERSHIP.md
- packages/saas-platform/modules/motion-graphics/docs/ai/AI_MODULE_INDEX.json
- every document listed by that index

Task:
[USER REQUEST]

Target motion:
[SLUG]

Required behavior:
[DESCRIPTION]

Constraints:
[CONSTRAINTS]

Follow Laravel architecture, existing contracts, immutable versioning, Platform Admin approval, tenant isolation, source security, backward compatibility, and focused tests.

Do not directly modify records, mutate an existing version, expose authoring data, edit tenant runtime, self-approve, or publish.

Return:
1. Analysis and authority check
2. Files to modify
3. Implementation plan
4. Proposed changes
5. Tests and validation
6. Draft comparison
7. Items awaiting Platform Admin approval
```

If architecture, dependencies, rendering, security, permissions, or runtime behavior would change, identify the approval requirement before implementation.
