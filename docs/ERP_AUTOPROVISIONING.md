# FidaERP: automatic tenant provisioning

## What happens after approval

1. A platform administrator approves a pending registration. A database-queued job is created, not executed inside the public registration request.
2. A unique provisioning run and encrypted tenant credentials are recorded centrally.
3. A restricted cPanel MySQL database and user are created via UAPI and granted privileges on that database.
4. Isolated tenant migrations run on the new database. Default business settings, account chart, first branch, warehouse, permissions, owner account and currencies are created transactionally.
5. The hostname must resolve to the platform server and serve the Laravel /up endpoint over valid HTTPS. Only then is the tenant marked active. If DNS/certificate or hosting configuration is missing, the tenant stays awaiting_domain.
6. Failed or pending provisioning can be retried from Platform > Registrations. The command erp:registration-provision ID --sync is available for secure CLI recovery.

## Security and recovery

- Never run these jobs from public tenant registration forms; only platform-admin approval triggers work.
- A deterministic MySQL name derived from the registration ID is never reused for other registrations.
- A collision with a pre-existing cPanel resource is rejected rather than silently reusing a database.
- Credentials are encrypted in the central Tenant model. cPanel UAPI receives the password via a temporary private file, not a shell command argument.
- Steps are recorded after successful API actions, and Laravel migrations can resume. Unexpected orphaned cPanel resources require manual investigation; no destructive cleanup occurs automatically.
- Each registration has a unique queue job and an application-level provisioning lock to limit concurrent execution.
- Production must never be used as a demo tenant. Staging demo data is seeded separately and only for demo-* tenants.

## cPanel staging runtime

Use the existing cPanel account's cron to process the dedicated queue reliably:

\`* * * * * cd /home/fidaerp/staging/application && /usr/bin/flock -n /home/fidaerp/staging/application/storage/framework/erp-provision-worker.lock /usr/local/bin/php artisan queue:work database --queue=erp-provisioning --stop-when-empty --tries=1 --timeout=160 --max-time=180 >/dev/null 2>&1\`

The DB_QUEUE_RETRY_AFTER setting must exceed the worker timeout (default 210 seconds in this version). The worker must run under the authorized fidaerp hosting account.

## Required external setup

- DNS records for tenant subdomains such as BUSINESS.stage.fidaerp.com must point at the staging server.
- Valid trusted SSL certificates must cover each tenant hostname.
- The hosting web server must map tenant hostnames to /home/fidaerp/staging/public.
- Until these are working, the database and owner user can be provisioned, but tenant status remains awaiting_domain and tenant login is deliberately disabled.
- Production domain and HTTPS automation must be separately configured and verified before commercial rollout.

## Acceptance checks

- Review a dedicated test registration on staging.
- Confirm cPanel created only the expected tenant database and user.
- Inspect provisioning run stage, tenant migrations and owner account.
- Check that domain readiness blocks activation when DNS or HTTPS is invalid.
- After domain is correctly configured, requeue and confirm that tenant login and the main workspace are accessible.
- Never approve or mutate customer registrations merely to test the provisioner.
