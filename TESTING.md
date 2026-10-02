# NestCrafters testing guide

## Testing goals

Tests must prove that the Laravel rebuild preserves data meaning, permissions, business rules, and usable phone and desktop workflows. Test server authorization directly; visual hiding is not sufficient.

## M1 checks now available

From laravel-app, run php artisan test --compact, php vendor/laravel/pint/builds/pint --test, composer validate --strict --no-check-publish, composer check-platform-reqs and npm run build. The 13 M1 feature tests use in-memory, unsaved identity models plus isolated configuration and do not touch imported data. M2 adds migrations, parser fixtures and a separately guarded MariaDB suite described below.

The M1 suite covers guest redirects, role boundaries/redirects, unknown roles, unapproved contractor denial, local preview gating and non-authentication, logout, health failure/non-disclosure, private storage routes, safe error rendering, subdirectory links/cookies and local mail restrictions. Actual loopback checks also verified health, captured synthetic mail, missing-CSRF419, private-path404, compiled assets and /contractors session scope. Browser emulation covers five shell screens at the six required widths. See [M1 evidence and limitations](docs/m1-foundation.md).

The M1 checks establish the foundation only. M2 verifies imported integrity/model mapping; M3 adds real login, verification, recovery, anti-spam and ownership policies. Upload workflows, job transitions, remaining business notifications and native-device workflows remain later milestones.

## M2 checks completed — 2026-10-01

At M2 completion: **31 passed, 367 assertions**. Four strict SQL-parser tests cover Unicode, quotes/control bytes, binary payloads, NULL versus empty/zero, unsupported statements, changed schema and truncated input. Fourteen import feature tests run on MariaDB 10.4.32 with synthetic accounts, notes, media, HTML and a controlled known-password bcrypt hash; they never use exported private records. Current M3 validation is below.

Those feature tests reset only nestcrafters_m2_test/nestcrafters_m2_source_test after checking the actual local host, loopback driver and explicit `_test` schema allowlist. Dedicated test credentials are in ignored .env (M2_TEST_USERNAME/PASSWORD), with grants only on those two schemas. Fake storage/mail/notifications/queues prevent file or delivery effects outside testing. Without credentials, these 14 tests explicitly skip; do not interpret a skipped run as M2 verification. Run this suite sequentially because tests share the disposable schemas; do not add --parallel without separate per-process provisioning.

Covered behavior: every fixture column, preserved keys/next IDs, all relationships, dates/decimals/NULL, hidden BLOB/hash/path fields, native escape interpretation, no import events/delivery, unchanged reruns, target/source edit refusal, tampered private copies, partial staging resumption, overlapping-import locks, signed restrictive FKs with orphan-log preservation, transaction rollback and migration rollback/rebuild.

Separate full-export checks verified 412 rows in native staging and target, all 77 original column definitions, exact per-row hashes including binary/password bytes, InnoDB/utf8mb4 target metadata, 12 references with zero domain orphans and 15 unchanged orphan logs. Four private copies total 2,899,381 bytes and match the BLOB digests. All 33 bcrypt hashes are recognized and unchanged; actual plaintext legacy credentials are unavailable. Imported user/job edits and service detachment were rolled back, then the full baseline check passed again. SELECT-only staging allows verified reruns.

Formatter, strict Composer validation and platform checks pass. Loopback health/liveness pass; guest role routes redirect and private/import/.env/SQL URL probes return 404. M2 has no frontend changes; the existing M1 asset build remains valid. All 60 legacy source hashes and the SQL digest remain unchanged. See [M2 report](docs/m2-data-migration.md) and [setup/test account instructions](laravel-app/README.md).

## M3 checks completed — 2026-10-01

The full sequential suite passed **63 tests / 722 assertions**, with no skips. After final changes, **14 import tests / 292 assertions** and **11 foundation tests / 65 assertions** passed again. The import checks verify unverified identity-state creation and zero notification intent/delivery; original source columns remain unchanged. Run MariaDB suites sequentially, with M2's dedicated test credentials and local host/schema guard.

M3 covers legacy password login for both roles, safe cost rehash/long-credential compatibility, session/CSRF rotation, approval 0/1/2, pending status-only access, imported verification gates, current role/email/lifecycle checks, version-based session revocation, generic recovery, hashed expiring single-use tokens, signed/wrong-user/tampered verification, controlled admin password setting and current-actor validation. Registration checks duplicates, field injection, services, bot envelope/provider failures, throttles and rollback of user/pivots/audit/intents. Policy tests include HTTP probes for foreign contractors, nested-parent mismatch, reassignment, re-review, private source mapping and acceptance-required media creation.

Delivery checks retain committed intent after dispatch/transport failure, retry without duplicate processing, suppress stale/expired/revoked recipients or recovery tokens, encrypt payloads and refuse external local transports. Provider tests use HTTP fakes with stray requests prevented. A separate synthetic verification message passed through real Laravel Notifications into a private local log; capture was checked without printing the token or message. No real SMTP/provider call or imported credential was used.

Formatter, Composer validation/platform, Vite build and Blade compilation pass. Actual loopback HTTP checks return health 200 and missing-CSRF 419, and keep configured auth-link origins under hostile Host headers. Responsive browser checks use public forms plus synthetic authenticated accounts on a separate temporary test server/schema, covering login, registration, forgot/reset, status and admin account/password screens at 320/375/390/430/768/1280px. No horizontal page overflow was found. Service filtering/selection, password visibility and phone navigation were checked. Native keyboards/devices, uploads, job transitions and production delivery remain unverified.

The M3 migration and actual-target reconciliation retain all 412 source rows/four private checksums, with 33 unverified identity states and no notification events. See [M3 report](docs/m3-identity-permissions.md). Test-only policy probes and synthetic QA accounts are not shipped public routes or main-database demo users.

## Test layers

### Unit tests

Use unit tests for isolated rules and transformations:

- Approval-state labels and transitions.
- Job status calculation from assignment, acceptance, and completion values.
- Deposit validation against approved price.
- Due-date and insurance-expiry calculations.
- File type, file-size, tag, expiry, and safe filename validation.
- Activity-log message formatting.
- Legacy password-hash compatibility where a custom hasher is required.

### Feature tests

Use Laravel feature tests for routes and complete server-side workflows:

- Admin and contractor login, logout, redirects, and session regeneration.
- Registration with required fields, duplicate email, service selections, and pending approval.
- Admin approval, rejection, priority changes, editing, and deletion rules.
- Contractor profile/photo/service updates and the resulting pending-review state.
- Admin job creation, service selection, assignment, editing, acceptance on behalf, completion, and deletion.
- Contractor assigned-job visibility, acceptance, and unauthorized job access.
- Notes and activity logs with the correct actor and target record.
- Document upload, preview, download, expiry, archive, deletion, and ownership.
- Job media upload only after acceptance, tag validation, MIME validation, 10 MB limit, preview, and authorized deletion.
- Template create, edit, duplicate prevention, and deletion restrictions.
- Search, filters, pagination, and empty results.
- Validation failures preserve safe input and return useful messages.

### Authorization tests

For every resource, test that:

- A guest is redirected or rejected as appropriate.
- A contractor cannot read or mutate another contractor's profile, files, jobs, notes, or media.
- A contractor cannot access admin routes.
- An admin can perform authorized management actions.
- Direct requests with changed IDs cannot bypass policy checks.
- File routes do not reveal content without authorization.

### Database and migration tests

- Restore the SQL export into an isolated test database.
- Confirm all expected tables, columns, keys, and relationships exist.
- Compare imported row counts with the export.
- Verify password hashes, timestamps, null values, text encoding, decimal amounts, and binary file values.
- Run Laravel migrations and seeders from a clean database.
- Run the data import more than once where repeatability is required.
- Verify transaction rollback when a related service, upload, or log operation fails.

### Upload and storage tests

Use fake storage and generated test files. Test valid JPEG, PNG, and PDF files, invalid MIME declarations, oversized files, partial failures, duplicate submissions, safe display names, private storage, authenticated previews, and failed storage writes. Test profile-photo limits separately from the 10 MB job-media limit.

### Notification tests

Use a mail fake or local mail sink. Verify the event, recipient, subject, safe content, related-record link, preference rules, and queued status for every notification that is deliberately retained. Test registration, profile/service changes, approval/rejection, assignment/reassignment, job changes, acceptance, completion, notes/media, and document-expiry reminders. Test that a mail transport failure is recorded and handled according to the agreed workflow without incorrectly reporting the core mutation as failed.

### Abuse-prevention tests

- Verify registration throttling by IP and account identifier.
- Verify email verification is required before the configured access level.
- Verify CAPTCHA or equivalent bot protection rejects invalid challenges and does not block valid users unnecessarily.
- Verify duplicate email and duplicate/suspicious company registrations are flagged safely.
- Verify pending or rejected contractors cannot access protected workflows.
- Verify admin review, reject, archive, block, and bulk actions enforce authorization and preserve audit records.
- Verify rate limits on login, password reset, file upload, and notification-triggering actions.
- Verify suspicious heuristics never delete records automatically without the configured review action.

## Mobile and UI verification

Review at 320, 375, 390, 430, 768, and 1280 px, including phone portrait and landscape layouts. Verify login, registration, profile editing, services, documents, job acceptance, media upload, admin approval, job editing, and activity filtering.

Check:

- No page-level horizontal scrolling.
- Touch targets are at least 44 by 44 CSS pixels.
- Bottom navigation, action bars, dialogs, and the keyboard do not cover controls.
- Long names, addresses, filenames, descriptions, validation errors, and empty states remain readable.
- Tables become usable cards or deliberately scrollable components.
- Camera/file selection, PDF viewing, downloads, dates, and uploads work on available iOS and Android devices; label browser-emulation checks separately.
- Keyboard navigation, focus states, labels, error associations, contrast, and screen-reader announcements work.

## Test data

Use factories or sanitized fixtures for at least:

- One admin.
- Pending, approved, and pending-review contractors.
- Contractors with no services, multiple services, documents, expired insurance, and upcoming expiry.
- Unassigned, pending, accepted, and completed jobs.
- Jobs with notes, all media tags, missing optional financial values, and long rich text.
- Unauthorized users and records belonging to another contractor.

Never use production passwords, SMTP credentials, or unsanitized private records in automated tests. Keep local mail, storage, and database services isolated.

## Suggested commands

Use the commands supported by the selected Laravel version. The expected baseline is:

```bash
php artisan test
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
npm run build
```

Add static analysis, formatting, browser tests, or accessibility tooling only after the project dependencies and supported versions are selected. Record the exact commands in `README.md` once the Laravel project exists.

## Release gate

A milestone is ready for review only when its relevant tests pass, the SQL import checks pass, authorization has been tested with direct requests, mobile checks have been recorded, and known limitations are documented. Deployment requires a separate backup, rollback, and post-deployment verification plan.

## Audit evidence and required regressions — 2026-10-01

Local syntax checks passed for 34/35 recovered PHP files; contractor_job_add_media.php has an unclosed brace. Static SQL byte parsing checked 412 INSERT column counts and inferred references. At the audit checkpoint no Laravel tests, SQL restore, UI/browser checks or mail delivery had run. The authoritative reference baseline is [database-audit.md](docs/database-audit.md), with route/action coverage in [legacy-audit.md](docs/legacy-audit.md). Subsequent M1 checks are recorded below and do not establish migration or domain-workflow coverage.

Add the following focused regressions when implementation begins:

- Deny guest/nonadmin directory AJAX and every admin_action replacement, including approval/priority, service mutation, contractor Notes and document archive/delete/upload. Derive contractor service owner from session; reject hostile IDs targeting another contractor or admin.
- Recheck DB approval/role/lifecycle after login and during actions; direct file/thumbnail/print/download requests cannot bypass verification, review, ownership or nested-job scope. Verify the D1 limited-onboarding exception separately.
- Test coherent immediate admin acceptance timestamps, repeated accepts, concurrent reassignment/accept/upload, reopen/completion transitions and previous-assignee access. Confirm D3 semantics before fixtures are accepted.
- Preserve 0.00 and fractional prices; enforce deposit/price rules identically on create/edit. Verify all status/date filters, state-0 directory filter, date-only due-today boundaries, completed-job metrics, active W9 and archived/expired insurance rules.
- Test successful admin contractor creation sets a real ID, saves selected services and correct actor/subject log; user/services/log rollback works on InnoDB. Partial profile/document storage failures must not leave false review success.
- Test detected MIME, explicit document archived=0, safe response names/MIME/headers, corrupt/large decoded images, agreed photo/document limits and GIF handling. Batch media preserves successes and deduplicates retries; before-acceptance contractor upload fails.
- Test shared Job_Notes versus private account Notes, including missing legacy contractor note feature after D2; do not assert unapproved edit/delete permissions. Referenced services cannot disappear from existing jobs; archived/deleted actors retain readable history.
- Test rich description/terms/templates with permitted formatting and hostile HTML/attribute/URL cases; filenames, job names, filter dates and flash messages cannot inject HTML or script.
- Test event dispatch only after commit, no event on failed/no-op mutations under agreed rules, precise actor/recipients/preferences, nullable-email skips, deduplicated batch/expiry notices, and private failures. Import never sends workflow email.
- Restore counts: Users 33, Services 36, ContractorServices 117, Jobs 1, Job_Services 5, Files 2, Templates 3, ActivityLogs 215; Job_Notes/Job_Media/Notes 0. Preserve the 15 orphan log references (IDs 11/12/14), custom keys/auto IDs and BLOB checksums. Use synthetic notes/media and controlled bcrypt fixtures because the dump supplies no such collaboration rows or known plaintext passwords.

Do not turn source defects into expected target behavior. D1 pending access is now explicitly status/verification only; the remaining decisions still require review before dependent workflows. Record intended departures alongside feature tests. The local Laravel commands and browser emulation are available; native-device and hosting checks remain deferred.

## Technical-review additions — 2026-10-01

Review findings and recommended decisions are in [review.md](docs/review.md). These are requirements for later tests, not tests executed during this review:

- Verify generated migrations do not create a second users table or collide with Jobs using a queue jobs table. Configure app_queue_jobs or another explicit nonconflicting name; test actual MySQL identifier behavior and signed INT/custom-key references, not only SQLite or a mocked schema. Check target schema on intended hosting as well as local development.
- Verify unverified accounts can receive initial verification, changed-email proof and allowed recovery/invitation without receiving protected work access. These flows must not activate archived/blocked accounts. Ordinary workflow recipient rules stay separate.
- Verify committed intent survives failed dispatch, rollback creates no deliverable intent, duplicate processing does not create duplicate event records, and worker/mail failure records an actionable retry/ambiguous-send outcome. Do not assert exactly-once external SMTP delivery with a mail fake.
- Verify local `/contractors/` base-path equivalents for login/intended redirects, assets, signed verification/reset URLs, previews/downloads and print. Another site session must not impersonate portal authentication; private root/config/source/dump paths must be inaccessible.
- Verify dedicated import preserves historical records without running public registration rules, manufacturing verified-email values or sending events. Retain original rich HTML before sanitization and original acceptance evidence before any deliberate migration transformation.
- Once workflow defaults are accepted, test pre-acceptance contractor notes versus acceptance-required media, completed read-only behavior, explicit reopening/reassignment, material-change reconfirmation and immediate loss of work access during re-review. Preserve admin deletion capability or its accepted replacement with reference/history checks.

## M4 workflow verification — 2026-10-01

The disposable identity test harness now runs the M4 migration and fixture workflows. `M4ContractorWorkflowTest` covers admin search/create/update/approval/archive/restore, profile re-review and cross-owner denial, notes/activity, service create/rename/usage-guarded deletion, private document upload, metadata, authenticated preview/download, cross-contractor denial, archive, and permitted permanent deletion. The suite uses `Storage::fake('private')` and the dedicated M2 test schemas; it never resets the populated local target.

The current sequential feature suite passes **64 tests and 804 assertions**. Formatting, Blade view caching, route compilation, and the pre-existing M2/M3 import, identity, delivery, and ownership tests pass. Browser/device verification for authenticated uploads and native file pickers remains a later M7 check; the route responses are covered at the HTTP boundary.

## M5 jobs, notes, and media verification — 2026-10-01

`M5JobWorkflowTest` covers admin job creation/editing, decimal and deposit validation, service synchronization, approved assignment and reassignment resets, acceptance on behalf, idempotent acceptance/completion, reopening, append-only notes, sanitized rich content, private tagged media, detected MIME and 10 MB limits, authenticated preview/download/thumbnail responses, ownership, stale-assignment writes, guarded deletion, activity records, transition history, print routes, and role-specific screens. `OwnershipPolicyTest` also confirms legacy completed-job media inspection remains readable while contractor writes are blocked at the workflow boundary.

The focused M5 plus ownership run passes **9 tests / 207 assertions**. The final sequential suite passes **75 tests / 974 assertions** with no failures. `php vendor/laravel/pint/builds/pint --test`, `composer validate --strict --no-check-publish`, `composer check-platform-reqs`, `php artisan view:cache`, PHP syntax checks, and `npm run build` pass. The target database is only migrated forward with `app_job_transitions`; disposable test schemas are reset by the test harness and the imported target rows are not reset or re-imported.

Browser navigation in the existing in-app tab remains subject to its URL policy. Native camera/file-picker, PDF rendering/download, and device accessibility checks remain explicitly deferred to M7; authenticated HTTP responses, private storage, and responsive Blade output are covered locally.

## M6 regression coverage - 2026-10-01

M6DashboardTest exercises real full-dataset metrics beyond the visible-card limits, date-only inclusive windows, owned job filters and private global document views. M6ManagementTest covers service search, template CRUD/unique names/sanitized JSON/cache directives, all admin-only boundaries, strict log filters, complete filtered statistics, orphan account associations and honest print truncation. M6ReviewTest covers explicit bulk selections, verification separation, archival preservation, stale/admin/blocked/missing targets, rollback-before-mutation, malformed inputs, ALL-service filtering, directory parity and the 1,000 print cap.

M6NotificationsTest covers switches, meaningful transitions/no-ops, same-instant workflow episodes, rollback and after-commit dispatch, reassignment/re-review eligibility, pending approval-status mail, generic notes/upload batches, private failure/retry, duplicate worker processing, local external-transport rejection, real array-mail rendering, Insurance boundaries/dedup/current owner checks, invalid/non-current recording dates and rejection of the 31st note before mutation/event creation. The final run also exercises M1-M5 regressions sequentially against guarded disposable schemas. Test evidence and unresolved device/release work are in [M6 report](docs/m6-management-dashboards.md).

M6 final verification: **110 tests / 1,392 assertions** passed sequentially without failures or skips. Formatter, 137 PHP syntax checks, Blade compilation, strict Composer/platform checks, Vite build and loopback health/preview/private-path checks pass. All 412 imported rows, four private copies, the original SQL digest and 60 legacy file hashes remain unchanged. Native/device checks remain M7; remaining M4.5 and production rollout are not completed or authorized.

## Notification center and blocking continuation — 2026-10-01

NotificationCenterTest covers inbox ownership/idempotent reads, role gates, email preference validation and creation/delivery rechecks, security-token isolation, registration inbox independent of mail, rollback, reassignment and private admin retries. ContractorBlockingTest covers record preservation, session revocation, unblocking to review, reason/confirmation/admin-only rules and atomic bulk cancellation. StagedInsuranceReminderTest covers stage progression, deduplication and current-date/file eligibility. All use the guarded disposable identity database harness.

The initial focused 36 tests / 430 assertions passed. Vite build, Blade and route compilation passed. The final full-suite result is recorded in session-handoff.md. Main local development only received additive tables and the inbox environment flag; no domain test fixtures were added there.

Final continuation verification: 120 tests / 1,508 assertions passed sequentially with no failures or skips. Pint, Vite build, Blade and route compilation passed; local health returned HTTP 200. All 60 legacy file hashes and the SQL export digest remain unchanged. The intermediate full run used an already-loaded outdated retry-job assertion; the final settled-file rerun passed. No production action or external email delivery occurred.

## M7 upload reliability — 2026-10-02

`MediaUploadRetryTest` covers identical replay, changed payload/tag/group conflicts, current ownership/approval/acceptance and removed-media checks, partial success, failed-storage rollback, grouped events, and admin/non-JavaScript compatibility. `node --test tests/js/uploads.test.js` covers file-type/size/empty/HEIC boundaries and safe error messages. Run MariaDB suites sequentially; do not reset the review fixture during browser checks.

Passed: 18 upload/M4/M5 tests / 296 assertions and 13 M6 notification tests / 187 assertions (31 / 483), plus two JS tests, production build, Pint, Blade and syntax. Browser partial success, outage/retry and document submission passed in m2_test. No new full-suite or native-device pass is claimed. Detailed executed checks and the effective 477 CSS px limitation are in [upload reliability](docs/m7-upload-reliability.md).

## Notification inbox management — 2026-10-02

Added fixed-type/date filters, status tabs/counts, individual read/unread controls and selected bulk actions to the notification inbox. Authorization is recipient-only even for admins; all-or-nothing selection validation, 50-item cap, rate limiting and current resource link checks remain active. Migration 000009 makes inbox arrival timestamps immutable. `NotificationInboxManagementTest` plus the existing notification suite passed 11 tests / 135 assertions. Browser review passed on disposable m2_test. See [notification inbox management](docs/notification-inbox-management.md).

## M8 release preparation — 2026-10-02

The new `release:preflight` command is read-only and supports table or JSON output. Normal local preflight passed all checks, including APP key presence without disclosure, debug mode, database connectivity, migration table, private storage, and compiled assets. Strict mode was also run and correctly returned exit code 1 with the hosting-specific blockers: HTTPS, `/contractors` base path, Secure session cookies, persistent queue, external mail, and production bot-control configuration.

Release validation passed: Pint, PHP lint, `composer validate --strict --no-check-publish`, `composer check-platform-reqs`, `npm run build`, `php artisan view:cache`, `php artisan route:cache`, `git diff --check`, and HTTP `/health` (200). The deployment runbook documents the required host facts, backup/transfer boundary, rollback choices, and post-deployment checks. No production or legacy/SQL files were changed.

## Branding and M7 continuation - 2026-10-02

Focused regression passed 31 tests / 407 assertions (Foundation, M4, M5, ownership, upload retry) plus 2 upload JavaScript checks. Browser document upload/download and measured responsive widths are recorded in [M7 branding verification](docs/m7-branding-verification.md). The review fixture now produces a renderable one-page PDF. Native camera, browser PDF viewer, exact 320 px and full accessibility coverage remain open.


## Brevo and background processing — 2026-10-02

41 targeted tests / 409 assertions passed: BrevoTransportTest, BackgroundScheduleTest, BackgroundProcessingTest, IdentityDeliveryTest, StagedInsuranceReminderTest, M6NotificationsTest, NotificationCenterTest, VerificationRecoveryTest and LocalMailTest. API tests mock Brevo; database-worker tests use the disposable database and verify durable verification/reset/business delivery, unique retry enqueueing and token rechecks. Host cron and actual inbox receipt remain unverified. Composer/platform, Pint and PHP lint passed. See [setup and evidence](docs/brevo-shared-hosting.md).
