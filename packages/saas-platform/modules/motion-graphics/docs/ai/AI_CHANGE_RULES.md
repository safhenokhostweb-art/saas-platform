# AI Change Rules

## Allowed

AI may propose colors, text, timing, speed, effects, particles, positions, layout, theme, and responsive behavior only when explicitly allowed by `editable_schema_json`. Changes apply to a copied source definition, create a new draft, and require validation, comparison, and Platform Admin approval.

## Requires approval

Obtain explicit architectural approval before changing:

- architecture or package boundaries;
- libraries or external dependencies;
- rendering engine or output contract;
- authentication, authorization, isolation, encryption, signing, or security;
- the published runtime contract; or
- runtime consumer logic.

Approval to explore is not approval to publish.

## Forbidden

Never:

- modify database records directly for an animation edit;
- bypass models, policies, services, validation, approval, or audit controls;
- edit, replace, remove, or collapse version history or assets;
- expose sources, schemas, AI metadata, prompts, snapshots, or private previews;
- add authoring or AI mutation features to tenant applications;
- edit tenant runtime during an ordinary content change;
- let tenants create, approve, publish, roll back, regenerate, or delete;
- publish automatically or treat AI as an approver;
- weaken permissions or isolation; or
- place secrets or tenant data in definitions, metadata, logs, or assets.

Separately authorized migrations and application-managed persistence are architecture work, not permission for direct record manipulation. If an edit is not clearly allowed by both this file and the editable schema, require approval. Reject requests that conflict with ownership or security.
