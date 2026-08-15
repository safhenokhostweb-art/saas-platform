# Legacy production deployment path — intentionally blocked.
#
# Production releases must be created from an explicit source revision and
# executed by deployment-system. This script must not stage, commit, push,
# archive, or transfer the current working tree.
Write-Error 'Legacy direct deployment is blocked. Use deployment-system with a committed source revision.'
exit 1
