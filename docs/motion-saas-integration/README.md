# Motion Graphics SaaS Integration Contract

Status: **official integration-candidate design; not implemented**. This layer defines the future boundary between the SaaS Platform and the standalone Motion Graphics module. It creates no Laravel classes, bindings, routes, migrations, endpoints, deployment behavior, or production connection.

```text
SaaS Platform -> Motion Graphics Adapter -> Standalone Motion Graphics Module
```

The Platform supplies authenticated identity, tenant/site scope, licensing, orchestration, persistence transactions, and delivery authority. The module supplies asset/version/package/variant validation and published runtime projection semantics. The adapter translates versioned DTOs and fails closed; it does not duplicate either side.

The companion read-only validator is at `tools/motion-saas-integration-validator/`.

