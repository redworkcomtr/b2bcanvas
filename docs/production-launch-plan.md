# Production Launch Plan - 2026-06-04

## Current Verified State

- Laravel 13.8.0, PHP 8.3.30, Vue 3, Vite 8.
- Local `.env` is configured for PostgreSQL, Redis queue/cache, SMTP mail, and S3-compatible storage values.
- Local PostgreSQL role/database `b2bcanvas` was created and all migrations ran successfully.
- Automated checks passed:
  - `php artisan migrate:status`
  - `php artisan test` (`48` tests)
  - `npm run test:unit` (`1` test file)
  - `npm run build`
  - `./vendor/bin/pint --test`

## Critical Launch Blockers

### P0 - Storage Upload

Status:
- Fixed in code: `MediaUploadController` now stores uploads on the configured filesystem disk.
- Fixed in dependencies: `league/flysystem-aws-s3-v3` is installed for S3-compatible storage.
- Covered by tests: upload metadata and file persistence are verified for the configured `s3` disk.

Frontend impact:
- New order artwork upload, product catalog image/template upload, issue attachment, and claim evidence screens call the same upload endpoint and now inherit the configured backend disk.

Remaining launch verification:
- Configure real S3/MinIO credentials outside Git.
- Run one real upload against the production bucket or staging bucket.
- Confirm generated media URLs match the intended bucket/CDN visibility rules.

### P0 - Payment Flow Needs Real Stripe Configuration and E2E

Backend state:
- Payment intent, confirm, and webhook endpoints exist.
- Service calls real Stripe API and fails correctly when `STRIPE_SECRET_KEY` is absent.
- Webhook signature validation exists when `STRIPE_WEBHOOK_SECRET` is set.

Frontend state:
- New order payment step uses Stripe Elements and real payment intent endpoints.

Launch requirement:
- Configure `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `VITE_STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET`.
- Run a full test-mode card payment and webhook replay before production.
- Add E2E coverage for successful payment, failed payment, and requires-action payment.

### P0 - Production Infrastructure Inputs Are Missing

Needed before deploy:
- Production domain and `APP_URL`.
- Managed PostgreSQL host/user/password/database.
- Redis host/credentials.
- SMTP provider credentials and verified sender domain.
- S3-compatible bucket, access keys, region, endpoint/CDN URL.
- Queue worker process definition.
- Redis PHP support in the production runtime. The Dockerfile installs `php-redis`, but host-local PHP did not have Redis support, so local testing currently uses database cache/queue.
- Backup and restore process.
- Deployment target and restart command.

No production secrets should be committed to the repo.

## Module Gap Report

### Auth / Tenant / Users

Backend:
- Session auth, active user checks, tenant middleware, invite and role update endpoints exist.
- Policies exist for orders, issues, product mappings, and users.

Frontend:
- Settings UI can invite users, activate/deactivate users, and update roles.

Gaps:
- No MFA/2FA.
- No password policy UI.
- No production onboarding flow for first tenant/user; seed data is only suitable for local testing.

Launch decision:
- Can launch as private/admin-created tenant system.
- Public self-serve signup should wait.

### Orders

Backend:
- List, detail, create, export, saved views, address update, notes update, status transition, status events, audit logs exist.

Frontend:
- Order list, detail, new order wizard, local draft, artwork upload, pricing, and status transition UI exist.

Gaps:
- No item edit after order creation.
- No bulk operations.
- No carrier/tracking integration.
- No invoice/statement module.
- Payment is present but blocked by Stripe config/E2E.

Launch decision:
- Can launch with manual order create/import and manual lifecycle transitions after payment/storage P0 fixes.

### Import Orders

Backend:
- CSV template endpoint, preview, import history, commit, error CSV, mapping bridge, duplicate required-action creation exist.

Frontend:
- Template is loaded from backend, CSV preview and commit are wired.

Gaps:
- UI still says CSV/XLSX, but backend preview accepts CSV/text only.
- XLSX upload/conversion is not implemented.
- Large imports are synchronous; no chunked queue worker pipeline.

Launch decision:
- Launch as CSV-only.
- Remove or change XLSX copy before production unless XLSX is implemented.
- Queue/chunk import should be P1 if expected files are large.

### Product Catalog

Backend:
- Product type, variant, and option CRUD endpoints exist.

Frontend:
- Catalog management UI exists and upload controls are wired.

Gaps:
- Upload storage P0 affects image/template files.
- No granular option compatibility matrix.
- No production import/export for catalog bulk maintenance.

Launch decision:
- Launch with manual catalog CRUD after storage fix.

### Product Mappings

Backend:
- CRUD, simulate, conflict detection, rule validation, duplicate detection, and required-action auto-resolve exist.

Frontend:
- Create, edit, delete, conflict detection, and simulator UI are wired.

Gaps:
- Simulator is a useful preview, not a replacement for real import/order revalidation.
- No bulk mapping import/export.
- No pagination for very large mapping lists.

Launch decision:
- Launch-ready for small/medium mapping volume.
- Bulk mapping import is P1 for real customer catalogs.

### Required Actions

Backend:
- Resolve, reopen, escalate, comment, import row revalidation, and mapping-based auto-resolve exist.

Frontend:
- Required action list/detail/resolution UI exists inside Issues view.

Gaps:
- `product_mapping_required` and `address_error` have concrete resolution logic.
- `invalid_artwork`, `duplicate_order`, and `product_unavailable` are mostly workflow/status handling, not full domain remediation.
- No bulk resolve.

Launch decision:
- Launch if initial operations focus on mapping and address exceptions.
- Other action types need P1 hardening before broad customer rollout.

### Issues / Claims

Backend:
- Ticket/claim create, update, comments, read state, claim resolution, audit hooks exist.

Frontend:
- Issues/claims UI, comments, attachments, and claim evidence UI exist.

Gaps:
- Attachments depend on storage P0.
- No realtime comments.
- No SLA/escalation dashboard.

Launch decision:
- Launch as async support workflow after storage fix.

### Notifications

Backend:
- Notification subscriptions, mail logs, preview, retry, unsubscribe, queued mail job exist.

Frontend:
- Subscription management and notification log UI exist.

Gaps:
- Event catalog is implicit, not centrally managed.
- Production mail provider and worker supervision are not configured.
- No in-app notification feed beyond sidebar/header counters.

Launch decision:
- Launch e-mail notification only after SMTP and queue worker verification.

### API / Integrations

Existing:
- Internal JSON API exists for SPA.

Gaps:
- No customer API token management UI.
- No outbound webhook endpoints.
- No Shopify/Etsy/Woo connector.
- No OpenAPI contract.

Launch decision:
- Launch as portal-first product.
- API/self-service integrations are P1/P2.

## Fastest Safe Launch Path

1. Fix media storage disk usage and test uploads against S3/MinIO.
2. Decide CSV-only launch, remove XLSX wording, and run a real CSV import -> mapping required -> mapping create -> import row ready -> commit order flow.
3. Configure Stripe test credentials and run full card payment + webhook replay.
4. Configure SMTP and run notification queue worker with one real test recipient.
5. Add minimal smoke E2E for login, order creation with artwork, CSV import commit, mapping auto-resolve, payment happy path, and issue comment attachment.
6. Prepare production `.env` outside Git with `APP_ENV=production`, `APP_DEBUG=false`, `DB_CONNECTION=pgsql`, Redis, SMTP, S3, Stripe, trusted hosts/proxies, and secure session cookie settings.
7. Run production deploy sequence on staging first:
   - `composer install --no-dev --optimize-autoloader`
   - `npm ci`
   - `npm run build`
   - `php artisan config:cache`
   - `php artisan route:cache`
   - `php artisan view:cache`
   - `php artisan migrate --force`
   - start app process and queue worker
8. Smoke test staging with real browser and real PostgreSQL.
9. Take DB backup and deploy production.
10. Monitor logs, failed jobs, notification logs, payment webhooks, and upload failures during first customer run.

## Minimal Pre-Launch Fix List

- P0: Verify real S3/MinIO upload URL generation and file visibility rules in staging.
- P0: Configure and test Stripe credentials/webhook.
- P0: Configure and test SMTP + queue worker.
- P0: Remove XLSX promise from import UI or implement XLSX conversion.
- P0: Add smoke E2E tests for the real launch journeys.
- P1: Bulk mapping import/export.
- P1: Required action remediation for invalid artwork, duplicate order, product unavailable.
- P1: Production PostgreSQL/Redis CI job.
- P1: API token and outbound webhook management.
