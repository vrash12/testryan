# NestCrafters migration milestones

Updated: 2026-10-01 (Asia/Shanghai).

## Scope and working rules

Rebuild the contractor portal in Laravel and Tailwind while preserving its features and existing data. Follow `AGENTS.md` and `UI/UX.md`. Develop locally in a separate application directory; production deployment is a later decision.

Statuses describe verified deliverables. Update this file and `session-handoff.md` when a milestone changes, recording validation and unresolved issues. Do not mark work complete based only on generated code.

## M0 — Reference recovery and behavior inventory

Status: In progress.

- [x] Diagnose the production startup failure and restore database connectivity.
- [x] Inspect the main legacy PHP workflows and identify the two roles.
- [x] Save a local code snapshot and a SQL export with definitions for 11 tables.
- [x] Write agent guidance and mobile UI/UX requirements.
- [ ] Finish and verify the recursive source download; the existing ZIP is a partial snapshot.
- [x] Validate the supplied SQL through an isolated local restore, including binary files, text encoding, and record counts. M2 reconciles all 412 rows and four BLOB copies; snapshot consistency/triggers/views/routines/events and current production completeness remain unverified.
- [x] Map every recovered legacy route/action and active missing-endpoint reference to its proposed Laravel equivalent, with permissions, validation, side effects and UI entries. See `docs/legacy-audit.md`; production-source completeness remains unverified.
- [x] Audit exact SQL schema, logical relationships and static row/reference checks in `docs/database-audit.md`; this does not replace restore verification.
- [x] Document the permission matrix, proven versus required notification events, and anti-spam controls in `docs/permissions-notifications.md`.
- [x] Produce a reviewable Laravel structure, migration sequence, implementation order and decision register in `docs/migration-plan.md` and `docs/legacy-audit.md`.
- [x] Complete technical review of that audit/plan, correct verification/queue/scaffold/delivery/base-path gaps, and recommend concrete D1–D10 defaults in `docs/review.md`. This is not product acceptance or implementation authorization.
- [ ] Resolve differences between legacy behavior and intended rules, especially approval access and contractor notes.

Exit criteria: A verified source inventory, a usable local database copy, and a feature checklist with explicit decisions for ambiguous behavior.

Audit checkpoint (2026-10-01): local snapshot has 60 files/35 PHP files; 34 syntax checks pass and contractor media upload fails parsing. SQL contains 412 rows across 11 MyISAM/latin1 tables, with 15 orphan actor-log references. Missing handlers/assets/PHPMailer and absent previously listed download helpers/ZIP prevent a completeness claim. No code/import/application tests/production changes were made during that audit. At that checkpoint isolated restore/recovery and D1–D10 review were pending. The user subsequently authorized M1, then M2, whose reconciliation is recorded below; source recovery and business decisions still prevent M0 completion.

Technical review checkpoint: 60-file manifest and SQL digest rechecked unchanged. Seven review findings are documented and incorporated; default jobs/users migrations must not conflict with Jobs/Users. Source recovery and restore were pending during that review; legacy third-party dependencies need not be runnable for Laravel, but missing business behavior needs evidence or an accepted replacement. Required owner facts include business timezone/currency, retention and hosting/mail/queue/scheduler details. At that checkpoint no application implementation had begun; later M1/M2 delivery is recorded below.

## M1 — Local Laravel foundation

Status: Complete for the local foundation. The user explicitly authorized M1 while M0 remained incomplete, then separately authorized M2 and M3. These milestones do not close M0 or authorize deployment.

- [x] Check local runtimes and supported Laravel/Tailwind versions before selecting dependencies.
- [x] Create a separate Laravel application with Blade and Tailwind in laravel-app.
- [x] Configure an isolated empty local database, private storage, and captured development mail.
- [x] Keep secrets and private source/data snapshots out of version control.
- [x] Remove conflicting default auth/queue migrations before execution; map Users identity fields, reserve app_ infrastructure names; verify `/contractors/` URLs/assets/public-root isolation and dedicated sessions locally.
- [x] Establish role-aware layouts, disabled navigation placeholders, reusable components, and application error handling.
- [x] Add authentication structure and protected role boundaries without implementing M3 credential workflows.
- [x] Add HTML/JSON health checks and verify the local application, build, tests and setup instructions.

Exit criteria: The application runs locally with a reproducible setup and a successful asset build.

M1 delivery: PHP 8.4.10, Composer 2.8.12, Node 24.13.1, npm 11.8.0 and MariaDB 10.4.32 confirmed before code; Laravel 13.34.0/Tailwind 4.3.3/Vite 7.3.6 installed and locked. Isolated nestcrafters_m1 has zero tables. Feature tests, asset/template build, platform/lock checks, captured synthetic mail, HTTP/CSRF/private-path and subdirectory checks pass. Browser emulation covers 320–1280px across five shell screens. See [M1 report](docs/m1-foundation.md) and [setup](laravel-app/README.md). The later M2 authorization and delivery are recorded below; M0 source completeness/business decisions remain open.

## M2 — Schema and data migration

Status: Complete for the supplied local export. The user authorized M2 after M1; M0 source-completeness and business-decision work remains open and does not prevent this isolated data validation.

- [x] Derive migrations and model mappings from the actual SQL definitions.
- [x] Preserve IDs, relationships, exported timestamps, password hashes, status values, and decimal monetary amounts.
- [x] Map legacy table names and custom primary keys explicitly.
- [x] Implement a guarded, repeatable import into an isolated database with transactional target writes and overlapping-import locks.
- [x] Copy BLOB documents/photos/media to private storage with metadata and verified byte integrity; retain original BLOBs for reversibility. Actual media table is empty and synthetic media covers the mapping.
- [x] Reconcile row counts, relationships, null values, file contents, and encoding after import.

Exit criteria: Imported records and files retain their meaning and integrity; the import does not depend on production access.

M2 delivery: nestcrafters_local has all 412 source rows in 11 InnoDB/utf8mb4 domain tables plus three tracking tables/app_migrations. Native nestcrafters_m2_legacy preserves MyISAM/latin1 bytes and its staging account is now SELECT-only. All 77 legacy column definitions, next IDs, hashes/text/dates/nulls/decimals and four private copies (2,899,381 bytes) reconcile. Twelve restrictive domain FKs have zero orphans; 15 historical orphan logs remain without a FK. Events/mail are suppressed; unchanged reruns verify without duplicates and changed data is refused. The packet limit is 32 MiB runtime-only locally and may reset on database restart.

Validation at M2 completion: 31 tests/367 assertions passed, including 14 disposable-MariaDB import tests; actual imported model edits rolled back and the original baseline re-verified. See [M2 report](docs/m2-data-migration.md). The user subsequently requested M3, recorded below. M2 did not accept D1–D10 recommendations or authorize deployment.

## M3 — Authentication and permissions

Status: Complete locally. The user authorized M3 and selected account status/email verification only for pending contractors. Imported accounts retain their hashes and require email proof; no rollout exception was requested.

- [x] Implement login, logout, role redirects, and existing password-hash compatibility.
- [x] Start verification, throttling, bot controls and durable notification intent from M4.5 alongside identity.
- [x] Implement registration, approval/review-state gates, and the agreed pending status/verification experience. Approval editing remains M4.
- [x] Implement policies, ownership checks, validation, CSRF protection, and authentication rate limits.
- [x] Implement the existing admin password reset workflow with current admin password, audit and session revocation.
- [x] Test unauthorized requests and cross-contractor access directly at the server boundary; later business routes must apply these policies.

Exit criteria: Both roles can log in, and all protected actions enforce the agreed access rules.

M3 delivery: Four app_ identity/token/event/delivery tables bring the local target to 19 tables. All 412 source rows, 33 source hashes and four private checksums still match; all imported users have unverified state and no import email. Registration is atomic and pending; signed verification/recovery and current account/session gates are active. Ten policies enforce admin/approved verified ownership/nested records. Identity mail is captured privately, durable and retryable; public bot verification fails closed without configured keys. Business CRUD/file endpoints remain M4–M6.

Validation: the pre-M4 M3 baseline was 63 tests/722 assertions; the historical M4 checkpoint was 64 tests/804 assertions. The first-import regression rerun passed 14 tests/292 assertions. Formatter, Composer/platform, asset/Blade build and real HTTP health/CSRF/origin checks pass. Public and synthetic authenticated screens are checked at the six required widths. See [M3 report](docs/m3-identity-permissions.md). M4 and M5 are implemented locally; production rollout remains separately unauthorized and D2–D10/release facts remain open.

## M4 — Contractor and document workflows

Status: Complete locally. Depends on M3. See [M4 report](docs/m4-contractor-workflows.md).

- [x] Build admin contractor creation, editing, approval, priority, search/filtering, and safe deletion (archive by default; guarded permanent deletion only for unreferenced contractors).
- [x] Provide the minimum service catalog needed by contractor selection and jobs before completing catalog UI at M6.
- [x] Build contractor profile, photo, and service selection workflows with re-review transitions.
- [x] Build W9/insurance upload, preview, download, expiry, archival, and permitted deletion actions.
- [x] Build contractor notes and associated activity logging.
- [x] Verify transaction behavior and authenticated file access.

Exit criteria: The contractor-management feature checklist passes for both roles and existing records. M4 tests use the disposable M2 test schemas; the populated local target is preserved and is not reset by verification.

M4 delivery checkpoint: The fifth application migration adds `app_private_uploads` without changing the legacy schema. Admin and contractor profile/service/document/photo workflows, the minimum service catalog, admin-private notes, authenticated binary responses, archive/restore, guarded permanent deletion, and activity logging are implemented. At the M4 checkpoint the local target had 20 tables, all 412 imported rows, and no application-created upload rows. The historical sequential feature suite passed 64 tests/804 assertions; the focused M4 suite passed five tests/86 assertions. M5 later adds the 21st table. Browser navigation/refresh was blocked by the existing local-tab URL policy, so native upload/PDF/device checks remain deferred.

## M4.5 — Notifications and registration protection

Status: Local feature implementation delivered across M3, M6 and the notification-center continuation. Inbox, email preferences, delivery status/retry, block controls and optional reminder stages are implemented. Final continuation verification passed 120 tests / 1,508 assertions; production configuration and acceptance remain separate. See docs/m45-notification-center.md.

- [x] Implement configurable local email event defaults for registration, approval/not-approved changes, profile/services, assignment/reassignment, job changes, acceptance, completion, job notes/media and the 30-day Insurance reminder. Additional reminder stages and production recipient acceptance remain open.
- [x] Add per-user email preferences, recipient rules, queue-compatible delivery, private failure logging and an admin status/retry UI; real worker configuration remains deployment work.
- [x] Add email verification, registration throttling, local bot envelope/server-provider adapter, duplicate-email checks and private company/service review indicators. Live provider configuration and additional risk/retention rules remain release work.
- [x] Build an admin review queue with safe reject, archive, reversible block, and atomic explicit bulk actions.
- [x] Cover implemented events, preference gates, private failures/retries, inbox ownership and block/bulk controls with local regression tests. Live-provider/release configurations remain unverified.

Exit criteria: Notifications work through a local mail sink, abuse controls are tested, and administrators can review suspicious registrations without data loss.

## M5 — Jobs, notes, and media

Status: Complete locally. Depends on M3 and the service catalog slice delivered in M4. Production and legacy files remain unchanged.

- [x] Build job creation/editing/assignment, service selection, due dates, descriptions, and terms.
- [x] Preserve price, deposit validation, and material responsibility.
- [x] Implement acceptance, acceptance on behalf, reassignment behavior, completion, reopening, and timestamps.
- [x] Implement permitted append-only job notes, tagged media, authenticated previews, thumbnails, and deletion.
- [x] Enforce the acceptance prerequisite and JPEG/PNG/PDF 10 MB upload limit with detected MIME checks.
- [x] Provide responsive print views and append-only activity/state history.

Exit criteria: Job transitions and media permissions pass feature tests, including reassignment and invalid requests.

M5 delivery: Admin and contractor job routes now cover creation/editing, service selection, assignment/unassignment, acceptance on behalf, acceptance, completion/reopen, notes, tagged media, authenticated preview/download/thumbnail, guarded deletion, and print. `JobManagement` wraps multi-record changes in transactions, resets acceptance/completion on reassignment, records activity, and appends `app_job_transitions` history without a Job foreign key so deletion history survives. Job media is private, retains legacy BLOB compatibility, records `app_private_uploads` metadata, and accepts detected JPEG/PNG/PDF files up to 10 MB. Descriptions, terms, and notes render through the Symfony HTML Sanitizer. The local target receives migration `2026_10_01_000006_create_job_transitions_table.php`, bringing it to 21 tables without resetting imported rows.

Validation: the full sequential suite passes **75 tests / 974 assertions**; the focused M5 and ownership checks pass **9 tests / 207 assertions**. Formatter, Composer strict validation/platform checks, Blade cache, PHP lint, and the Vite production build pass. Native file-picker/PDF/device checks remain M7 work. Business notifications, review queues, dashboards/templates, and release deployment remain M4.5/M6/M7/M8 scope.

## M6 — Management and dashboard coverage

Status: Complete for the verified local M6 deliverables. User authorized M6 after local M4/M5; source/product acceptance, remaining M4.5 and M7/M8 remain separate gates. See [M6 report](docs/m6-management-dashboards.md).

- [x] Complete service and rich-text template management.
- [x] Build role-specific dashboards, approval queues, due-date views, and insurance expiry indicators.
- [x] Implement search, filters, pagination, and activity log views.
- [x] Verify legacy mail-call evidence and implement configurable local notification defaults with development mail tests; production recipient/preferences/schedule decisions remain open.
- [x] Sanitize rich HTML while preserving supported formatting in descriptions, terms and template preview/insertion; retain original stored HTML.

Exit criteria: Every reference feature has an implemented equivalent or a documented, accepted change. Local implementation is verified below; the report explicitly retains product/source-acceptance gaps rather than claiming those decisions were accepted.

M6 delivery: Service/template CRUD and sanitized job-template insertion, real role dashboards, strict filters/pagination, truthful historical activity associations/statistics, capped filtered print views, active document expiry views, ALL-service contractor filtering and safe explicit 1–50 bulk review are implemented. Durable business notification defaults are opt-in, transactional, retryable and recheck current permissions. Local/testing refuses external mail; no scheduler/production worker is enabled. User preferences/inbox, block controls and release decisions remain M4.5/M7/M8.

Validation: **110 tests / 1,392 assertions** pass sequentially without skips or failures. Formatter, 137 PHP syntax checks, Blade compilation, Composer/platform validation and Vite build pass. Loopback health/preview/private-path checks pass. Import verification reconciles all 412 rows and four private copies; the SQL digest and all 60 legacy file hashes remain unchanged. No M6 schema change was needed and the target remains at 21 tables. Native responsive/device acceptance remains M7.

## M7 — Mobile usability and final local verification

Status: In progress — core synthetic local workflow review complete; exact viewport/native-device checks and client decisions remain open.

- [ ] Verify the tasks and viewport sizes listed in `UI/UX.md` for both roles. Dashboard six-width checks passed; broader workflow/device coverage remains pending.
- [ ] Verify touch targets, safe areas, keyboard behavior, form errors, empty states, and retry feedback on native or emulated devices.
- [ ] Check camera/file selection, PDFs, downloads, and uploads; label emulator-only checks honestly.
- [x] Add selected-file previews and removal for documents/media, plus per-file job-media progress, partial success and deduplicated retry (2026-10-02). Local browser selection, validation and outage/retry passed; native-device coverage remains open. See `docs/m7-upload-reliability.md`.
- [x] Reuse the required feature tests, production asset build, and import integrity checks from the verified M6 run.
- [x] Prepare and execute a synthetic client review for admin, approved contractor, and pending contractor workflows at the available local viewport.
- [ ] Obtain client sign-off for the remaining review rows and D2–D10 decisions.

Exit criteria: Core tasks work on phone and desktop, required checks pass, and known limitations are documented.

M7 local review checkpoint (2026-10-01): `m7:prepare-review` created isolated admin, approved, pending, and unverified identities plus representative jobs, documents, notes, media, templates, and activity records in `m2_test`. The local CUA review passed the shared shell/auth, admin dashboard/review, approved contractor job acceptance/media-gate, and pending account-status/direct-denial flows. The disposable accepted-job mutation was resettable and did not touch `nestcrafters_local`. Exact six-width rendering, native file picker/PDF/camera behavior, real-device accessibility, external mail, and client decisions remain blocked/pending. See `docs/m7-client-review.md`.

## M8 — Deployment preparation and approved rollout

Status: In progress — local deployment preparation complete; production rollout is not authorized.

- [x] Add a read-only `release:preflight` command that checks secrets, debug mode, URL/base path, private storage, session cookies, queue/mail posture, bot-control configuration, database connectivity, migrations, and compiled assets.
- [x] Prepare a current release runbook covering hosting facts, artifact build, backups, transfer, cutover, rollback, and post-deployment checks.
- [ ] Confirm hosting runtime, document root, storage permissions, mail, and any required scheduler/queue configuration with the deployment owner.
- [ ] Prepare and validate a current production backup and final data-transfer procedure against the target host.
- [ ] Obtain approval for the concrete rollout plan and any maintenance window.
- [ ] Deploy, reconcile migrated data, and verify login, critical workflows, files, and error logging.

Exit criteria: Approved deployment succeeds and post-deployment checks confirm feature and data integrity.

M8 local preparation checkpoint (2026-10-02): `php artisan release:preflight --json`, Pint, PHP lint, Composer validation/platform checks, Vite production build, Blade cache, route cache, `git diff --check`, and loopback `/health` passed. Strict preflight intentionally exits 1 on local settings because HTTPS, the `/contractors` base path, Secure cookies, a persistent queue, external mail, and production bot-control configuration still require hosting facts. No deployment, production database access, mail delivery, or legacy/SQL change occurred. See [deployment runbook](docs/deployment-runbook.md).

## Notification inbox management continuation — 2026-10-02

Implemented status tabs/counts, fixed event/date filters, individual reversible read state, selected bulk actions (current page, up to 50), strict recipient ownership and immutable arrival timestamps. The disposable review fixture now includes five synthetic inbox rows without mail delivery. Validation: 11 tests / 135 assertions, Pint, Blade, route cache, browser fixture review and health checks pass. Exact 320 px review was interrupted by browser reset; native device and full-suite acceptance remain open. See `docs/notification-inbox-management.md`.

M7 branding continuation (2026-10-02): generated logo integrated into guest/auth, workspace and favicon. Document layout, focus, upload and download checks progressed; focused regression passed 31 tests / 407 assertions. M7 remains in progress; see [evidence and remaining device checks](docs/m7-branding-verification.md).


M8 Brevo/shared-host preparation (2026-10-02): Brevo API integration, prefixed durable queue migrations, bounded shared-host worker schedule and automatic reminder schedule are implemented with opt-in activation. Migration 000010 applied locally. Verification: 41 targeted tests / 409 assertions, Composer/platform and Pint passed. User deferred provider/sender/timezone/hosting details; live email receipt and actual host cron remain pending. See [activation guide](docs/brevo-shared-hosting.md).
