# NestCrafters architecture

## Purpose

NestCrafters is a role-based contractor management portal. The Laravel rebuild must preserve the existing workflows and data while replacing the custom PHP page/action structure with a maintainable application architecture and a responsive Tailwind interface.

## Audit baseline — 2026-10-01

The local-only audit is documented in [route/features/risks](docs/legacy-audit.md), [exact SQL model and relationships](docs/database-audit.md), [permissions/events/anti-spam](docs/permissions-notifications.md), and [structure and implementation order](docs/migration-plan.md). The user subsequently authorized M1 through M6. The separate laravel-app has a foundation, all 412 exported rows, local authentication/permissions, contractor/document workflows and jobs/notes/media; [M2 evidence](docs/m2-data-migration.md), [M3 evidence](docs/m3-identity-permissions.md), [M4 evidence](docs/m4-contractor-workflows.md) and [M5 evidence](docs/m5-jobs-notes-media.md) record the verified boundaries. M0 source completeness/business decisions remain in progress; M6 management, dashboards and business-event/review foundations are delivered locally; see [M6 report](docs/m6-management-dashboards.md). Remaining M4.5 preferences/inbox/block work, M7 native verification and deployment remain pending.

All 11 exported tables use MyISAM/latin1 and have no foreign keys. Target transactions require a documented InnoDB conversion; text conversion to utf8mb4 requires byte/encoding verification. Preserve custom IDs/table names, bcrypt hashes and original record meanings. The SQL has 33 users, one job, no job notes/media, and 15 orphan log-user references; do not invent actors or silently delete history. See the SQL audit for exact counts/definitions and import limitations.

## Current legacy architecture

The production application is a custom PHP/MySQL system. Each page commonly combines request handling, database queries, authorization checks, and HTML rendering. Actions are handled by PHP endpoints and query parameters. Authentication uses PHP sessions and password hashes. Files, profile photos, and job media are stored as binary database values.

The legacy frontend uses server-rendered HTML, Bootstrap, jQuery, Bootstrap Icons, TinyMCE, and custom JavaScript. PHPMailer is manually included for SMTP email.

The legacy application is reference behavior. New code should not copy its mixed concerns, exposed configuration style, inline output, or inconsistent authorization patterns.

## Target Laravel architecture

Use a conventional Laravel application with these layers:

- **Routes:** named web routes grouped by authentication and role middleware.
- **Controllers:** coordinate requests and responses; keep business rules out of Blade templates.
- **Form requests:** validate and authorize incoming data before controller logic runs.
- **Policies and middleware:** enforce admin permissions and contractor ownership on every protected resource.
- **Models and relationships:** represent users, jobs, services, files, media, notes, templates, and activity logs.
- **Actions or services:** hold multi-step workflows such as approving a contractor, assigning a job, accepting a job, importing data, and handling uploads.
- **Notifications and mail:** isolate notification delivery from core mutations and support a local mail sink during development.
- **Storage services:** keep files private, validate content, and serve them through authorized controller responses.
- **Blade components:** provide reusable layouts, navigation, cards, badges, tables, forms, dialogs, upload controls, and empty states.
- **Jobs and queues:** use for non-critical email, thumbnails, and other work only after the behavior is tested and queue configuration is available.

## Roles and access boundaries

The application has `admin` and `contractor` roles. Admins can manage all contractor and job records. Contractors can access their own profile, services, documents, assigned jobs, permitted notes, and job media under the agreed approval/verification gate. Ownership checks must happen server-side even when a URL contains a record ID. Account `Notes` are recommended admin-private; contractor job-note creation is a missing legacy handler and requires a reviewed rule. Do not infer those permissions from the unsafe legacy action dispatcher.

Use policies for users, jobs, files, job media, notes, services, templates, and activity logs. A hidden button is not an authorization control.

## Domain model

The initial migration should map the existing tables explicitly:

- `Users`: accounts, contact details, company information, password hash, role, approval state, priority, and profile photo.
- `Jobs`: job details, assignment, due date, acceptance/completion flags and timestamps, pricing, deposit, materials, terms, and description.
- `Services`: available contractor/job services.
- `ContractorServices`: contractor-to-service pivot records.
- `Job_Services`: job-to-service pivot records.
- `Files`: contractor documents, metadata, expiry, archive state, and binary content or migrated storage key.
- `Job_Media`: job media, MIME type, tag, uploader, timestamp, and binary content or migrated storage key.
- `Job_Notes`: notes attached to jobs.
- `Notes`: contractor notes created by admins.
- `Templates`: reusable rich-text job content.
- `ActivityLogs`: auditable user actions and timestamps.

Use the SQL export as the source for exact types, keys, nullability, and constraints. Do not infer schema details from screen code alone.

## Core workflows

### Authentication and approval

Users log in with email and compatible legacy password hashes through Laravel's web guard. New contractors start pending at approval 0; ApprovalStatus also represents approved 1 and review 2. The user selected authenticated account status/email verification only for pending contractors. Protected contractor access requires current active lifecycle, verified email and approval 1; email proof never implies approval. Imported accounts are not automatically verified.

### Contractor management

Admins create, review, approve, prioritize, edit, and delete contractors. Contractors edit their own profile, photo, and service selection. Profile/service changes set the account back to admin review and must be transactional.

### Job management

Admins create, edit, assign, accept on behalf, complete, and delete jobs. Contractors see assigned jobs, accept them, view details, and upload media after acceptance. Acceptance and completion are separate states with separate timestamps.

### Files and media

Validate MIME type, size, ownership, and context before saving. Use private storage in the target system where practical. Serve files through authorization-aware responses with safe download names and content types. Job media supports Before, During, After, and Other tags.

### Auditing and notifications

Important mutations create activity log entries. The target notification module should use Laravel Notifications/Mail, queue non-critical delivery when available, and retain an in-app notification record or delivery status where the product needs history. Initial email events are:

- New contractor registration to administrators.
- Contractor profile or service changes to administrators.
- Contractor approval or rejection to the contractor.
- Job assignment or reassignment to the contractor.
- Job detail changes to the assigned contractor.
- Contractor job acceptance to administrators.
- Job completion to administrators.
- New permitted notes or job media to relevant users.
- Upcoming insurance or document expiry reminders.

The admin must be able to enable/disable event types and manage recipient rules without exposing SMTP credentials. Email failures should not silently roll back an already successful core mutation unless the product explicitly requires that behavior. The recovered code references PHPMailer but its library is absent locally. The only proven business mail call is the admin contractor-update handler, before the final user write; job assignment is a commented placeholder. The target records intent/audit atomically and dispatches after commit, with durable retry/failure tracking and import-time delivery suppression. See the event matrix for recipients and unresolved defaults.

### Registration abuse controls

Registration is a public attack surface. Use request throttling, email verification, CAPTCHA or an equivalent bot-control step, duplicate email/company checks, disposable or suspicious-address indicators where appropriate, and an admin approval gate before contractor access. Provide an admin review queue with reasons or risk signals and safe bulk archive/reject actions. Do not automatically delete records based only on a heuristic.

## Data migration strategy

1. Restore `nestcrafters_database.sql` into an isolated local database.
2. Verify all table definitions, row counts, relationships, encoding, and binary values.
3. Create Laravel migrations or an import adapter that maps the legacy schema deliberately.
4. Preserve legacy IDs and password hashes during the first import.
5. If moving binary data to private storage, record a reversible mapping and verify file hashes and MIME metadata.
6. Reconcile records after import and retain a read-only backup before any destructive transformation.

## UI architecture

Use a shared responsive shell with role-aware navigation, flash feedback, breadcrumbs/back links, and reusable Tailwind components. Build mobile-first, with phone bottom navigation for primary contractor/admin destinations and stacked cards replacing wide tables. See `UI/UX.md` for the full interaction and mobile requirements.

Implement one action per shared workflow across role-specific controllers, explicit legacy model keys/timestamps, policy-checked nested resources and private preview/download routes. The recommended directory tree, ancillary schema needs and sequencing are in [migration-plan.md](docs/migration-plan.md). M1 selected Laravel 13.34.0, Blade, Tailwind 4.3.3 and Vite 7.3.6 after runtime checks; exact versions are locked. See [M1 foundation](docs/m1-foundation.md).

The completed [technical review](docs/review.md) requires case-safe ancillary table names (database queue storage must not collide with Jobs), adapted auth scaffolding rather than a second users table, and exact legacy FK/key mapping. Save durable notification intent in the mutation transaction and recover delivery after commit; verification/recovery addresses are exempt from ordinary verified-workflow-mail eligibility. Test the `/contractors/` external base path and serve only the public front controller. Concrete business defaults remain proposals, not user approval.

## Operational boundaries

Configuration belongs in environment variables. Production errors go to private logs. Storage, database, mail, sessions, queues, and cache must be configurable per environment. Deployment must include backups, a migration plan, health checks, and rollback steps.

## Implemented M1 boundary

The shared Blade application layout receives a role and navigation definition. Admin and contractor shell routes require Laravel web authentication plus role middleware; the contractor shell also requires approval=1. All other feature destinations/actions are disabled placeholders. Local previews use a separate middleware requiring both local environment and an explicit flag, create no authenticated session, and query no domain records. M3 will implement credential submission, registration, verification, throttling and the agreed onboarding state; no M1 preview is an authentication bypass.

The initial authenticatable model maps Users/user_id/password_hash, with integer role/approval casts and no updated_at or remember_token assumption. Default users/jobs/cache migrations, synthetic account factories and seeding were removed before execution. M1's nestcrafters_m1 remains empty; M2 now uses populated nestcrafters_local. app_migrations and app_queue_* names reserve framework storage safely; sessions/cache still use files and queues remain synchronous.

The private disk is outside public, has no automatic serving or storage symlink, and fails visibly on filesystem errors. Local Mail uses a private log sink and a synthetic check command, never external SMTP. Friendly error templates and private daily logs are in place; public/index.php disables raw error display. /health provides local dependency status but generic public liveness outside local. The subdirectory helper verifies links, assets and cookie scope at /contractors/ without changing production configuration. Application secrets exist only in the ignored .env. UTC is provisional pending a business timezone decision.

## Implemented M2 boundary

Reviewed schema constants drive three migrations: 11 domain tables, app_import_runs/app_import_rows/app_private_files, and 12 restrictive domain references plus Files.unique_file_id uniqueness. All original 77 columns, signed INT keys, composite/custom pivots, defaults, nullability, next IDs, password/BLOB bytes, statuses and exported date strings are preserved. Target domain tables use InnoDB/utf8mb4_unicode_ci; native MyISAM/latin1 staging retains the original bytes. ActivityLogs retains its 15 orphan user references without a foreign key or invented actors. The full relationship map is in the M2 report.

Models map every exported table and relationship explicitly; money casts remain decimal strings, domain timestamp automation follows actual columns, and hashes/BLOBs/private paths are hidden from normal serialization. Approval/media enums and derived job status do not mutate history. No public CRUD, authentication submission, verification values, workflow transitions or ownership policies were added in M2; those must accompany the later authorized routes.

## Implemented M3 boundary

Laravel identity now includes registration, login/logout, role redirects, signed email verification, hashed single-use recovery tokens and controlled admin password setting. FormRequests whitelist fields; registration atomically writes user/services, audit and durable notification intent. LegacyUserProvider verifies PHP-compatible hashes without a blanket reset and rehashes only safely representable successful credentials. Current-account middleware refreshes role/approval/lifecycle and checks a session version; password resets increment it. CanonicalUrls pins security-link origins to configuration.

Four new app_ tables isolate identity state, password tokens, encrypted notification intents and recipient delivery attempts from the unchanged legacy schema. Existing users and first imports receive unverified state rows without events. The populated local target has 19 tables and all 412 source rows unchanged. Activity logs contain actor/target identifiers and outcomes, never passwords or security URLs.

Ten discovered policies enforce admin/owner/approval/verification and nested-resource boundaries. Account Notes stay admin-private; M5 Job_Notes writing is append-only for the current authorized assignee under the local decision boundary. Read policies follow current assignment and private source mappings; transition history, private uploads and retention validation are implemented for M4/M5 while broader business events remain later work.

Identity notifications persist intent with the mutation and dispatch after commit through a queue-compatible job, currently sync. Private failures/backoff survive delivery/dispatch failure and can be retried with nestcrafters:retry-identity-mail. Local/testing refuses external identity mail. Initial verification/recovery can reach eligible unverified addresses; ordinary registration notices require active verified admins. No implicit backfill, workflow preferences, scheduler, worker or database queue table exists yet.

Layered login/registration/recovery limits, honeypot, expiring one-use bot envelope, duplicate-email enforcement and private company/service signals are active. The arithmetic fallback is local/testing only; public registration fails closed without a configured server-verified provider. Numeric defaults, live provider/hosting setup and signal retention remain operational decisions. See the M3 report for routes, policy matrix, verification and remaining limits.

App/Actions/Import separates strict dump parsing, local scope guards, row/schema mapping, transactional target import and compensating private-copy cleanup. Only reviewed literal INSERTs run natively in the dedicated source schema; arbitrary SQL and dump DROP statements are rejected or not executed. Loopback/server/schema checks and advisory locks protect both import databases. Existing target/source edits and altered files abort without replacement. Repeated unchanged imports verify the ledger/rows/counters/files and create no duplicates. No model events or workflow delivery occur during import.

Four verified copies totaling 2,899,381 bytes are mapped privately while all source BLOB columns remain intact. Their storage keys are not public URLs. M4/M5 provide policy-enforced streaming/download/preview responses for contractor documents, profile photos and job media. The staging account is now SELECT-only; target and disposable test credentials have separate database scopes. Export UTF-8 literals are validated byte-for-byte; original rich HTML remains available alongside sanitized rendering. Import +00:00 sessions preserve exported calendar strings without proving the unknown original business timezone.

## Implemented M4 boundary

Contractor management is implemented in `App\\Actions\\Contractor\\ContractorManagement`, with admin and contractor controllers using FormRequests, policies, transactions, and the existing legacy key mappings. Admin contractor deletion defaults to reversible archive; a separate guarded permanent-delete action is available only when no job/history references exist, and it preserves ActivityLogs. Profile/service edits return contractor approval to review and increment the account session version; the current contractor session is redirected to the status page after a self-edit.

`app_private_uploads` stores metadata for new profile photos and documents while `Files.file_data` and imported `app_private_files` remain available for compatibility and recovery. `ContractorDocumentController` and `ProfilePhotoController` authorize every response and return private binary content with safe headers. `PrivateUploadPolicy` covers the new metadata records. New uploads are deleted on failed transactions; imported documents can be archived but not permanently removed.

The minimum service catalog, contractor search/filter, approval/priority/review controls, admin-private account notes, expiry fields, authenticated preview/download, safe profile-photo removal, guarded deletion, and associated activity logging are covered by `tests/Feature/M4ContractorWorkflowTest.php`. Business notification events and contractor note visibility remain M4.5/review decisions.

## Implemented M5 boundary

Jobs are implemented through `App\Actions\Job\JobManagement`, which is the transaction boundary for details/services, assignment, acceptance, completion/reopen, notes, media, and deletion. Controllers authorize the actor and delegate state changes; form requests validate fields, approved contractors, services, money, and upload limits. Policies recheck role, ownership, nested parent scope, assignment, and acceptance at the request boundary, while the action locks the current job row again to reject stale assignment writes.

Migration `2026_10_01_000006_create_job_transitions_table.php` adds `app_job_transitions`. It stores actor, action, from/to status and contractor IDs, JSON state snapshots, metadata, and timestamps. It deliberately has no foreign key to `Jobs`, allowing guarded deletion to remove job children while retaining auditable transition history. `JobStatus` is derived from legacy acceptance/completion flags: unassigned, pending, accepted, and completed.

New job media remains private. Legacy `Job_Media` rows retain BLOB compatibility; new rows also map to `app_private_uploads` with generated paths, byte size, SHA-256, detected MIME, and safe original names. `JobMediaController` is the only preview/download/thumbnail boundary and applies nested policy checks plus private/no-store/nosniff headers. Rich job descriptions, terms, and note text are rendered through `App\Support\JobContent` using Symfony HTML Sanitizer.

The job views share the role-aware Blade layouts and Tailwind components. Admin screens expose assignment, acceptance on behalf, completion/reopen, and guarded deletion; contractor screens expose only current assigned jobs, acceptance, notes, and media after acceptance. Native file-picker/PDF/device checks and business-event delivery remain later verification/workflow milestones. See [M5 jobs, notes, and media](docs/m5-jobs-notes-media.md).

## M6 local management and business event delivery

M6 adds admin TemplateController, ActivityLogController, ReviewController and DocumentController, plus real role dashboards. Controllers validate bounded search/filter scalars and invoke policies; review locks all selected users and account state before transactional approval/archive mutations. Legacy table names remain mapped and no new table is introduced. Original rich HTML remains stored; JobContent sanitizes all rendered job/template preview and insertion content.

BusinessNotifications persists encrypted durable intents/deliveries inside the business transaction, dispatches DeliverIdentityEvent after commit and uses BusinessDeliveryEligibility to recheck current access at delivery. Identity and business messages share private retry bookkeeping without changing verification/recovery exceptions. External mail is refused in local/testing. Environment event switches and a disabled-by-default business master switch precede future per-user preferences; these switches do not constitute a notification inbox. Expiry reminders are explicit current-date-only commands, never an enabled scheduler. See [M6 implementation and boundaries](docs/m6-management-dashboards.md).

## Notification center continuation — 2026-10-01

Two additive app_ tables store per-event recipient email preferences and an inbox with event/recipient uniqueness and read timestamps. BusinessNotifications and IdentityMessages record non-security inbox entries inside the existing event transaction. Mail creation and delivery both recheck preferences; inbox creation has a separate environment switch. NotificationCenterController scopes every item to the signed-in recipient and retains the approved/verified gate. NotificationInbox rechecks current resource access before supplying links. Security tokens remain exclusively in the encrypted outbox.

Admin retry dispatches DeliverNotificationDelivery for one failed recipient after commit; private eligibility checks and row locks remain in DeliverIdentityMessage. BlockContractor transactionally updates blocked_at/session_version, audits the required reason and requires approval review on unblock. Existing current-account middleware immediately enforces the state. See docs/m45-notification-center.md for schema, routes and runtime boundaries.

## Upload usability and safe retry — 2026-10-02

Shared `upload-picker` and `resources/js/uploads.js` provide file selection previews, removal and HEIC guidance. Both job controllers accept JSON confirmations as well as the existing form redirect. Additive `app_media_upload_receipts` serializes scoped retry tokens inside `JobManagement`'s transaction and returns prior IDs without repeating files, audit entries or notification events. One browser form groups successful-file notifications; normal batch POST behavior is preserved. Files remain private, with the same policy/state/MIME/size checks. See [upload reliability](docs/m7-upload-reliability.md) for lifetime, retention, failure and runtime boundaries.

## Brevo and shared-host background processing — 2026-10-02

The user selected Brevo and shared hosting. Mail now has a Brevo API transport via symfony/brevo-mailer, using an environment-only BREVO_API_KEY and a bounded HTTP client. Local/testing environments reject this external transport. Migration 000010 adds prefixed persistent queue, failed-job and batch tables without colliding with Jobs. Database event jobs fan out to individual delivery jobs; existing locked delivery/re-eligibility checks and durable retries remain authoritative.

An opt-in Laravel schedule enqueues due deliveries each minute, records staged insurance reminders daily in APP_TIMEZONE, then drains the database queue with a bounded worker for shared hosting. Laravel cache locks prevent task overlap. This requires a host cron entry; a persistent daemon is not assumed on shared hosting. See [configuration and operational limits](docs/brevo-shared-hosting.md). DNS/provider credentials, live receipts and host execution remain pending.
