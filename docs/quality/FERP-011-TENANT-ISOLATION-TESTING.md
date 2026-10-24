# FERP-011: Tenant Isolation — Verified CI Scope

Status: **Disposable MySQL and security baseline CI passed on commit `9bc54e8`; final merge review remains open.**

## Evidence
- Tenant security baseline: https://github.com/fidatech/fida-erp/actions/runs/40141271058
- Disposable MySQL integration: https://github.com/fidatech/fida-erp/actions/runs/40141271095
- Both workflows completed successfully for commit `9bc54e89131d3b4973557a07feb42e370f6f53db`.

## Verified by automated tests
- Two tenant hostnames authenticate users against distinct disposable MySQL databases.
- Authorized customer API requests return the current tenant's customer and exclude the other tenant's customer.
- Tenant A-to-B and B-to-A authenticated session replay requests are rejected.
- Unknown and suspended tenant hosts return 404.
- Unauthenticated protected customer endpoints return 401.
- The tenant context is unbound after tested HTTP requests.
- Tenant database credentials cannot access the other tenant's database, as tested in the MySQL boundary suite.
- PHP syntax linting runs before the integration tests.

## Test environment
The GitHub Actions workflow provisions MySQL 8 on a disposable runner with central and tenant databases named `erp_test_central`, `erp_test_a`, and `erp_test_b`. Separate CI-only users are granted per-database privileges. The Laravel tests require `APP_ENV=testing` and `ERP_RUN_ISOLATION_TESTS=1`. Never point these tests at staging or production databases.

## Limitations and open review gates
- The CI fixture uses a minimal hand-built schema rather than full tenant migrations; this is **not** end-to-end deployment verification.
- The test session driver is `array`, not a real browser with production cookie and session-store configuration.
- Platform-admin session isolation, tenant user suspension, concurrent requests, queue workers, and tenant context cleanup after exceptional paths need dedicated coverage.
- The legacy `TenantIsolationIntegrationTest` remains explicitly incomplete; the passing CI workflows run the newer HTTP and MySQL boundary suites.
- Review deployment configuration, session cookie domain, HTTPS, and proxy host validation before production.
- No merge or production rollout is implied by these passing tests.

## Safe review sequence
1. Review the tenant resolver, guard lifecycle, and CI workflow changes.
2. Add missing platform/admin and production-like session regression tests.
3. Confirm the full migration schema and database grants in a disposable environment.
4. Require green CI on the final reviewed commit.
5. Request explicit merge/deployment approval.
