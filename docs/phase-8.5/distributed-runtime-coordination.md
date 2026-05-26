# Distributed Runtime Coordination — Phase 8.5

## Overview

Multi-node coordination contracts provide infrastructure-agnostic node awareness, workload distribution, and cluster health tracking. These are contracts — they work today on single-node setups and scale to clusters without code changes.

## Node Lifecycle

```
RuntimeNodeCoordinator.join()
    → NodeRegistry.register(nodeId, metadata)
    → NodeCapacityTracker.reportCurrent(nodeId)
    → Periodic: RuntimeNodeCoordinator.heartbeat()
    → RuntimeNodeCoordinator.leave()
    → NodeRegistry.deregister(nodeId)
```

## Node Health

Nodes that have not sent a heartbeat in 90 seconds are classified as `stale`. `DistributedRuntimeMap` separates active from stale nodes.

## Workload Routing

`RuntimeNodeCoordinator.routeWorkload()` always picks the **least-loaded active node** by utilization percentage. Falls back to current node if no active nodes are registered.

## DistributedRuntimeMap

Built on-demand and cached for 5 minutes. Structure:
```json
{
  "node_count": 3,
  "active_count": 2,
  "stale_count": 1,
  "cluster_utilization": 42.5,
  "nodes": {
    "host1-uuid": { "metadata": {}, "capacity": {}, "health": {} }
  }
}
```

## Infrastructure Independence

- No Kubernetes assumptions
- No vendor-specific APIs
- Cache-backed: works with any Laravel cache driver
- Compatible with single-server, VM fleet, or container orchestration
- `NodeRegistry.currentNodeId()` is stable across restarts (stored in cache)

## Future Multi-Region Extension

The `NodeRegistry` metadata accepts arbitrary fields. Adding a `region` field to each node's metadata enables region-aware routing without changing any contracts.
