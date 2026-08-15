#!/usr/bin/env bash
# Legacy production deployment path — intentionally blocked.
# Production releases are owned by deployment-system and must use a committed
# source revision. This script never mutates Git state or contacts production.
set -euo pipefail
echo 'Legacy direct deployment is blocked. Use deployment-system with a committed source revision.' >&2
exit 1
