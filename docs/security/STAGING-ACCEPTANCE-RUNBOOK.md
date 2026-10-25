# FidaERP Batch 02 — Staging Acceptance Runbook

**Status: NOT EXECUTED.** Use a dedicated staging installation with disposable accounts and databases. Do not run these checks against production or customer data. The passing GitHub Actions suite does not certify the deployed host.

## Gate A — Identify the environment (read-only)
Record the Git commit, staging hostname, PHP/Laravel version, timestamp, and operator in a private release record. Never paste .env, database URLs, passwords, APP_KEY or session cookies into the record.

From the **staging** application directory, with the correct deployment account:
```sh
php artisan about --only=environment
php artisan route:list --path=erp
php artisan config:show session
php artisan config:show cache
```
Review the output locally. Redact sensitive configuration before attaching evidence. Confirm APP_ENV is staging, APP_DEBUG is false, HTTPS is enforced, session cookies are Secure and HttpOnly, and the reverse proxy forwards only trusted host/IP information. Verify that the session domain does not permit cross-tenant cookie sharing.

## Gate B — Tenant and platform separation
Use two explicitly created disposable tenants with separate MySQL users and databases. Confirm:
1. Tenant A login and logout work; tenant B cannot use tenant A's session.
2. Tenant A cannot read tenant B's sales, customers, products or invoice PDF by changing IDs or host headers.
3. Platform routes reject tenant users; tenant routes reject platform sessions.
4. Suspended tenant and disabled user are denied; a queued tenant job must re-check lifecycle state.
5. On a tenant DB connection, verify SELECT from the other tenant's database is denied. Do not test using production data.

## Gate C — Branch permissions
Use an owner-approved test user-to-branch map. Run `php artisan erp:audit-branch-memberships <tenant-id>` for each active test tenant. The command reports counts and grants no access. Check allowed and denied warehouse receipts, sales posting, invoice PDFs, role revocation, and branch membership restoration. Never blanket-assign legacy users to branches.

## Gate D — Provisioning
Provision only into a pre-created disposable database with a least-privilege MySQL account. Confirm the HQ branch, main warehouse and new owner branch membership are present. Test a deliberately invalid hostname and ensure no tenant is created. Verify that failed provisioning leaves no active tenant and has an operator-approved cleanup plan.

## Gate E — Backup and recovery
Use a disposable staging tenant and a separate restore database. Create an encrypted backup through the approved operations process, restore it to the isolated destination, and verify row counts, foreign keys, a sample sale and invoice PDF. Document recovery time, retention, access permissions, and cleanup. Never overwrite the source database.

## Gate F — UI and operations
Check mobile/tablet navigation, desktop layout, English/Dari/Pashto direction and number rendering, login throttling behind the real proxy, logs without secrets, queue context cleanup, and alerts for tenant connection failures.

## Evidence and decision
For each gate record **PASS / FAIL / NOT RUN**, environment, time, tester, evidence link, and remediation issue. No check is considered PASS based solely on code inspection or GitHub CI.

**Release decision: HOLD** until all gates pass, outstanding legacy access mappings are explicitly approved, and the owner separately authorizes merge and deployment.
