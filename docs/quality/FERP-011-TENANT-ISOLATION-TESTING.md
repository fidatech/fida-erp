# FERP-011 Tenant Isolation — Security Verification Record

Status: **In progress; do not merge or deploy.** Last fully verified passing revision: `c29704cf4321544c54d3d40e4a52920aaa4876a6`.

## Last fully passing CI
- Baseline: https://github.com/fidatech/fida-erp/actions/runs/40177128898
- Disposable MySQL: https://github.com/fidatech/fida-erp/actions/runs/40177128965
- These runs do not validate subsequent changes until their own workflows pass.

## Verified at the last passing revision
- Separate MySQL tenant databases, least-privilege database users, tenant-specific customer reads.
- Authenticated cross-tenant replay rejected in both directions; unknown/suspended tenant host rejected.
- Tenant and central platform guard isolation, repeated tenant requests, logout revocation.
- File-backed session test fixture, host-only Set-Cookie header, HttpOnly, SameSite=Lax and HTTPS Secure cookie assertions.
- Explicit reverse-proxy allowlist configuration (not a live reverse-proxy test).

## Changes under verification
- Inactive tenant user session revocation in `ResolveErpTenant`.
- Regression test for disabling a user after login.
- Expanded GitHub Actions change-path coverage for authentication, sessions, and proxy settings.

## Remaining batch 2.34–2.53 review gates
- Test trusted vs untrusted proxy header behavior and host spoofing; validate deployment's actual proxy IPs.
- Browser cookie separation on sibling subdomains, HTTPS termination, CSRF and session persistence.
- Platform administrator authorization and user suspension across real persisted sessions.
- Tenant context cleanup on exceptions and queued/scheduled work.
- Role/permission and company/branch isolation, with explicit multi-company access rules.
- Full central and tenant migration tests instead of only minimal CI fixture tables.
- Credential handling, login abuse prevention, provisioning/suspension lifecycle and deployment hardening.
- Independent review of all findings and green CI on the final commit.

## Production guardrails
Never run the disposable fixture against real databases. Do not set trusted proxies to `*` or a publicly reachable address range. Configure `ERP_TRUSTED_PROXIES` with only actual proxy IPs, and set HTTPS-only cookies in production. Host-only cookies are expected (`SESSION_DOMAIN` unset). Require an explicit approval before merge or deployment.
