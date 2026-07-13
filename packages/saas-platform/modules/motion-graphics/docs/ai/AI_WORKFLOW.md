# AI Workflow for Motion Graphics

For a request to modify `iran-network`, the AI must follow this workflow:

1. **Establish authority.** Confirm this is a main-platform authoring request. Tenant requests never enter authoring.
2. **Locate the motion.** Resolve `motion_graphics.slug = iran-network` through established services, policies, and scopes. Never edit records directly.
3. **Load authorized context.** Within the Platform Admin boundary, load the source definition, editable schema, current version and lineage, relevant metadata, and rendering contracts. Never return them to tenants.
4. **Check allowed edits.** Validate the request against `editable_schema_json` and `AI_CHANGE_RULES.md`.
5. **Prepare a source proposal.** Edit only a copy of `source_definition_json`; never mutate current or historical records or assets.
6. **Create a draft.** Append the next version with its parent, snapshot, change description, and authenticated actor.
7. **Generate a preview.** Keep previews private and compile immutable versioned assets without authoring data.
8. **Validate and compare.** Run schema, rendering, integrity, authorization, isolation, security, and regression checks. Show the parent-to-draft difference.
9. **Wait for approval.** Stop at a reviewable draft. Never self-approve, directly publish, or silently advance deployments.
10. **Publish only after approval.** After explicit Platform Admin approval, only the authorized platform service may publish and update eligible deployments.

On failure, retain the published version and report the error. Corrective work may affect only an unpublished draft and must not rewrite history.
