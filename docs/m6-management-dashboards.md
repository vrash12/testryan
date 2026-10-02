# M6 — Management, dashboards and business mail

Updated: 2026-10-01 (Asia/Shanghai).

Status: Complete for the verified local M6 deliverables. Product acceptance, source completeness, remaining M4.5 and M7/M8 are separate gates.

The user authorized M6 after the local M1–M5 implementation. All changes are in `laravel-app/` and project documentation. Production, the legacy snapshot, the SQL export, imported identities and preserved passwords were not edited. Pending contractors still have account status and email verification only.

## Management and route coverage

All management routes require an active, verified admin session and the relevant policy. Contractors cannot reach their HTML, JSON, print or mutation endpoints.

| Area | Laravel routes and behavior | Legacy reference |
| --- | --- | --- |
| Services | `/admin/services` search and pagination, create/rename, contractor/job usage counts, deletion blocked by either relationship | `admin_services.php`, service handlers |
| Templates | `/admin/templates`, `/create`, `/{template}/edit`, POST/PUT/DELETE, `/{template}/content`; unique name, Jobs/Email/Contractor types, formatting toolbar, escaped original editor, sanitized preview | `admin_templates.php` and template handlers |
| Job template insertion | Explicit append to description or terms; authenticated sanitized JSON, draft text retained, server sanitization again on display/print | Legacy job template selection |
| Activity | `/admin/activity`, `/admin/activity/print`; account association, action substring, inclusive calendar date range, 30 per page; filtered total, role association, orphan and today statistics | `admin_logs.php` |
| Contractor review | `/admin/review` and POST; pending states 0 and 2, active accounts, contact/domain/date/services/documents and escaped private registration indicators | Legacy approval queue plus required registration review |
| Bulk review | Explicit distinct selection of 1–50 accounts, confirmation, approve/set not approved/archive; user/state locks, entire selection validated before any change, audited transactional writes | Safe replacement for bulk review/archive requirement |
| Contractor directory/print | `/admin/contractors`, `/admin/contractors/print`; name/company/email/phone, approval, priority, ALL selected services, missing W9 and include-archived filters | `admin_directory.php`, missing print destination |
| Document overview | `/admin/documents`; search, type, active/archived and Insurance expiring/expired filters; policy-protected management/preview/download links | `admin_dashboard.php` document alerts |
| Admin dashboard | `/admin`; real totals, separate states 0/2, open jobs by state, overdue/upcoming work, missing W9, expiring/expired insurance, recent activity, filtered destination links | `admin_dashboard.php` |
| Contractor dashboard | `/contractor`; own job/document totals, readiness and next action, own overdue/upcoming work, current approval, related links | Contractor landing workflow |
| Job filters | Admin and own contractor lists support search, state and overdue/next-seven-day filters with preserved pagination | Legacy job/dashboard views |

Directory prints explicitly show their full matching total and the first 1,000 limit; activity prints show their matching total and latest 2,000 limit. Historical `ActivityLogs.user_id` is labeled an associated account rather than inventing a reliable actor. Orphan associations remain readable. New security audit entries identify their actor and affected account in the recorded action.

Dashboard totals query the full matching dataset independently of limited cards (8 alerts, 10 contractor jobs/recent activity). Active contractor alerts exclude archived/blocked accounts and administrators. W9 presence uses the stored W9 type, not a guessed filename, and excludes archived files. Insurance reminders include today and day 30; expired insurance is separate. Incomplete jobs are overdue before today and upcoming today through day 7. A contractor's document readiness requires an active W9 and Insurance with no recorded expiry or expiry today/later. This is a UI readiness indicator, not an additional work-access gate. Dates use the configured application timezone, provisionally UTC.

Desktop and phone navigation now expose implemented Jobs, Templates, Activity, Review and Documents destinations. Phone shortcuts follow the role-specific primary destinations. Preview pages remain synthetic shells without queries, authentication or private records. Blade layouts stack cards and wrap long content; template controls no longer impose a phone-width minimum.

## Rich HTML

Original template/job HTML remains stored for migration reversibility. `JobContent` uses Symfony's safe-element sanitizer for job details, print, template preview and insertion. Editors escape the original HTML. Scripts, event handlers and unsafe URLs cannot reach the rendered preview. Email and Contractor templates can be managed but do not automatically become business notification bodies; generic Laravel mail keeps private descriptions, terms, notes and documents out of messages.

## What legacy mail evidence proves

`admin_update_contractor.php:245` calls `sendAdminNotification` synchronously before updating the user, including no-op summaries and hard-coded administrator recipients. `admin_assign_job.php:55–56` has a commented-out assignment send. `includes/test_mail.php` is a diagnostic. PHPMailer source is missing. The snapshot therefore proves a profile-update mail call, not working delivery for every required event. No legacy addresses, SMTP settings or passwords were copied into the new notification configuration.

## Implemented notification defaults

These are local implementation defaults from the migration review, not accepted production settings. `NESTCRAFTERS_BUSINESS_NOTIFICATIONS=false` is the default master switch; individual event switches are in `.env.example` and `config/notifications.php`. Identity verification, recovery and enabled registration notices retain their independent controls.

| Event | Recipients when enabled |
| --- | --- |
| Profile/services/photo change or photo removal | Active verified administrators, excluding the actor; meaningful changes only |
| Approval state change, including state 0 | Active verified affected contractor, status page only; unchanged state sends nothing |
| Assignment/reassignment or material job detail/services change | Current active verified assigned contractor; no-op saves send nothing |
| Acceptance | Active verified administrators, excluding the actor |
| Completion | Active verified administrators and assigned contractor, excluding the actor; repeated completion sends nothing, a later reopen/completion is a new event |
| Shared job note or uploaded media batch | Contractor for an administrator's action; administrators for a contractor's action; no note contents/attachments in mail |
| Insurance within 30 days | Active verified administrators; one reminder per document and expiry date |

Intent creation is inside the workflow transaction; dispatch is after the outer commit. `app_notification_events` stores encrypted payloads, and `app_notification_deliveries` retains per-recipient outcomes privately. ULIDs distinguish rapid repeated workflow episodes. Stable expiry deduplication uses the database unique key and race-aware creation. Rollback creates neither committed mail intent nor queued work. Enabled events with no eligible recipients create no future backfill.

Delivery rechecks current email, active account, verification, event/master switches and job view policy. Reassignment or approval suspension revokes old contractor links. Expiry delivery rechecks current owner role/activity, Insurance type, archive state, unchanged expiry and the current reminder window. Role-specific URLs are built at delivery. Approval mail remains usable by pending verified contractors without granting access. Details and attachments remain available only after authenticated authorization.

Local/testing permits only the `log` and `array` transports and rejects external transports before sending. Failures store only exception class, private identifiers and bounded retry timing; addresses/tokens or SMTP failure details are not shown in the UI. The existing `DeliverIdentityEvent` job and `nestcrafters:retry-identity-mail` command now process both identity and business mail. A crash after transport handoff can still be ambiguous; this does not promise exactly-once SMTP delivery. High-volume profile/job/note/media/review mutations have a shared 30-per-minute authenticated write limit in addition to the existing identity limits.

To exercise business mail locally, set the master switch true in the ignored environment with `MAIL_MAILER=log`, clear cached configuration if present, and use synthetic accounts in disposable tests. Captures contain private links and must not be shared. This milestone did not enable mail against the imported accounts.

`php artisan nestcrafters:notify-document-expiry` records eligible Insurance reminders. Optional `--date=YYYY-MM-DD` must be a valid calendar date equal to the current configured business date. Other dates are refused before creating intent so an early skipped reminder cannot consume the later deduplication key. Daily reruns deduplicate the same expiry. No scheduler or external worker was enabled; business timezone and hosting must be decided before scheduling release reminders.

## Verification

The final sequential `php artisan test --compact` run passes **110 tests / 1,392 assertions**, with no failures or skips, including 35 M6 tests. A prior focused run passed 34 tests / 342 assertions before the additional throttling/date corrections; the full final suite covers those corrections too. All database tests use the guarded disposable MariaDB schemas and never reset the populated target.

`php vendor/laravel/pint/builds/pint --test`, `composer validate --strict --no-check-publish`, `composer check-platform-reqs`, `php artisan view:cache`, PHP syntax checks across 137 application/config/route/test files, and `npm run build` pass. The local route inventory resolves 89 application routes. UTF-8 checks pass across application sources and Markdown documentation. Actual loopback health returns HTTP 200 with `{"status":"ok"}`, the admin shell preview returns 200 with its no-record banner, and `/.env` returns 404. The stopped preview server was restarted hidden on `127.0.0.1:8011` and remains available.

M6 adds no tables or legacy column changes; the target remains at 21 tables. The verified import rerun reconciles all 412 source rows, four private copies, original next IDs/hashes/bytes and 15 preserved orphan log associations. All 60 legacy manifest file hashes remain unchanged. The original SQL SHA-256 remains `3ebef325d80b514f900f462673ba40398365fbbcdb4162c20b00771469495fc5`. Main imported accounts remain unverified, and development verification did not send them business mail.

## Unresolved scope and release decisions

- M4.5 is still incomplete: per-user email preferences, notification inbox/unread state, admin delivery-error UI, explicit block/unblock controls and live bot-provider configuration/retention need implementation and review. M6 supplies its business-event and safe review foundations.
- D2–D10 remain product/release decisions, including notes/completion/reassignment defaults, recipient rules, withdrawal/reopen/deletion mail, document retention, material-change acceptance reconfirmation, currency, business timezone and hosting/mail/queue/scheduler. Bulk rejection uses legacy state 0, so it does not invent a separate permanent rejection state.
- Source completeness and missing legacy handlers/dependencies remain M0 gaps. Existing imported files are still archive-only evidence. No new completion/deletion permissions or production acceptance are implied by this milestone.
- Native phone, keyboard, camera/file-picker, PDF and device checks remain the M7 gate. Passing HTTP tests and compiling responsive Blade screens do not substitute for those checks.
- M8 deployment remains unauthorized. Production and external messaging were not used.
