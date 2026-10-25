# FidaERP Batch 02 — Security and Readiness Review

Status: **NOT APPROVED FOR PRODUCTION**. This is a development-branch review, not a penetration test or production sign-off.

## Scope
Phases 2.34–2.46 covered tenant host resolution, proxy boundaries, session scoping, central/tenant separation, worker cleanup, role permissions, branch/warehouse authorization and disposable MySQL migrations. Phase 2.47 added membership restoration regression coverage. Phase 2.49 introduced distinct tenant/platform login throttles. Phase 2.48 documented safer example environment defaults.

## Verification gates
- Tenant Security Baseline GitHub Actions: must pass on the final head commit.
- Disposable MySQL Integration GitHub Actions: must pass on the final head commit, including fresh central/tenant migrations and schema isolation assertions.
- Check both workflow conclusions at the final commit before merging; previous green runs are not sufficient.
- Perform staging browser smoke tests for login/logout, suspended tenant, disabled user, role revocation, branch membership revocation/restoration, sales posting, inventory receipt, invoice PDF, mobile/tablet navigation.
- Verify no customer data, credentials, or secrets are exposed in logs or artifacts.

## Known risks and follow-ups
1. **Legacy branch membership migration:** newly provisioned owners are now explicitly assigned to the created HQ branch (commit `00c75eb`); existing users without `branch_user` rows will still lose warehouse access by design. Provide explicit authorized administrator assignment/onboarding; do not auto-grant all users access.
2. **Multi-company support:** current branch isolation does not implement independent legal-company boundaries within a single tenant.
3. **Tenant provisioning:** database creation, credential rotation, backup/restore, and rollback require operational staging validation.
4. **Login throttling:** account/IP limits require a durable, shared cache backend in multi-server production; validate reverse-proxy IP trust.
5. **Secrets:** sample environment contains placeholders only. Production secrets must be injected through secret management, rotated on exposure, and excluded from Git and CI logs. Review existing Git history separately.
6. **Session security:** enforce HTTPS, secure cookies and correctly scoped session domains in production; do not reuse development example settings unchanged.
7. **Tenant lifecycle:** validate suspension and reactivation against real central and tenant databases and active sessions; include queue/scheduler paths.
8. **Operational readiness:** no confirmed load testing, backup restore drill, observability review, disaster recovery drill or external security assessment.

## Release decision
**HOLD** until latest CI is green, staging acceptance is documented, legacy branch membership access is resolved, and the owner explicitly authorizes merge/deployment. Never deploy from this review automatically.

## Phase 2.53 development checkpoint
- CI passed for commit `4bee694` (both security and disposable MySQL workflows).
- Later commits added lifecycle flag checks, safe environment contract tests, tenant owner HQ membership assignment, read-only legacy branch audits, and central DB_URL isolation. Both workflows passed on `4bee694`. **Recheck CI on any newer final head.**
- The tenant owner provisioning regression test currently checks the command's source contract; it does **not** substitute for an end-to-end tenant provisioning smoke test on a disposable MySQL database.
- Development hardening can be considered implemented, but staging acceptance, legacy tenant branch migration, backup restore and production configuration remain release blockers.

## Legacy branch migration preflight (read-only)
1. Run `php artisan erp:audit-branch-memberships <tenant-id>` for each active tenant from an authorized maintenance environment. Record only the counts, not user names or credentials.
2. For any nonzero missing count, obtain the tenant owner's explicit branch-to-user mapping and apply it through an authorized, audited administration workflow. Never infer permissions from an existing owner role or grant all users every branch.
3. Repeat the audit and test permitted/restricted warehouses, sales and invoice PDFs before scheduling cutover.
4. Suspended tenants intentionally fail the audit until reactivated by an authorized administrator.
5. The command is read-only; its presence does not certify that a legacy tenant has been migrated.

## Central DB_URL isolation check
- TenantConnector explicitly removes the inherited central MySQL `url` key before configuring `erp_tenant` (commit `bec7803`). A configured central `DB_URL` must never override tenant database credentials.
- Disposable MySQL regression coverage injects a deliberately invalid central URL and verifies the tenant's own database, username and access boundaries (commit `391c04a`).
- Before release, verify the deployed configuration cache and each tenant's database account privileges; no tenant account should be able to read another tenant schema.

## Staging acceptance evidence checklist (not yet executed)
Record the environment, timestamp, test tenant IDs, operator and pass/fail evidence without passwords, customer records or connection secrets.
- [ ] HTTPS and Secure/HttpOnly/SameSite host-only session cookies verified in browser.
- [ ] Tenant A and B login/logout and cross-host cookie/session replay denial verified.
- [ ] Tenant status and suspension timestamp each deny access; reactivation requires both cleared/active.
- [ ] Disabled users and revoked roles or branch memberships lose access immediately.
- [ ] Restricted warehouse sales, stock receipts and invoice PDF are denied; authorized branch restoration works.
- [ ] Branch membership audit returns zero missing active memberships after explicit owner-approved assignments.
- [ ] Shared cache-backed login throttling and trusted proxy/client IP behavior verified.
- [ ] Fresh tenant provisioning smoke, rollback on failure, and database privilege boundaries verified.
- [ ] Encrypted backup created and successfully restored to an isolated staging database; integrity checked.
- [ ] Queue/scheduler tenant context cleanup, logs, alerts and recovery procedure reviewed.
- [ ] Mobile/tablet navigation and RTL language layouts manually checked.

**No checkbox above is automatically satisfied by green GitHub Actions.**
