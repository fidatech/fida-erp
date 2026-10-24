# FERP-011: Tenant Isolation Test Execution Guide

Status: **Draft — do not execute the database integration suite yet.**

## Scope
The current tests provide preliminary checks of tenant guard registration, tenant connection input validation, middleware presence, and opt-in MySQL integration scenarios. They do **not** prove cross-tenant data isolation.

## Safe test environment prerequisites
- Dedicated disposable MySQL instance with three separate **empty** databases, each named `erp_test_*`.
- Distinct database users with privileges restricted to their corresponding test databases. The central test connection must not have access to live SaaS data.
- `APP_ENV=testing` and `ERP_RUN_ISOLATION_TESTS=1` set explicitly in the test process, not in production `.env`.
- Never run this suite against production or staging tenant databases.
- Use a nonproduction app key and test-only email addresses.
- Snapshot or recreate disposable test databases out of band before each run.

## Current blockers
1. The integration test creates persistent tables and has no tearDown cleanup. A repeat run will not be reliable.
2. The central test model depends on the application's actual central connection and encryption key. Verify the connection before enabling the suite.
3. Tenant database users must be constrained to their respective databases; the current harness does not independently prove those grants.
4. Cross-host session reuse, protected resource access, suspended-user behavior, and post-request context leakage need additional assertions.
5. PHP/MySQL integration tests have **not** been executed. Do not merge on the strength of source review alone.

## Safe sequence
1. Review this test harness and complete cleanup/preflight isolation.
2. Confirm all three database names, host and grants in a disposable MySQL instance.
3. Run PHP syntax checks and the non-database test subset.
4. Run the database suite in a disposable CI environment.
5. Review failures, fix root causes, rerun, and only then request merge review.

No live credentials should be committed to the repository.
