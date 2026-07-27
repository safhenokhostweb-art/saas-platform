# Lifecycle Management

## State model

```text
Draft -> Review -> Approved -> Published -> Deprecated
  ^        |          |            |            |
  +-- new version on rejection/changes ---------+
```

Allowed forward transitions are `draft -> review`, `review -> approved`, `approved -> published`, and `published -> deprecated`. A rejected review returns work through a **new draft version**; it never mutates the reviewed version.

## Entities and lineage

| Entity | Owner | Version rule |
|---|---|---|
| Master Motion | Platform | immutable source lineage |
| Motion Version | inherited from parent | append-only semantic version |
| Customer Variant | customer/site, platform-custodied | new lineage referencing exact master version/checksum |
| Template Usage | tenant/site | references an exact published version and placement contract |
| Brand Adaptation | customer/site, platform-custodied | immutable variant with palette, provider, prompt reference, and source lineage |

Every version records an immutable ID, parent version, source checksum, artifact checksum, lifecycle state, change description, creator, timestamps, validation profile, approval evidence, license snapshot, and lineage.

## Publication and rollback

- A published artifact is immutable and content-addressed or stored at an immutable versioned path.
- Publication advances an explicit published pointer only after revalidation and Platform Admin approval.
- No silent replacement, mutable file overwrite, or history deletion is permitted.
- Previous versions remain available for audit and authorized restoration, subject to retention policy.
- Rollback creates a new `draft` copied from the selected historical version and records `restore_from_version`; it must pass review, approval, and publication again.
- Deprecation blocks new placements but does not silently break authorized existing usage.

## Concurrency and integrity

The future adapter must use optimistic concurrency on the expected current version and bind every review/approval/publication to the exact checksum reviewed. A stale pointer or checksum mismatch fails closed.

