# Cascading Failure Prevention — Phase 8.4

## Overview

The cascade protection stack prevents a single component failure from propagating through the dependency graph.

## Dependency Graph

Static adjacency list in `DependencyImpactGraph::DEPENDENCY_MAP`:

```
database      → queue, cache, tickets, forms, notifications, telemetry
cache         → queue, telemetry
queue         → notifications, telemetry
external_api  → notifications, telemetry
```

## Blast Radius Analysis

`RuntimeBlastRadiusAnalyzer::analyze(component)` returns:
- `impacted`: list of transitively affected components
- `blast_radius_score`: count of affected components
- `risk_level`: low | medium | high | critical
- `is_critical`: true if blast_radius ≥ 3 or component is in critical set
- `containment_order`: recommended order to contain failures

## Failure Containment Flow

```
CascadingFailureDetector.detect()
    ↓ (detects ≥2 failures or cascade pattern)
FailureContainmentCoordinator.contain(component)
    ↓
RuntimeBlastRadiusAnalyzer.analyze()
    ↓
ModuleCircuitRegistry — open module circuit
    ↓ (if critical risk)
Open downstream component circuits too
```

## Circuit Breaker States

```
CLOSED → (failures >= threshold) → OPEN
OPEN   → (recovery timeout elapsed) → HALF_OPEN
HALF_OPEN → (successes >= successThreshold) → CLOSED
HALF_OPEN → (any failure) → OPEN
```

## Cascade Detection

`CascadingFailureDetector` maintains a 120-second failure window per component via cache. A cascade is detected when:
- ≥2 components have failures AND downstream relationships exist (via dependency graph), OR
- ≥2 component failures detected regardless of relationship (`CASCADE_THRESHOLD=2`)
