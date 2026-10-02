# Project release contract

`release-policy.json` is consumed by the deployment-system canonical
`deployment/release-prepare.php` command. Run it from an isolated `main`
worktree with development dependencies installed and the test database
configured:

The Phase 3C regression also requires the existing ignored FoodMart reference
tree and `storage/app/theme-baselines/foodmart/integrity-manifest.tsv`. Copy
those test fixtures into a new worktree; the test verifies their fixed manifest
digest and all 70 file digests. Missing fixtures fail the gate.

```sh
php ../deployment-system/deployment/release-prepare.php check
```

Create the untracked `release-task.json` using the deployment-system example,
with the production baseline SHA and exact task-owned paths. The check rejects
unowned changes, unsafe migrations, syntax errors and any failing required test.
No platform test exclusions or known-failure exemptions are enabled. The
customer policy separately validates its user-authorized version-specific
baseline registry; see `../../customer-site/.deployment/README.md`.

`releases/canvas-homepage.json` records the release version, author, creation
time and both original homepage source commits. These source commits are
provenance, not the SHA of a later metadata commit. The Hub's immutable
Release/Artifact/Deployment records identify each exact built and activated
commit and retain the authoritative agent evidence. Release identity fields
were completed through canonical release services without changing that
evidence or readiness criteria.

The four obsolete expectations are corrected, not suppressed: typography
tests verify the actual included CSS partials; customer navigation tests check
visible block text and anchor destinations rather than saved editor attributes.
Artifact construction, integrity verification, approval, activation and health
checks remain owned by the deployment-system pipeline.
