# FidaERP reference UI and isolated demo fixtures

## Design references

The authorized references are the ten 1648px screenshots supplied in chat on 23 October 2026: Dashboard, Profile, Login, Registration, Sales List, Add Sale, KPI Overview, Charts & Analytics, Settings and Modal Samples. The tenant workspace uses their layout and visual language with FidaERP branding instead of the original NexaERP labels.

Implemented:
- Header with global module search and keyboard shortcut; dual rail/context navigation; quick-action drawer
- Navy, blue and light-gray component system with cards, metric summaries, mobile navigation, line icons, panels, tables, filters and forms
- Dashboard, KPI and analytics views populated from posted journals, stock balances and completed business transactions
- Profile and password forms; company setting and branch creation
- Searchable sales invoices, settlement modal, draft sales form with live calculations
- Existing login and registration routes continue to use FidaERP branding

Not yet functional: approvals, tasks, MFA, manufacturing UI, full payroll, full purchase-order lifecycle and custom tenant theme persistence. They are not advertised as functional in the application.

## Safe demonstration dataset

Code: \`database/seeders/ErpDemoTenantSeeder.php\`
Command: \`php artisan erp:demo-seed demo-showcase --confirm\`

The command requires staging/local/testing, an ACTIVE tenant whose slug starts with \`demo-\`, a fully provisioned separate database and empty finance/stock transaction tables. Production and regular customer tenants are rejected. It runs inside a transaction, records a seed marker and refuses duplicate runs.

Generated demo examples include:
- 15 customers, 8 suppliers, 25 products (variants) in 5 categories
- 20 employee rows, a second warehouse, 17 goods receipts, 45 sales invoices
- Opening capital posted as a balanced journal
- Posted invoice payments, supplier settlements and a warehouse transfer

Demo data is explicitly fictitious, and should never be used for invoices sent to actual customers or official financial reporting.

## Acceptance testing

Run tests isolated from staging tenant data:

\`\`\`
php artisan view:cache
php artisan test
\`\`\`

The fixture test checks idempotence, non-demo tenant rejection, accounting balance and rendered UI with seeded data. Real MySQL tenant login, session isolation, permissions and actual browser visual alignment require separate staging acceptance testing.

## Deployment

Staging application directory: \`/home/fidaerp/staging/application\`.
Actual cPanel document root: \`/home/fidaerp/staging/public\`.
Copy the versioned \`public/fida-erp/reference-ui.css\`, \`ui-icons.svg\` and \`ui-shell.js\` into the external document root after updating staging.
Do not run database migrations on central production or customer data.
Run tenant migrations individually after confirming tenant backups and schema status.

## Commercial readiness

This update improves the working accounting/stock/sales foundation and its presentation. It does not complete the entire ERP. Missing modules from the original full-ERP scope still require implementation, real business testing and security acceptance before sale.
