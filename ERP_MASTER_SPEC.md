# FidaERP Master Specification
Version: 1.0 | Baseline: 2026-10-22 | Status: Approved development scope

## Product and environments
FidaERP by Fida Technologies is a commercially usable SaaS ERP for Afghan wholesale, retail, trading, importing, distribution and service businesses, expandable internationally. Laravel 13, PHP 8.4+, MySQL 8+, Blade/Vite/Bootstrap 5, Select2, server-side DataTables, SweetAlert2, ApexCharts/Highcharts, queues, scheduler, REST APIs, mPDF. Production: https://fidaerp.com (`main`); staging: https://stage.fidaerp.com (`development/admin-redesign`). Preserve current code, configuration, data and working functionality; never rebuild blindly or commit secrets.

## Architecture
Modular monolith with a **central SaaS database** and **one isolated MySQL database per tenant**. Central stores platform administrators, registrations, tenants, domains, encrypted database credentials, provisioning state, plans, subscriptions, billing, payments, resellers, entitlements and audit logs. Tenant DB stores business users, roles, branches, warehouses, products, stock, customers, suppliers, sales, purchases, POS, double-entry accounting, HR/payroll, documents and audit history. Resolve tenant by verified hostname; never trust request-supplied tenant IDs. Reset tenant connection/auth state for each request and queue job. Use least-privilege DB users and enforce isolation with tests.

## Registration and provisioning
Registration -> pending -> platform review -> approved-for-provisioning -> idempotent queued provisioning -> verified isolated database and user (authorized cPanel UAPI or separately configured operator) -> tenant migrations and seeds -> business owner account -> health verification -> active -> notification and audit. Reject and retry states must be explicit. Never activate after partial failure. Reserve unique slug/domain and prevent duplicate jobs. Use stored password hash securely or one-time account activation; never log plaintext credentials. Do not assume the Laravel DB account has CREATE USER/CREATE DATABASE privileges. Provisioning jobs must have retry safety and operator-visible failure details without exposing secrets.

## SaaS platform modules
Registration review, tenant management, provisioning/retry/status, subscription/trial/grace/suspension and entitlement enforcement, plan CRUD, billing/invoices/payments, reseller commissions/settlements, platform roles, notifications, audit history, backups and operational health. Platform statistics must be derived from real data.

## Tenant modules
1. Company/branches/warehouses/settings, fiscal periods, document numbering, profiles, users, invitations, roles and granular permissions.
2. Customers, suppliers, payment terms, credit limits, statements and opening balances.
3. Products, categories, brands, units, variants, barcodes, prices, batches and expiries.
4. Inventory ledger, goods receipt/issue, transfers, adjustments, counts, valuation, reorder and stock aging.
5. Purchasing: requisition, quotation, order, receipt, invoice, returns, payments, landed/import costs.
6. Sales: quotation, order, invoice, delivery, returns, receipts, discounts, tax, partial payment, customer pricing.
7. POS: barcode, cashier shift, receipts, multiple payment methods, returns, refunds and reporting.
8. Finance: hierarchical chart of accounts, balanced immutable posted journals, reversals, general ledger, receivables/payables, cash/bank, petty cash, reconciliation, closing, P&L, balance sheet, trial balance, cash flow, aging and statements.
9. Multi-currency: AFN/USD/PKR/IRR/EUR/AED plus configured currencies, historical FX rates, base-currency valuation and realized/unrealized exchange differences. Never overwrite historical posting rates.
10. HR/payroll: employees, departments, attendance, leave, holidays, contracts, configurable/versioned salary tax, advances, payroll postings and payslips.
11. Secure documents, attachments, versioning and audit logs.
12. Unified real-data dashboards and reports with filters and asynchronous PDF/Excel/CSV exports.

## UI and localization
Premium institutional NexaERP-inspired design with FidaERP identity: navy/white/blue, top header, icon rail, contextual sidebar, global search, breadcrumbs, responsive dense tables, modals, loading/empty states, light/dark, keyboard shortcuts, charts, accessible interactions. Consistent design across login, registration, SaaS administration, all ERP lists/forms/details, POS, reports and settings. English, Dari and Pashto, correct RTL/LTR, Vazirmatn, Jalali and Gregorian dates, Asia/Kabul defaults, multilingual mPDF invoices/reports.

## Engineering rules
- Build complete end-to-end workflows, not placeholder pages. Each module requires schema, service, authorization, validation, UI, reporting, audit and automated tests.
- Use decimal math for money/quantity, database transactions, idempotency keys, concurrency control, immutable posted entries and reversals.
- Server-side pagination and indexed queries for large datasets; queues for exports; no loading millions of rows into browsers.
- Enforce permissions and subscription entitlements on the server.
- Implement secure sessions, CSRF, XSS/SQL injection protections, rate limits, encrypted secrets, file authorization, audit trails and recoverable backups.
- Evaluate cPanel/CloudLinux limits for 1,000 isolated databases and define a migration path to dedicated database infrastructure.
- Never change production without verified backups, staging tests and explicit release approval.

## Delivery sequence
P0 audit and architecture/backlog -> P1 central SaaS registration/provisioning/tenant isolation/auth/subscriptions -> P2 tenant core/master data -> P3 stock/purchasing/sales/POS -> P4 accounting/FX -> P5 HR/documents/reports -> P6 full UX/localization refinement -> P7 security/integration/load/backup restore -> P8 controlled production release. Apply design, security and tests throughout all phases.

## Definition of done
Tenant signup-to-activation works safely; isolated tenant login and permissions work; purchases and sales post balanced accounting and correct stock; POS, payments, returns and multi-currency reconcile; reporting agrees with ledgers; HR and multilingual interfaces/PDFs work; subscriptions are enforced; critical tests pass; staging and backup/restore are verified. Do not claim completed, tested or deployed unless evidenced.

## Work cycle
Inspect -> implement a coherent unit -> add tests -> run tests -> deploy to staging -> verify -> commit development branch -> record exact results in IMPLEMENTATION_STATUS.md -> proceed. Maintain production stability. Blockers must be reported precisely and independent work should continue.

Source: user-approved FidaERP Complete Enterprise SaaS ERP Development Master Prompt, 2026-10-22.
