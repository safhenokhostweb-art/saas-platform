# Legacy compatibility

`AuthenticateTenantApi` temporarily accepts legacy body or query `license_key` for deployed customer versions. It emits credential-free deprecation telemetry. A Bearer credential wins only when it matches the supplied legacy value; conflicts fail closed.

Remove the bridge after supported customer releases have migrated and telemetry shows no legacy transport for a full release window.

