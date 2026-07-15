# Testing Guide

This module is complete and tested as a standalone package but is not currently connected to the main SaaS platform or customer websites.

Run from repository root:

    composer validate --strict --no-check-publish --working-dir=packages/saas-platform/modules/motion-graphics
    php vendor/bin/phpunit -c packages/saas-platform/modules/motion-graphics/phpunit.xml

Optional database variables are MOTION_TEST_DB_CONNECTION, MOTION_TEST_DB_HOST, MOTION_TEST_DB_PORT, MOTION_TEST_DB_DATABASE, MOTION_TEST_DB_USERNAME, and MOTION_TEST_DB_PASSWORD.

Use SQLite in-memory when pdo_sqlite exists. For MySQL checks, create a disposable module-only database and drop it after the suite. Never use development or production data.

The suite covers defaults/provider boot, ownership/authorization, classification, immutable lifecycle/rollback, manifest security, deployments, pipeline validation/cleanup, editable schema, fake integrations, migrations/rollback, indexes, constraints, and relationships.

No package-local static-analysis or style configuration currently exists; do not alter root tooling to add it.
