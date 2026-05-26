# Distributed Queue Governance — Phase 8.5

## Overview

Queue governance provides tenant-affinity routing, shard-based load distribution, and hotspot prevention across all queue partitions.

## Queue Partitions

| Partition     | Shards | Priority | Max Workers |
|---------------|--------|----------|-------------|
| default       | 1      | 50       | 4           |
| high          | 2      | 90       | 8           |
| low           | 1      | 10       | 2           |
| notifications | 2      | 70       | 4           |
| reports       | 1      | 30       | 2           |

## Tenant Affinity Resolution

```
TenantQueueAffinityResolver.resolveShardedQueue(tenantId, jobType)
    ↓
1. Resolve base queue (plan-based: enterprise→high, trial→low)
2. Compute shard: tenantId % shardCount  (deterministic)
3. If preferred shard is hotspot → route to least-loaded shard
```

## Hotspot Detection

Shard depth ≥ 200 jobs → marked as hotspot in cache (5min TTL). The `QueueShardManager` detects hotspots on each `coordinationStatus()` call.

## Imbalance Detection

`QueueRuntimeBalancer`: if max_shard_depth / avg_shard_depth ≥ 3.0 → rebalancing needed.

## Isolation Guarantee

- Tenant affinity keys are per-tenant per-jobType, stored independently
- Shard assignment is deterministic (tenant_id mod shard_count) — no cross-tenant data mixed
- Queue names never embed tenant IDs in job payloads — isolation is routing-only

## Artisan

```bash
# Queue coordination status with hotspot detection
php artisan scalability:simulate-load --pending=1000
```
