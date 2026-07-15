# AI Asset Modification Rules

## AI may propose

- semantic recoloring;
- resizing and responsive adjustment;
- outline/filled style changes;
- material and shadow changes;
- theme adaptation;
- safe logo replacement where an editable placeholder exists;
- industry-specific variants; and
- a new customer/site variant or a new master draft version.

## AI may not

- modify an immutable master or published file in place;
- remove or change platform/customer ownership;
- bypass editable properties, validation, approval, or version creation;
- expose private metadata, prompts, logo analysis, provider payloads, or source definitions;
- overwrite a published asset;
- publish or approve its own proposal;
- add scripts, external URLs, unsafe SVG features, unapproved fonts, or dependencies; or
- point one customer at another customer's private variant.

## Required workflow

1. Resolve the stable master ID and exact version.
2. Establish Platform Admin authority.
3. Copy the source into a private draft.
4. Limit changes to editable properties.
5. create a new version or customer variant.
6. Validate schema, XML, theme, accessibility, security, size, and rendering.
7. Compare parent and proposal.
8. Wait for Platform Admin approval.
9. Publish only through the future authorized platform workflow.

Master assets remain platform-owned and immutable. Customer/site variants retain their source master identity and independent ownership manifest.

