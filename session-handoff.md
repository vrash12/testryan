# NestCrafters session handoff

Updated: 2026-10-01 (Asia/Shanghai).

## Objective and current authorization

The user authorized M6 after local M1-M5 delivery. M6 implements management screens, role dashboards, due/document views, safe contractor review, filtered logs/print, templates and configurable business-mail defaults in laravel-app. Final verification and boundaries are in [M6 report](docs/m6-management-dashboards.md). The selected pending experience remains account status/email verification only. Stop before M7 final/native review, remaining M4.5 preferences/inbox/block work or M8 production work unless requested. Legacy edits and production deployment remain unauthorized; M0 and D2-D10 product/release decisions remain open.

Follow `AGENTS.md`, `UI/UX.md`, and `milestones.md`. Production must not be modified during local development.

## Workspace contents

| Path | Purpose and current status |
| --- | --- |
| `AGENTS.md` | Roles, business rules, Laravel direction, and verification requirements. |
| `UI/UX.md` | Tailwind design direction with detailed phone navigation, forms, upload, accessibility, and verification requirements. |
| `milestones.md` | Sequenced migration deliverables and acceptance gates. M0 is still in progress. |
| `nestcrafters_codebase/` | 60 recovered files, including 35 PHP files; manifest in `docs/source-manifest.md`. Incomplete dependencies/handlers; production completeness unverified. |
| `nestcrafters_codebase.zip` | Previously reported partial ZIP; absent from the current workspace. Do not rely on it. |
| `nestcrafters_database.sql` | 3,077,625 bytes; 11 table definitions/412 rows. M2 native restore, target import, text/BLOB/password byte reconciliation completed; original export unchanged. |
| `download_codebase.py`, `inspect_system.py` | Previously reported FTPS helpers; absent from the current workspace. |
| `docs/legacy-audit.md` | Full recovered route/action/feature inventory, missing endpoints/dependencies, risks R1–R18, decisions D1–D10. |
| `docs/database-audit.md` | Exact source DDL, models/keys/types, relationship diagram, row counts and orphan/BLOB/password observations. |
| `docs/permissions-notifications.md` | Legacy versus target permission matrix, actual mail call sites, required event matrix, anti-spam and review controls. |
| `docs/migration-plan.md` | Proposed Laravel directory structure, safe migration stages, state contracts and implementation order. |
| `docs/source-manifest.md` | Every supplied reference file's path, byte length and SHA-256; metadata only. |
| `docs/review.md` | Completed technical review, findings RV1–RV7, recommended D1–D10 rules and remaining factual/readiness gates. |
| `laravel-app/` | Laravel 13/Blade/Tailwind, six migrations, verified import, identity/policies, M4 contractor/document workflows and M5 jobs/notes/media. Local target has 21 tables/all 412 source rows; previews remain empty. |
| `docs/m1-foundation.md` | M1 implementation boundary, versions, route inventory, verification and remaining limits. |
| `docs/m2-data-migration.md` | Current schema/import/private-file mapping, actual reconciliation, rerun/rollback behavior, tests and unresolved decisions. |
| `docs/m3-identity-permissions.md` | Current identity routes, gates, policies, durable mail/abuse controls, local validation and remaining decisions. |
| `docs/m4-contractor-workflows.md` | Current contractor/service/document routes, private storage, permissions, validation and remaining decisions. |
| `docs/m5-jobs-notes-media.md` | Current job routes, transitions, notes/media permissions, private storage, validation and remaining decisions. |

Reference snapshots contain private data and credentials. Do not print secrets, commit them, or serve them from a public document root. Documentation deliberately contains no passwords.

## Production incident and resolution

The live contractor URL returned HTTP 500. The PHP error log showed MySQL access denied at `includes/config.php` when the application used its previous database account.

The user supplied the migrated database account. The configuration was updated to use database/user `pieiyvmy_lazula_direct` at `localhost`. A dollar sign in the new password required a literal PHP string; that was corrected. The login page then returned HTTP 200. Authenticated workflows were not verified by that check.

The FTP account's `/` maps directly to the contractor application directory. The latest observed server path is `/home1/pieiyvmy/public_html/lazula.net/contractors/`; the log is `error_log` within that directory. Credentials were supplied earlier in this chat and are intentionally omitted here.

## Source behavior established so far

- The application is custom PHP with mysqli, server-rendered pages, Bootstrap, jQuery, TinyMCE, and manually included PHPMailer code.
- Users have admin/contractor roles and approval states 0, 1, and 2; labels differ between some screens.
- Contractor profile/service edits set approval to pending admin review.
- Jobs carry separate acceptance/completion flags and timestamps, contractor assignment, due date, services, rich description/terms, approved price, deposit, and material responsibility.
- Admins can accept jobs on behalf of a contractor, including a separate action handler for this workflow.
- Contractors can view assigned jobs. Accepted ownership is the intended media prerequisite, but recovered contractor_job_add_media.php is syntactically broken (unclosed brace), has unreachable processing and a batch/signature mismatch. Do not claim working legacy upload based on its source intent.
- Media tags are Before, During, After, and Other. Job uploads allow JPEG/PNG/PDF up to 10 MB.
- Documents include W9 and insurance, optional expiry, and archival. The admin dashboard highlights insurance expiring within 30 days.
- Profile images, contractor documents, and job media are stored as binary database content.
- Templates populate rich job descriptions and terms. Activity logs record important changes.
- Job pages include print behavior; detail lists include notes/media pagination.

Tables in the recovered SQL: `ActivityLogs`, `ContractorServices`, `Files`, `Job_Media`, `Job_Notes`, `Job_Services`, `Jobs`, `Notes`, `Services`, `Templates`, and `Users`.

## Corrections and unresolved questions

- Earlier messages called the ZIP complete, but the download was manually interrupted while transferring assets. Finish the inventory and transfer before making that claim.
- The SQL was generated by a temporary PHP script using SHOW CREATE TABLE and row INSERT statements, rather than a native mysqldump. It did not verify triggers, views, routines, events, a consistent snapshot, or binary/text round trips. Validate these before relying on it as a migration backup. The temporary export endpoint was removed after download.
- Legacy login does not gate work by approval. M3 implements the user's selected status/verification-only pending experience, with approved verified ownership required for protected resources.
- PHPMailer and an admin notification helper exist, but helper presence alone does not establish which events actually send emails. Verify call sites and expected events.
- Contractor note creation permissions need route-level confirmation; do not infer write access from a note list.
- Some legacy redirects reference `contractor_jobs.php`, which was not present in the root inventory. Preserve useful workflow intent and repair broken navigation in the rebuild.
- A separate `spl/index.php` contains visitor tracking and redirects to an unrelated domain. Treat it as unrelated suspect reference material; do not port or execute it as part of the contractor app.
- Read-only admin review showed 29 pending approvals, 31 contractors missing W9 documents, and several suspicious-looking registrations, including `test@test.com` and unusually formatted Gmail addresses. Treat this as a spam/abuse requirement, not proof that every flagged record is malicious.
- The legacy code contains PHPMailer and an admin-update notification. Job assignment notification is a commented placeholder; the complete notification module remains to be implemented.
- Exact types, indexes and logical relationships are now recorded in `docs/database-audit.md`. All tables are MyISAM/latin1 with no foreign keys; legacy transactions are not reliable rollback boundaries. Static checks found 15 orphan ActivityLogs user references. Existing rows use custom keys, nullable email, and bcrypt `$2y$10$` hashes.
- Source audit found missing admin-role checks in most admin_action branches, no auth in dashboard AJAX, submitted-user-ID trust in contractor service updates, CSRF/GET mutation risks, raw rich HTML/errors, and inconsistent dates/decimal bindings. See R1–R18; no exploitability or production behavior was tested.
- M1/M2 provide the local shell, verified native restore/import and relationships. M3 now implements identity/permissions and account security; domain workflows and production deployment remain pending.

## Next session sequence

1. M1-M6 local implementation is delivered; use docs/m6-management-dashboards.md and laravel-app/README.md for current evidence. Stop before M7, remaining M4.5 and production work unless requested. M0 remains incomplete.
2. Preserve accepted D1 status/verification-only pending access. Resolve remaining notes, consent/reassignment/history, eligibility, uploads/review, retention/deletion, recipients/schedules, managed contacts/bot configuration, timezone/currency/HTML and hosting/recovery facts before dependent workflows. Imported accounts remain unverified without a blanket rollout exception.
3. In a separately authorized task, finish source recovery/completeness and obtain a consistent current snapshot before release transfer. M2 verifies the supplied export's files/text/counts/orphans; it does not prove triggers/views/routines/events or production snapshot consistency.
4. M3 identity is implemented on mapped Users/preserved hashes, separate app_ state/tokens and ten policies. M4 contractor/document and M5 job/note/media slices are implemented with current-account/verified/role middleware, policies, transactions and domain validation. Default users/jobs migrations remain removed.
5. M4.5 identity, durable business events and safe review foundations are implemented. Per-user preferences/inbox, block/unblock, further reminder stages, live bot settings and delivery-error UI remain pending. Business events default off; local/testing refuses external mail. No production mail, worker or scheduler is enabled.
6. Update milestones and this handoff with actual validation, reviewed decisions and limitations. Production rollout remains separately authorized after a concrete verified plan.

## Local verification performed

Read all requested guidance and recovered PHP request/forms/queries/endpoint references. Syntax-only `php -n -l` checks: 34 passed, one contractor media handler parse error. Static binary-safe SQL parsing checked 412 INSERT row column counts and inferred references; definitions/counts are documented. SQL SHA-256: `3ebef325d80b514f900f462673ba40398365fbbcdb4162c20b00771469495fc5`. These checks establish reference evidence, not a successful restore or complete production source.

Export counts: Users 33 (2 admins, 31 contractors: approval 0=28, 1=2, 2=1); Services 36; ContractorServices 117; Jobs 1; Job_Services 5; Files 2; Templates 3; ActivityLogs 215; Notes/Job_Notes/Job_Media 0. Fifteen logs reference absent users 11/12/14. Two documents and two profile BLOBs have recognizable JPEG/PDF prefixes, but byte round trips remain unverified.

Markdown audit documents and architecture/testing/UI/milestone/handoff updates are the deliverables. No application code edits, Laravel project, database restore, feature tests, browser/device verification, mail sends, production access or production modifications were performed during this audit. Historical incident details above are inherited context, not actions taken this turn.

Review validation: source manifest still matches all 60 files and SQL SHA-256 is unchanged. Targeted source inspection confirms job reassignment resets acceptance only, profile/admin-create defects and the shared-note model. New review corrections address case-insensitive Jobs/jobs and Users/users scaffold collisions, signed legacy INT/key mapping, verification-mail exceptions, durable intent/ambiguous SMTP retries, preservation of admin deletion capability, `/contractors/` public-root/session routing and import bypass of new-account validation. No source/application modifications or application tests were made during review.

## M1 local foundation checkpoint

This section records the earlier M1 delivery. Current database/import status is in the M2 checkpoint below.

Versions confirmed before code: PHP 8.4.10 at C:/xampp-new/php/php.exe, Composer 2.8.12, Node 24.13.1, npm 11.8.0 and MariaDB 10.4.32 (running server and client, TCP 127.0.0.1:3306, lower_case_table_names=1). The selected locked application is Laravel 13.34.0, Blade, Tailwind 4.3.3 and Vite 7.3.6. Required PHP extensions/platform checks pass.

Local URL: http://127.0.0.1:8011; start from laravel-app with php artisan serve --host=127.0.0.1 --port=8011. The current turn started a local server. Preview admin/contractor links render role-aware empty layouts but do not authenticate. /admin and /contractor remain auth/role protected; unapproved contractors are denied. /login is a disabled form structure; credential submission/registration/verification/bot control are M3. No test account or fake login was created.

The new nestcrafters_m1 database is empty (zero tables); its dedicated loopback account has a random password stored only in ignored .env. Do not print or overwrite .env. There is no restore/import or migrations executed. Private storage and daily error logs remain outside public; no storage symlink or unauthenticated file route exists. Local mail is captured in private storage/logs/mail.log; a synthetic test command refuses nonlocal/external delivery. File sessions/cache and sync queues avoid default framework schema collisions. User identity maps Users/user_id/password_hash with no synthetic remember_token/updated_at or seeded factory.

Validation: feature tests, Blade compilation, npm asset build, Composer platform/lock checks, zero known package advisories, database connection/zero-table check, captured local mail, actual HTTP health/private-path/CSRF checks, and /contractors/ links/assets/session cookie check. Browser emulation at 320/375/390/430/768/1280px covers landing/login/health/both shells without horizontal overflow; mobile menu pointer/keyboard/expanded-state behavior was checked. No native-device or domain-workflow claim. Exact evidence and remaining limits: docs/m1-foundation.md.

M1 changed only the new app, documentation and root .gitignore. Legacy files and SQL digest are rechecked unchanged. No production request/change or deployment occurred. UTC is provisional; D1–D10 and M0 remain open. Subsequent work must use the current user authorization, not historical incident permissions.

## M2 schema and import checkpoint

The user authorized M2 and population from the attached old SQL. The local target is now nestcrafters_local at 127.0.0.1:3306, with a dedicated same-name user and random password only in ignored laravel-app/.env. It has 15 tables: 11 domain tables, app_import_runs/app_import_rows/app_private_files and app_migrations. The old nestcrafters_m1 remains empty. Staging nestcrafters_m2_legacy preserves native MyISAM/latin1 interpretation with the separate nestcrafters_stage account now SELECT-only. Do not print .env, private reports/logs or reference records.

All 412 rows are populated and verified: Users 33, Services 36, Templates 3, ContractorServices 117, Jobs 1, Files 2, ActivityLogs 215, Job_Services 5; Notes/Job_Notes/Job_Media 0. All 77 original column definitions, signed INT/custom/composite IDs, next counters, hashes/BLOB/text/date/null/decimal values reconcile. Target tables are InnoDB/utf8mb4_unicode_ci with 12 restrictive domain foreign keys and Files.unique_file_id uniqueness. ActivityLogs preserves 15 orphan references without inventing users or adding that FK. No lifecycle/email-verification fields or workflow transitions were manufactured.

Four additional private copies total 2,899,381 bytes (three JPEGs, one PDF), with metadata/digests in app_private_files. Original BLOBs remain intact. Private disk paths are outside public and hidden in normal serialization. No public storage symlink or file-serving route exists. Existing HTML/entities are retained; all text is valid UTF-8, with 58 non-ASCII action cells preserved. Import sessions use +00:00 to retain exported calendar values; original timezone and currency remain unknown. All 33 original bcrypt hashes are byte-identical and recognized; a controlled synthetic known-password fixture proves verification compatibility.

The local server's max_allowed_packet was raised at runtime only from 1 MiB to 32 MiB; a restart can reset it. No my.ini or production configuration changed. Import preflights both sessions. The guard requires local/testing, explicit loopback/schema allowlists and actual hostname/database identity. Advisory locks reject concurrent imports. SQL parsing allows only reviewed DDL and literal INSERT values; DROP/toggle/arbitrary statements are not executed. Target inserts/ledger/file mapping use one transaction and suppress events/mail. Failure rolls back target rows and cleans only new files. Source MyISAM partial restore resumes only byte-verified rows; completed staging is now read-only.

Reproduce baseline verification from laravel-app:

```powershell
php artisan migrate:status
php artisan nestcrafters:import-legacy 'C:\Users\MAURICIO\Documents\ChatGPT\ryan\nestcrafters_database.sql'
php artisan test --compact
```

Migrations already ran and the import command now returns verified-rerun. It does not refresh edited data: changed rows/counters/private bytes abort without overwrite. Do not run migrate:fresh/rollback on the populated target. Rollback/rebuild was tested only on disposable test schemas; source BLOBs/export are retained and private-copy cleanup is separate from migration rollback. A current consistent backup/transfer/rollback plan remains a release requirement.

Validation: **31 tests, 367 assertions pass**, including 14 real-MariaDB import tests plus four parser tests and the 13 M1 checks. Synthetic credentials/storage/mail/notifications/queues use only nestcrafters_m2_test/nestcrafters_m2_source_test, with a dedicated user restricted to those schemas. Test guards run before resets; those tests explicitly skip if ignored M2_TEST_USERNAME/PASSWORD is absent. Do not run the shared-schema tests in parallel. Actual imported user/job edits and service detachment were rolled back, and full source/target checks passed afterward. Formatter, strict Composer validation/platform checks, loopback health/guest/private-path checks and SELECT-only staging rerun pass.

The local URL remains http://127.0.0.1:8011. M1 preview routes still show placeholders and do not authenticate or display the imported records; real login is M3. All 60 legacy hashes and SQL digest remain unchanged. M2 made no production request/change or external mail delivery. D1–D10, missing source behavior, timezone/currency and hosting/release facts remain open. Stop at M2 until the user requests the next milestone.

## M3 identity and permissions checkpoint

The user subsequently requested M3 and selected pending account status/email verification only. Login/registration, role redirects, signed email verification, hashed single-use recovery, current-account/session-version gates, controlled admin password setting and ten domain/private-file policies are implemented. Registration writes a pending contractor/services/audit/identity intents atomically. Approval 0/2 and unverified accounts get no protected work access; blocked/archived/unknown roles fail closed. Existing compatible passwords work, with safe rehash only after successful credentials.

Current nestcrafters_local has 19 tables. Four new app_ tables hold account state, reset tokens, encrypted mail intents and recipient attempts; all 33 imported users have unverified state, and all original 412 rows/77 columns/passwords/four private checksums still reconcile. No main-database demo user, password mutation or approval change was made. First imports after all migrations also initialize unverified states without events. Native staging remains read-only. Do not reset/reimport over edited application records.

Local login: http://127.0.0.1:8011/login. Use an existing account password, then request proof. All admins also need email verification before /admin/accounts. Unknown passwords use ordinary /forgot-password; no blanket reset is imposed. Captured links stay in ignored private mail logs. Do not print .env, tokens, account records, encrypted payloads or private log/report contents. APP_KEY is needed to decrypt durable payloads and must be preserved.

The local server was restarted and final login/health returned 200. Temporary 8012/8013 QA servers and scratch scripts were removed; the user's browser tab was retained and viewport override reset. Once real local workflows write logs or rehash/edit Users, the strict M2 import verification will correctly detect baseline changes and refuse overwrite; it is not a live synchronization command.

Identity events are verification/recovery/registration; active verified admins receive enabled registration notices. Queue-compatible jobs currently run synchronously; due pending/failed deliveries are recoverable with php artisan nestcrafters:retry-identity-mail. Transport handoff/capture is not external mailbox proof; retries can be ambiguous under real SMTP. No backfill, business preferences/expiry scheduler or worker/schema was added. Numeric throttles and private review signals are documented. Arithmetic bot fallback is local/testing only; public configuration fails closed without Turnstile keys. No live provider/SMTP request occurred.

Validation: full sequential suite 64 tests/804 assertions; final first-import regression 14 tests/292 assertions. Formatter, Composer/platform, assets/Blade and actual HTTP health/CSRF/origin checks pass. Browser emulation covers public forms and synthetic authenticated status/account/password screens at all six required widths; narrow authenticated headers/cards were corrected. Synthetic mail passed through Laravel Notifications into a private QA capture. The temporary QA server used only the disposable schema; fixtures did not alter imported accounts. No native-device/workflow/production-delivery claim.

Current detail: docs/m4-contractor-workflows.md. M4 is complete locally. Job transition refinements, business mail recipients/schedules, document/profile retention, notes visibility, timezone/currency, source completeness and hosting facts remain unresolved. Production and all reference hashes remain unchanged. Earlier M1/M2/M3 checkpoint statements are historical; the current M4 checkpoint and authorization govern continuation.

## M4 contractor and document workflow checkpoint

The user authorized M4. The isolated Laravel app now has admin contractor CRUD/search/filter/approval/priority/archive/restore screens, a guarded permanent-delete route for unreferenced contractor accounts, the minimum service catalog, contractor profile/photo/service editing, private W9/insurance/other document upload/preview/download/archive/delete workflows, admin-private contractor notes, activity logging, and role/ownership enforcement. Profile/service changes move contractors to approval state 2 and increment the account session version. Account archive is the default; imported documents are archive-only, while new application documents can be permanently deleted by an authorized admin.

Migration `2026_10_01_000005_create_contractor_upload_tables.php` adds `app_private_uploads` for new private BLOB metadata and paths. Legacy `Files.file_data`, `Users.profile_photo`, `app_private_files`, and all imported rows remain intact. The private disk is outside the public root; file responses are policy-checked controller responses with safe MIME/name/cache headers. New storage is compensated on transaction failure.

New routes are listed in [docs/m4-contractor-workflows.md](docs/m4-contractor-workflows.md). The detailed M4 report records the route inventory, data model, permissions, test boundary, and unresolved decisions. `milestones.md`, `ARCHITECTURE.md`, `TESTING.md`, and `UI/UX.md` now include the M4 checkpoint.

Validation: the final sequential suite passed **64 tests and 804 assertions**, including five M4 tests and 86 assertions. `vendor/bin/pint --test`, `php artisan view:cache`, route compilation and the asset build pass. M4 tests use disposable M2 schemas and fake private storage. The populated `nestcrafters_local` target was only migrated with the new metadata table; do not run `migrate:fresh` or rerun import after application writes. Main local URL remains `http://127.0.0.1:8011`; the server is running. No production request/change or external mail delivery occurred.

Historical M4 checkpoint decisions before M4.5/M5: account Notes visibility, document/profile retention and expiry reminder semantics, business event recipients/preferences, admin bulk review, and job acceptance/media/notes behavior. The M5 checkpoint below records the current local defaults; native device file-picker/PDF checks remain M7 work.

## M5 jobs, notes, and media checkpoint

The user authorized M5. The isolated Laravel app now has admin and contractor job lists/details/print views, transactional create/edit/assignment/unassignment, acceptance on behalf, contractor acceptance, completion/reopen, append-only shared job notes, tagged private media upload, authenticated preview/download/thumbnail, guarded media/job deletion, activity logging, and append-only transition history. Current assignment and approval/verification are rechecked server-side; stale assignment writes are rejected. Completed jobs remain readable to contractors but contractor notes/media writes are blocked; reassignment clears active acceptance/completion and retains history.

Migration `2026_10_01_000006_create_job_transitions_table.php` adds `app_job_transitions` without a Job foreign key so deletion can retain history. The local target is now at 21 tables with all 412 imported rows and no M5 fixture rows. New media is private under generated paths and mapped by `app_private_uploads`; legacy BLOBs remain available. Descriptions, terms and notes are sanitized at render time through Symfony HTML Sanitizer, with original values retained.

Validation: the focused M5 plus ownership run passed **9 tests / 207 assertions**; the final sequential suite passed **75 tests / 974 assertions**. Pint, strict Composer validation/platform checks, Blade cache, PHP lint and Vite build passed. The local HTTP server remains at `http://127.0.0.1:8011`. Browser navigation is limited by the existing in-app URL policy, and native file-picker/PDF/device checks remain M7 work. No production/legacy file, SQL byte, external mail transport or deployment was changed.

M5 defaults pending product review: pre-acceptance shared job notes; acceptance-required contractor media; completed contractor read-only; explicit admin completion/reopen; reassignment resets active state with transition history; and imported media evidence cannot be deleted. M4.5 business notifications/preferences/review queue, M6 dashboards/templates/activity parity, M7 final local/device review and M8 release remain pending.

## M6 delivery checkpoint - 2026-10-01

The latest user instruction authorized M6. Service/template management, admin/own contractor dashboards, due/document views, strict filtered activity statistics/print, ALL-service contractor directory/print and safe explicit bulk review are implemented. Business notification defaults now hook meaningful contractor/job/note/media actions and a manual Insurance expiry command using the existing encrypted outbox, queued dispatch and private retry records. A current-permission recheck prevents stale job/document links. Business mail remains opt-in and local/testing rejects external transports. No scheduler, external worker, main-account notification or production action was enabled.

No new M6 schema is needed: the local target remains at 21 tables with the original 412 source rows and four verified private copies. The SQL digest and all 60 legacy manifest files remain unchanged. Preview server is available at http://127.0.0.1:8011/; imported users are still unverified and there is no demo sign-in bypass.

Read [M6 report](docs/m6-management-dashboards.md) for routes, implementation defaults and final verification evidence. Stop before M7, remaining M4.5 preferences/inbox/block/reminder-stage work, or M8 production work unless requested. M0 source completeness and D2-D10 business/release choices remain open. Do not confuse implemented local defaults with product acceptance or a release-ready state.

M6 final verification: **110 tests / 1,392 assertions** passed sequentially without failures or skips. Formatter, 137 PHP syntax checks, Blade compilation, strict Composer/platform checks, Vite build and loopback health/preview/private-path checks pass. All 412 imported rows, four private copies, the original SQL digest and 60 legacy file hashes remain unchanged. Native/device checks remain M7; remaining M4.5 and production rollout are not completed or authorized.

## M7 local review checkpoint — 2026-10-01

M7 now has a guarded disposable review setup. Run `php artisan m7:prepare-review` from `laravel-app` to reset only `m2_test` after the local import guard passes; serve it with `php -S 127.0.0.1:8013 -t public scripts/serve-m7-review.php`. The fixture provides synthetic admin, approved-contractor, pending-contractor, and unverified-contractor accounts plus representative jobs, documents, notes, media, templates, and activity records. It uses a separate session cookie and never targets `nestcrafters_local`.

The default local browser review passed shared authentication/navigation, the admin dashboard/review queue and private-indicator escaping, approved-contractor dashboard/job detail/acceptance/media gate, and pending account-status/direct protected-route denial. Health returned `200`; `/.env` returned `404`. The accepted-job mutation was disposable and resettable. M6 automated evidence remains **110 tests / 1,392 assertions** with no failures or skips.

M7 remains in progress because exact 320/375/390/430/768/1280 viewport checks, native phone/file-picker/camera/PDF behavior, Safari/Android/accessibility checks, and client decisions D2–D10 were unavailable in this run. No production, legacy, SQL, external mail, or deployment action occurred. See `docs/m7-client-review.md` and `laravel-app/README.md` for the reproducible review commands.

## Local single-account login update — 2026-10-01

Logo update: user-supplied mascot PNG copied unchanged into `laravel-app/public/images/nestcrafters-mascot.png` and used by the shared brand component. Its original aspect ratio/transparency are preserved with object-contain. Existing sidebar toggle behavior verified in-browser; assets build, Blade compilation and image HTTP 200 pass. Screenshot: docs/mascot-logo-preview.png. Production and legacy untouched.

At the user's request, one synthetic administrator account was added directly to the local `nestcrafters_local` database with user ID 9001. It uses the normal hashed-password login and a verified active account state. Login was verified through `http://127.0.0.1:8011/login`, redirecting to `/admin`. The account is local convenience data and is not part of the 412-row SQL import; production, legacy files, and the SQL export remain unchanged.

## Admin visual design checkpoint — 2026-10-01

Started the requested mini neo-brutalist admin styling in the Laravel app: shared shell/components, contractor directory, and dashboard headings/metrics. Corrected mobile menu contrast, scoped brand tokens to the admin body, ensured admin overrides win over default component utilities, and provided sidebar/mobile-menu scrolling. Build, Blade compilation and Pint pass. Browser visual verification remains incomplete due to a connection timeout. No route, policy, controller or database changes were made in this design continuation. Next work should verify the rendered admin screens before extending the styling to individual management forms.
`FoundationTest` passes: 11 tests / 65 assertions. Local `/health` returns 200.
`nLocal contractor access: user-requested approved/verified account contractor.local@nestcrafters.test (ID 9002) created on nestcrafters_local. Normal browser login verified at port 8011 and redirected to /contractor. No jobs assigned; imported accounts unchanged. Password supplied in chat, not recorded here.

Navigation update (2026-10-01): implemented logo-triggered 72 px icon rail/256 px sidebar, distinct section icons, persisted display preference, bottom account/logout, and mobile-logo menu toggle with Escape. Browser checks passed for the local contractor at desktop and 390 px. Captured docs/navigation-rail.png. Build/Blade/Pint/JS checks pass; no database or production changes.

Dashboard refinement (2026-10-01): redesigned both admin and contractor dashboard views with reusable metric/empty states and scoped dashboard CSS. Existing queries, policies and workflow routes remain unchanged. Validation: 16 tests / 103 assertions, Vite, Blade and Pint pass. Continuation completed authenticated browser checks for both dashboards with no horizontal overflow at 320/375/390/430/768/1280 px; desktop and 390 px layouts visually inspected, including long admin approval names. Temporary viewport override reset successfully. Screenshots: docs/contractor-dashboard-design.png, docs/admin-dashboard-design.png, docs/admin-dashboard-mobile.png. Restarted the local server on port 8011 and left the browser on the admin dashboard. Normal sign-in writes its expected local security log; no domain records, production, legacy files, or SQL export were changed. Native-device and broader M7 checks remain separate.

## Unfinished local features continuation — 2026-10-01

User authorized continued unfinished features. Implemented the M4.5 notification inbox/unread bell, own role-specific email preferences, admin delivery status and confirmed single-recipient retries, reversible reasoned contractor block/unblock and atomic bulk block, plus optional staged Insurance reminder command. The main local target received only migration 000007 (two new tables, 23 total) and NESTCRAFTERS_NOTIFICATION_INBOX=true; business email remains paused and no reminder command was run against imported accounts. Existing domain records and sessions were not reset.

Read docs/m45-notification-center.md for routes, defaults, privacy and unresolved release boundaries. Initial focused verification passed 36 tests / 430 assertions; Vite, Blade/route compilation and Pint passed. Contractor notification screens were inspected in-browser at desktop and effective 358 px with no horizontal overflow and viewport reset. Main user remains contractor; no blocking or preference mutations were performed on their account during browser review. Full regression result follows when complete.

Final continuation verification: 120 tests / 1,508 assertions passed sequentially with no failures or skips. Pint, Vite build, Blade and route compilation passed; local health returned HTTP 200. All 60 legacy file hashes and the SQL export digest remain unchanged. The intermediate full run used an already-loaded outdated retry-job assertion; the final settled-file rerun passed. No production action or external email delivery occurred.

## UI/UX unification checkpoint — 2026-10-01

User asked for further admin, contractor and homepage UI/UX improvement. The navy/orange neo-brutalist system now applies to both role shells and all guest pages (previously admin-only via `admin-brutalist`); `app.css` replaces the M1 green tokens, styles every form control under a `portal` body class, and adds primary/secondary/dark/danger button variants. New shared components: `page-header`, `job-card`; `alert`, `badge`, `empty-state`, `input`, `icon` were extended. Redesigned: homepage (onboarding steps, role capabilities, requirements; example job card labeled as illustrative), guest header/footer, sign-in, registration progress, account-status step tracker, job lists for both roles, job detail (timeline, contractor accept callout, media gate link, danger zone), documents (W9/insurance readiness), contractor profile (grouped, re-review warning), contractor directory filters (disclosure with active count). Phone header and bottom navigation fixed for 320 px and active states. Details: `UI/UX.md` "Unified brand system" section.

No routes, controllers, policies, validation or database records changed. `scripts/serve-m7-review.php` now serves PNG/SVG so the logo renders on the 8013 review server. Validation: full sequential suite **120 tests / 1,508 assertions** passed after the main changes; after the final account-status copy edit, a focused rerun (Authentication, Registration, Foundation, M6 dashboard, M5 jobs) passed **39 tests / 452 assertions**. Vite build, Blade compilation and Pint (edited PHP script) pass. Browser emulation on the disposable M7 fixture: no horizontal overflow at 320 px for contractor dashboard/jobs/job detail/documents/profile/account status/homepage and admin dashboard/jobs/job detail/contractors/review/documents/job create/activity/template create; desktop 1280 px and 390/768 px spot checks visually inspected. Native-device checks remain M7. The M7 fixture was re-seeded after the test runs; no records in the main `nestcrafters_local` target were changed. Both local servers share `public/build`, so port 8011 serves the new UI as well.

## M7 upload reliability — 2026-10-02

Added selected-file previews/removal and HEIC guidance to documents and job media. Job media now sends sequential per-file requests with progress, partial success, explicit unconfirmed states and stable retry references. `app_media_upload_receipts` prevents repeat files/audit/events; current permissions/job state are rechecked on replay. One rendered form groups notifications across its successful files. Normal document submission and non-JavaScript atomic job batches remain supported. See `docs/m7-upload-reliability.md` for implementation and lifetime boundaries.

Validation: 18 M4/M5/upload tests / 296 assertions plus 13 M6 notification tests / 187 assertions passed sequentially (31 / 483 total); two JS tests passed. Vite, Pint, Blade, syntax checks and main health passed. Browser testing in isolated m2_test confirmed previews/removal, HEIC/server-MIME errors, preserved partial success, server-outage retry without resending saved files, and normal private document submission. Desktop/effective 477 CSS px pages had no horizontal overflow; viewport reset. Exact widths/native phones remain open. The new synthetic PNG rendered; the original one-pixel fixture thumbnail remained broken in the screenshot. Evidence: docs/upload-selection-review.png.

Local MariaDB and port 8011 were offline and restarted. Only additive migration 000008 was applied to nestcrafters_local (24 tables); no domain re-import/reset. Database restart reset max_allowed_packet to 1 MiB; restored the existing local-only 32 MiB runtime setting, which still needs persistence in eventual hosting setup. PHP upload/POST limits are 40M. Review server 8013 uses a separate cookie and disposable fixture; test runs reset that fixture, so do not run browser review alongside database tests. Business mail remains paused; no production or legacy changes. SQL digest matches the audit baseline. Next: native-device/camera/PDF/download checks, exact responsive matrix and client release decisions, then an approved deployment plan.

## Notification inbox management — 2026-10-02

Continued the local notification center with All/Unread/Read tabs, event-type and inclusive date filters, inbox-wide counts, individual read/unread actions, and selected bulk read/unread changes (up to 50). Selection is recipient-scoped and transactional; foreign/missing mixed selections fail without partial mutation. Pagination preserves filters, the no-JavaScript forms remain usable, and the small JavaScript enhancement selects the current page only. Pending contractors remain outside the inbox gate. Migration 000009 corrected MariaDB's implicit `ON UPDATE CURRENT_TIMESTAMP` on `created_at`, preserving notification arrival time and sort order when read state changes.

Validation: 11 notification-center/inbox tests / 135 assertions passed; Pint, Blade, route cache, JavaScript syntax and main/review health checks passed. Browser fixture review confirmed 3 synthetic updates, 2 unread/1 read counts, page selection marked all read, type filtering, individual mark unread, immutable displayed timestamps and empty-selection validation. Review fixture only; production/legacy/SQL unchanged. Exact native-device checks and full-suite rerun remain separate.

## M8 deployment preparation — 2026-10-02

Added `laravel-app/app/Console/Commands/ReleasePreflight.php`, a read-only release gate that reports secret presence without printing values and verifies URL/base-path expectations, private storage, secure-session posture, queue/mail configuration, bot-control settings, database/migrations, and the production asset manifest. Added [docs/deployment-runbook.md](docs/deployment-runbook.md) with the release artifact, hosting-facts, backup/transfer, cutover, rollback, and post-deploy verification procedure. This is preparation only; no deploy or production connection was attempted.

Validation: non-strict preflight passed; strict preflight returned exit code 1 as expected for local HTTP/127.0.0.1 settings, missing `/contractors` mount, non-Secure local cookies, sync queue, local mail sink, and unconfigured production bot-control. Pint, PHP lint, Composer strict/platform checks, Vite build, Blade cache, route cache, `git diff --check`, and main `/health` HTTP 200 passed. The next release task needs hosting runtime/public-root facts, a current backup owner and restore point, SMTP/queue/scheduler/secrets configuration, a maintenance window, and explicit rollout approval.

## Logo integration and M7 verification continuation — 2026-10-02

Integrated the generated NestCrafters logo into the shared brand component. Expanded shells and guest/auth pages use `public/images/nestcrafters-logo.png`; the collapsed sidebar uses `nestcrafters-logo-mark.png`; and the shared head declares `favicon.png` and the Apple touch icon. The `/contractors/` local helper now serves PNG/SVG/font assets. The logo build, Blade cache, Pint, PHP syntax and Vite production build passed.

The earlier MariaDB blocker is resolved. The 8013 disposable review was restored and branding/document checks continued: no overflow at measured widths 358/374/389/429/768/1280, skip link/form focus verified, synthetic image upload and authenticated PDF download passed. Repaired the header-only review PDF; the downloaded one-page file renders correctly with Poppler. The in-app PDF viewer remains blank, so browser PDF rendering is not passed. Focused regression: 31 tests / 407 assertions and 2 upload JavaScript checks passed. See [branding and M7 evidence](docs/m7-branding-verification.md). Physical camera/native-device behavior, exact 320 px coverage, broader keyboard/screen-reader checks and client acceptance remain open; M7 is in progress. Production, legacy and the imported main database remain unchanged.

## Brevo and shared hosting — 2026-10-02

User selected Brevo and confirmed shared hosting, deferring sender, key, test inbox and timezone. Implemented official Symfony Brevo API mailer, environment-only key, local/testing transport guard, bounded provider timeout and a secret-safe readiness/synthetic-send command. Added migration 000010 (three app_queue_* tables) to the main local database. Added opt-in daily staged reminders, minute retry sweep and bounded shared-host database worker with overlap protection. Individual queued deliveries are unique for five minutes; event jobs fan out on the database queue to keep provider requests bounded.

41 targeted tests / 409 assertions pass, plus Composer strict/platform, Pint and syntax checks. No live API call, email, DNS, host cron, worker installation or production modification occurred. Local defaults remain log/sync and schedules disabled. The disposable review fixture is restored after tests. Read [Brevo/shared-host instructions](docs/brevo-shared-hosting.md) before activation: configure the host's PHP path and cron, provider/domain/sender, business timezone, run preflight and verify actual test inbox receipt. Automated bounce/complaint webhooks remain unimplemented; use Brevo delivery logs during rollout.

## Account settings navigation — 2026-10-02

Removed Account status from admin/contractor navbar lists. Sidebar account name now links to /account/settings; phone contractor shortcut is Settings, and the mobile menu also offers account settings. Eligible users get a styled settings hub inside the shared workspace (profile/services, email preferences, sign-in guidance), including when visiting old /account/status. Pending/unverified users retain status/verification only. No account records changed. Browser verified on the main local contractor at desktop and measured 389 CSS px, no overflow; viewport restored. Two permission/rendering tests pass (21 assertions); Vite and Pint pass. The main browser is left on /account/settings. Screenshots are in docs/account-settings-*.png.
