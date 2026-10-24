# FERP-011 — Disposable MySQL Integration Runner Contract

## Status
The prior database integration test was replaced with an explicit **incomplete** test because its open-transaction fixture conflicted with the tenant connector's purge/reconnect lifecycle. No database integration tests have passed.

## Execution design
1. Run inside a disposable isolated CI service with MySQL 8.
2. Create three new databases named `erp_test_central`, `erp_test_a`, `erp_test_b` using a CI-only administrator; do not give those credentials to the Laravel test process.
3. Create one least-privilege database user per database. The tenant users must not have access to the central database or the other tenant's database.
4. Run central and tenant migrations through dedicated CI bootstrap commands, not web requests.
5. Seed two tenants and their users in the disposable environment.
6. Run tenant A/B login, host switching, session separation, suspended tenant, cross-tenant customer access, and context cleanup tests.
7. Destroy the entire disposable MySQL service on job completion, including failures.

## Mandatory preflight
- Reject missing `APP_ENV=testing`.
- Reject any database name not beginning `erp_test_`.
- Reject reused database names or matching database users.
- Require an explicit CI-generated sentinel in each database.
- Reject production or staging hosts and non-loopback database hosts unless an explicitly reviewed CI service alias is used.
- Never call `migrate:fresh`, `db:wipe`, or `DROP DATABASE` against externally managed infrastructure.

## Acceptance gates
- Syntax checks pass.
- All integration tests actually execute (no skip/incomplete).
- A cannot read B's customers, users, or session.
- A tenant session cannot authenticate a request to the platform.
- Suspended/unknown hosts reject requests.
- No tenant context survives request completion.
- The CI environment is destroyed after each run.

This document is a runner contract, not evidence that the environment or tests are implemented.
