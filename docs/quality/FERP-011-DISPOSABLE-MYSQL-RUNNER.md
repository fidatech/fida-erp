# FERP-011 — Disposable MySQL Integration Runner

## Status and evidence
The GitHub Actions disposable MySQL integration workflow passed for commit `9bc54e89131d3b4973557a07feb42e370f6f53db`:
https://github.com/fidatech/fida-erp/actions/runs/40141271095

The legacy `TenantIsolationIntegrationTest` remains explicitly incomplete and is not part of the passing workflow. The executed tests are `TenantHttpIsolationTest` and `TenantMysqlConnectionTest`.

## Current runner design
1. Start a disposable MySQL 8 service on a GitHub-hosted runner.
2. Provision central, tenant A, and tenant B databases with the `erp_test_*` prefix.
3. Grant distinct CI-only users access only to their corresponding databases.
4. Provision minimal central tenant and tenant user/customer/role/permission tables.
5. Execute PHP lint, tenant host login, authorized customer API isolation, session replay in both directions, unknown/suspended tenant handling, context cleanup, and MySQL permission-boundary assertions.
6. Destroy the runner and MySQL service after the workflow.

## Safety requirements
- Run only with `APP_ENV=testing` and `ERP_RUN_ISOLATION_TESTS=1`.
- Use test-only hosts, credentials, encryption keys, and data.
- Never run this suite against production or staging databases.
- Never run destructive database commands against externally managed infrastructure.
- Require distinct database names and users, and least-privilege grants.

## Verified versus outstanding
**Verified in CI:** the current HTTP fixture and physical MySQL permission-boundary tests pass.

**Not yet verified:** full tenant migrations, production session drivers and cookies, platform-admin versus tenant session separation, suspended tenant users, queue-worker reuse, and exceptional-path cleanup. The minimal CI schema is not a substitute for full application migration testing.

The workflow is a reproducible regression harness, not production certification. Re-run it after any security-relevant change and require passing results on the final reviewed commit.
