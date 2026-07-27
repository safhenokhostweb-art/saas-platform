# Performance Budget Contract

Budgets apply to an individual published motion package after optimization. A template-level budget must also cap simultaneous assets.

| Metric | Excellent | Good | Warning | Fail |
|---|---:|---:|---:|---:|
| Compressed transfer size | <= 50 KB | <= 100 KB | <= 200 KB | > 200 KB |
| SVG DOM nodes | <= 250 | <= 500 | <= 1,000 | > 1,000 |
| Concurrent animated elements | <= 12 | <= 24 | <= 40 | > 40 |
| Timeline duration | <= 8 s | <= 15 s | <= 30 s | > 30 s |
| Target load on reference 4G | <= 300 ms | <= 700 ms | <= 1,500 ms | > 1,500 ms |
| Long tasks during start | 0 | <= 1 | <= 3 | > 3 |

Scoring uses the worst metric band: `excellent=100`, `good=85`, `warning=65`, `fail=0`. Security, broken references, accessibility, or reduced-motion failures override the numeric score and fail the release.

Template guidance: no more than two high-intensity motions above the fold, pause offscreen work, lazy-load below-fold assets, avoid expensive filters/masks, prefer transform/opacity, and measure on representative low/mid mobile hardware. The checker validates declared budgets; real-browser profiling remains a mandatory integration gate.

