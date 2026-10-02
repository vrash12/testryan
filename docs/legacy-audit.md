# Legacy route, feature, and risk audit

Audited: 2026-10-01 (Asia/Shanghai). Scope: local reference snapshot only. No application code, production requests, database mutations, email delivery, or deployment were performed.

## Evidence and limits

Read `AGENTS.md`, `UI/UX.md`, `ARCHITECTURE.md`, `TESTING.md`, `milestones.md`, `session-handoff.md`, the 35 recovered PHP files, and the SQL definitions and INSERT records. Reviewed request logic, embedded queries, forms, JavaScript endpoint references, shared helpers, and configuration. Configuration secrets and private record contents are deliberately omitted from these documents.

The source directory contains 60 files: 28 root PHP endpoints, six PHP includes, one unrelated `spl/index.php`, two error logs, 21 asset files, and two metadata files (`assets/.DS_Store`, `.ftpquota`). See the complete [source manifest](source-manifest.md). This is a complete inventory of the supplied snapshot and its observable actions, not proof of a complete production application. Missing handlers cannot establish their historical server behavior. The snapshot lacks most working UI assets and PHPMailer. Runtime behavior was inferred from source, not exercised against production.

PHP syntax-only checks (`php -n -l`, no execution) passed for 34 of 35 files. `contractor_job_add_media.php` fails with an unclosed brace at line 11. SQL was parsed as bytes with quoted-string/escape handling; all 412 recovered INSERT rows matched their table's column count. Database restore, file round trips, and authenticated/browser tests remain unverified. See [database audit](database-audit.md), [permissions and notifications](permissions-notifications.md), and [migration plan](migration-plan.md).

## Route conventions

All legacy paths below are relative to `/contractors/`. `GET/POST` means both rendering and submission exist; `POST fields` means the handler consumes POST data but does not necessarily enforce the HTTP verb. Laravel mappings below are the original recommendations; [M3 report](m3-identity-permissions.md) lists actual identity routes. Admin destinations require fresh database-backed authorization; contractor work requires verified, approved ownership. The subsequent D1 selection allows pending status/verification only.

### Public, account, and shared endpoints

| Legacy route | Inputs, behavior, and UI entry | Proposed Laravel route / controller action |
| --- | --- | --- |
| `index.php` GET/POST | Email/password login; prepared lookup, PHP `password_verify`, session ID regeneration; redirects by `is_admin`; approval is read but never gates access. Login page/public navigation. | `GET/POST /login` → `Auth/AuthenticatedSessionController@create/store` |
| `register.php` GET/POST | First/last name, company/address/phone, email, password ≥8, optional additional phones/types and service IDs. Duplicate-email lookup; user plus services; role=0, approval=0, priority=0; no photo input. Registration link. No event/log call. | `GET/POST /register` → `Auth/RegisteredUserController@create/store` |
| `logout.php` GET | Clears/destroys session; navigation link. | `POST /logout` → `Auth/AuthenticatedSessionController@destroy` |
| `job_media_view.php?id=` GET | Admin any media; contractor current assigned job only through joined lookup. Streams BLOB inline with stored MIME/name. Media viewer. | `GET /job-media/{media}/preview` → `JobMediaController@preview` |
| `job_media_thumbnail.php?id=` GET | Same joined ownership; renders image thumbnail up to 120×120; PDF/denied/missing falls back to asset. Media lists. | `GET /job-media/{media}/thumbnail` → `JobMediaController@thumbnail` |

### Admin pages and standalone handlers

| Legacy route | Feature, inputs, entry points, and side effects | Proposed Laravel route / controller action |
| --- | --- | --- |
| `admin_dashboard.php` GET | Contractor directory, all-selected-service search, approval/priority filters, totals/preferred/pending counts, approval 0/2 queue, unassigned jobs, jobs awaiting acceptance, missing W9, active Insurance expiry today through +30 days. Dashboard navigation. | `GET /admin/dashboard` → `Admin/DashboardController@index` |
| `admin_ajax_dashboard_search.php` GET | `q`, `approved`, `priority`, `service_ids[]`; returns contractor table HTML incl. contact/approval/service data. Dashboard AJAX. **No authentication or role check.** | `GET /admin/contractors/search` → `Admin/ContractorSearchController@index` |
| `admin_directory.php` GET | `approval_status`, `priority`, `service_id`, `search_query`; company/contact/services/completed-job count; print controls. State 0 filter is ignored by `empty()`; filter/reset JS redirects to missing admin_print_contractors.php. Directory navigation. | `GET /admin/contractors` → `Admin/ContractorController@index`; `GET /admin/contractors/print` → `@print` |
| `admin_register.php` GET/POST | Admin creates contractor with optional email, password, status, priority, service selection. **Reads but never saves selected services and never sets `$contractor_id`.** Logs/redirects with unset ID. Its PHP random-password helper is declared later in the file and uses weak rand(). Dashboard add button. | `GET /admin/contractors/create`, `POST /admin/contractors` → `Admin/ContractorController@create/store` |
| `admin_contractor.php?id=` GET | Profile/photo/services editing, approval/priority AJAX, password reset, documents incl. archive flags, admin contractor notes, user-associated logs, assigned jobs, suggested unassigned jobs matching ANY contractor service. Contractor links. | `GET /admin/contractors/{contractor}` → `Admin/ContractorController@show` |
| `admin_update_contractor.php` POST | `contractor_id`, profile/email/status/priority/services, optional image resized within 300×300. Replaces services; logs under target contractor rather than acting admin; calls admin email helper BEFORE final user update; no transaction; approved_date untouched. Contractor edit form. | `PATCH /admin/contractors/{contractor}` → `Admin/ContractorController@update` |
| `admin_assign_job.php` POST | `job_id`, `contractor_id`; updates assignment, clears acceptance/timestamp, retains completion state/timestamp. Logs both admin and contractor-associated text; assignment mail is commented placeholder. Suggestions in contractor/job detail. | `POST /admin/jobs/{job}/assignment` → `Admin/JobAssignmentController@store` |
| `admin_jobs.php` GET | Full job list with client-side search/status/due filters, status/overdue totals and recent job-text activity; create modal, delete confirmation. `Jobs` templates populate description and terms; all nonadmin users offered for assignment. | `GET /admin/jobs` → `Admin/JobController@index`; `GET /admin/jobs/create` → `@create` |
| `admin_job_add.php` POST | Name, rich description required, ≥1 service; optional due/terms/assignment/price/deposit/materials; admin acceptance checkbox. Deposit ≤price check only when both nonempty. Price bound as integer; accepted timestamp not set. Creates job/services + log; debug logs whole form. | `POST /admin/jobs` → `Admin/JobController@store` |
| `admin_job_detail.php?id=` GET | Description/terms/finances, due urgency, assignment suggestions (ANY service, approved only), accept on behalf, notes pagination 10, tagged media pagination 8/tag, viewer and download link. | `GET /admin/jobs/{job}` → `Admin/JobController@show` |
| `admin_job_edit.php?id=` GET/POST | Same fields/services; explicit Unassigned/Pending/Accepted/Completed or no change; keep-acceptance option on reassignment. Completion sets accepted too. Sets timestamps when flag changes to 1 but does not clear them on reset. No server deposit validation. Logs changes. | `GET /admin/jobs/{job}/edit`, `PATCH /admin/jobs/{job}` → `Admin/JobController@edit/update`; transitions via dedicated actions |
| `admin_job_add_note.php` POST | Existing `job_id`, nonempty `note_text`; inserts Job_Notes with admin author and log. Job note modal. | `POST /admin/jobs/{job}/notes` → `Admin/JobNoteController@store` |
| `admin_job_add_media.php` POST | Existing `job_id`, `media_file[]`, tag enum; detected JPEG/PNG/PDF, ≤10×1024² bytes/file. Batch per-file success/failure, logs successes. No acceptance prerequisite for admins. | `POST /admin/jobs/{job}/media` → `Admin/JobMediaController@store` |
| `admin_services.php` GET/POST; GET `delete=` | Lists names/contractor usage counts/recent service-text logs; creates nonempty unique service. Deletion blocked only by ContractorServices usage, ignoring jobs. Navigation/add/edit/delete controls. | `GET/POST /admin/services`, `DELETE /admin/services/{service}` → `Admin/ServiceController@index/store/destroy` |
| `admin_templates.php` GET/POST; GET `action=delete&id=` | Add/edit/delete unique named rich HTML templates, type Jobs/Email/Contractor; preview/editor; updated timestamps. No template-use restriction or activity log. Only Jobs type is consumed by recovered workflows. | `GET/POST /admin/templates`, `PATCH/DELETE /admin/templates/{template}` → `Admin/TemplateController@index/store/update/destroy` |
| `admin_logs.php` GET | `user_id`, `action_type`, `date_from`, `date_to`; all logs via LEFT JOIN user; actor-role/today metrics, first-word action types, text/date/user filters, client-side search. No edit/delete. | `GET /admin/activity` → `Admin/ActivityLogController@index` |

### `admin_action.php?action=…` dispatch inventory

Only session authentication is required at file entry (`includes/auth.php`). Explicit admin checks exist only for photo deletion, password reset, and contractor deletion. File preview checks admin-or-owner. Every other branch below lacks the expected admin gate; do not reproduce those grants.

| Action and legacy input method | Validation / writes / activity / UI | Laravel mapping |
| --- | --- | --- |
| `update_approval_status` POST | `contractor_id`, status 0/1/2; changes approved_date to now if 1, otherwise NULL; log and JSON. Detail dropdown. | `PATCH /admin/contractors/{contractor}/approval` → `ContractorApprovalController@update` |
| `update_priority_status` POST | ID, priority 0/1; update + log/JSON. Preferred toggle. | `PATCH /admin/contractors/{contractor}/priority` → `ContractorPriorityController@update` |
| `add_note` POST | Submitted `user_id`, nonempty note; inserts Notes with session ID as admin_id and actor log. Detail modal. | `POST /admin/contractors/{contractor}/notes` → `ContractorNoteController@store` |
| `delete_file` GET IDs | Alphanumeric unique file ID + submitted contractor ID; scoped existence/delete, no caller ownership check and no activity log. Document menu. | `DELETE /admin/documents/{file}` → `DocumentController@destroy` |
| `file_view` GET `id` | Admin or actual file owner; inline BLOB by unique_file_id. Shared legacy preview. | `GET /documents/{file}/preview` → shared `DocumentController@preview` |
| `upload_file` POST | Target contractor ID, file_type, display name, optional expiry, doc_file; trusts browser MIME PDF/JPEG/PNG; no size ceiling; does not set required archived column; no log. Detail modal. | `POST /admin/contractors/{contractor}/documents` → `DocumentController@store` |
| `archive_file` GET IDs | Sets archived=1 by file ID plus submitted contractor ID; no caller ownership or log. No unarchive branch. | `PATCH /admin/documents/{file}/archive` → `DocumentArchiveController@update` |
| `profile_image` GET `user_id` | Any logged-in user can read any photo/company initial; stored photo always declared JPEG, else generated SVG letter. Multiple lists/profile views. | `GET /users/{user}/photo` → shared `ProfilePhotoController@show` with admin/self policy |
| `delete_profile_image` GET ID | Explicit admin; photo=NULL + actor log. Contractor detail. | `DELETE /admin/contractors/{contractor}/photo` → `ProfilePhotoController@destroy` |
| `reset_password` POST fields | Explicit admin; contractor ID + password ≥8; hash replacement + actor log/JSON, no delivery. Detail password dialog. | `POST /admin/contractors/{contractor}/password` → `ContractorPasswordController@store` |
| `add_service` POST | Nonempty unique name; insert + actor log/JSON. Handler available, no direct recovered form call (screen uses admin_services). | `POST /admin/services` → `ServiceController@store` |
| `edit_service` POST | ID/nonempty name, existing row; DB unique constraint; update + actor log/JSON. Services inline form. | `PATCH /admin/services/{service}` → `ServiceController@update` |
| `approve_contractor` GET `id` | approval=1/date now + log; repeated requests refresh date. Dashboard/AJAX approve link. | Same dedicated approval action as above |
| `delete_contractor` GET ID | Explicit admin; blocks if ANY assigned Jobs row incl. completed; deletes Files, Notes by target, ContractorServices, then Users; logs admin; leaves actor references/logs. Detail delete menu. | `DELETE /admin/contractors/{contractor}` → `ContractorController@destroy` after retention decision D6 |

Controller names in this table are under `Admin/` except explicitly shared preview/photo routes. Legacy action aliases can be consolidated; compatibility GET links must never cause mutations in Laravel.

### `admin_job_action.php` dispatch inventory

All branches enforce admin at file entry; action comes from GET or POST, record fields from POST; no explicit method check. Job list/detail/dashboard are UI entry points unless noted.

| Action | Behavior | Proposed Laravel mapping |
| --- | --- | --- |
| `delete_media` | media_id scoped to job_id, delete + actor log | `DELETE /admin/jobs/{job}/media/{media}` → `Admin/JobMediaController@destroy` |
| `add_note` | Nonempty note/job ID; no existence lookup, insert + log. Duplicate of standalone note handler; no recovered form targets this alias. | Dedicated admin job-note route above |
| `accept_job` | Blind update acceptance/date now by job ID; no existence/assignment/repeat guard; log. No recovered form targets this alias. | Consolidate with guarded accept-on-behalf action |
| `accept_job_on_behalf` | Requires existing, assigned, unaccepted job; sets acceptance/date, log; does not check completion or contractor approval. | `POST /admin/jobs/{job}/acceptance` → `Admin/JobAcceptanceController@store` |
| `delete_job` | Requires existing job; deletes media, notes, services and job; actor log includes child counts. | `DELETE /admin/jobs/{job}` → `Admin/JobController@destroy` |

There is no standalone completion action. Completion/reopening is implemented through admin job editing and must become an explicit, audited transition in Laravel.

### Contractor pages and action dispatch

| Route/action | Legacy behavior and validation | Proposed Laravel mapping |
| --- | --- | --- |
| `contractor_dashboard.php` GET | Nonadmin session; own profile/status/date/services, assigned job list/accept forms, documents incl. archived flag. No approval gate or account Notes listing. | `GET /contractor/dashboard` → `Contractor/DashboardController@index` |
| `contractor_edit.php` GET/POST | Own profile/photo/services; required names/company/address/company phone; stores HTML-escaped profile strings; MIME-detected JPEG/PNG/GIF ≤5 MiB; profile and services update sets approval=2, leaves approved_date; no log/mail. | `GET/PATCH /contractor/profile` → `Contractor/ProfileController@edit/update` |
| `contractor_edit.php?action=delete_photo` GET | Own photo=NULL; approval=2; no log/mail. | `DELETE /contractor/profile/photo` → `Contractor/ProfilePhotoController@destroy` |
| `contractor_action.php?action=update_photo` POST | Own photo, browser MIME JPEG/PNG/GIF ≤2 MiB; no re-review, log, or mail. No form targets this alternate handler in recovered source. | `POST /contractor/profile/photo` → same profile-photo workflow; resolve D5 |
| `contractor_action.php?action=upload_file` POST | Own documents despite submitted user_id; required type/name, browser MIME PDF/JPEG/PNG ≤5 MiB, optional expiry; archived omitted, no log/mail. | `POST /contractor/documents` → `Contractor/DocumentController@store` |
| `contractor_action.php?action=file_view` GET `id` | Unique file ID + session owner lookup; inline BLOB. | Shared authenticated document preview/download |
| `contractor_action.php?action=accept_job` POST | Own assigned job with is_accepted=0; sets acceptance/date; affected-row check; no completion/approval gate, activity log, or email. | `POST /contractor/jobs/{job}/acceptance` → `Contractor/JobAcceptanceController@store` |
| `contractor_action.php?action=update_services` POST | **Trusts submitted user_id**, replaces services and sets that user's approval=2; no owner comparison, log/mail. Dashboard form. | `PATCH /contractor/services` → `Contractor/ServiceSelectionController@update` using session user |
| `contractor_job_detail.php?id=` GET | Own assigned job incl. description/terms/finances; acceptance, print, all Job_Notes (10/page), media (8/tag), viewer; add-note/upload modals. No approval gate or note visibility attribute. | `GET /contractor/jobs/{job}` → `Contractor/JobController@show`; `GET …/print` → `@print` |
| `contractor_job_add_media.php` POST intended | Intended own assigned accepted job, JPEG/PNG/PDF ≤10 MiB, tag + log. **Parse error; processing sits after exit inside invalid-request branch; bind signature has six types for seven arguments; UI submits media_file[] but handler treats it as single file.** Cannot claim functioning upload. | `POST /contractor/jobs/{job}/media` → `Contractor/JobMediaController@store` with batch parity |

Contractors have no implemented completion, document archive/delete, media delete, password change/reset, or account-note mutation handler in the supplied source. These are not implied permissions.

### Missing, infrastructure, and excluded routes

| File / reference | Classification and migration treatment |
| --- | --- |
| `contractor_jobs.php` | Missing; multiple invalid-request/role redirects use it. Add an owned job index in Laravel; no historical handler to audit. |
| `contractor_job_add_note.php` | Missing; contractor job detail posts note_text/job_id here. UI expresses intended write access but permission/acceptance prerequisites unknown (D2). |
| `admin_job_accept.php` | Missing live form target in admin_contractor.php:353. Existing guarded action can replace it. |
| `job_media_download.php` | Missing JS download target in both job viewers. Implement authorized attachment response alongside preview. |
| `admin_print_contractors.php` | Missing JS directory print target; in-page window.print also exists. Preserve filtered directory printing. |
| `includes/auth.php` | Session-presence guard only; no fresh role or approval check. Replace with Laravel middleware/policies. |
| `includes/config.php` | Hardcoded DB credentials, raw connection error; no explicit connection charset. Environment-only replacement; do not port credentials. |
| `includes/functions.php` | Activity logger, admin SMTP helper, service-name lookup, phone formatting, random file ID, default icon. Eager PHPMailer include aborts when library absent; debugging enabled. Split responsibilities. |
| `includes/header.php`, `includes/footer.php` | Bootstrap/jQuery shell; role navigation; greeting links contractors incorrectly to admin detail; duplicate bundle includes. Replace with Tailwind Blade shell. |
| `includes/test_mail.php` | Unguarded diagnostic SMTP sender with debug output/credentials; exclude from target routes and deployment. Never execute for audit. |
| `spl/index.php` | Unrelated visitor IP/device/geolocation logging, ISP/IP/UA filtering and redirects to external domain. No contractor authentication. Suspect provenance; exclude, preserve privately for owner investigation; not portal anti-spam. Never execute. |
| `error_log`, `includes/error_log` | Private diagnostic artifacts; no application route. Do not serve or copy to public target. |

Comments mention old merged names `add_note.php`, `delete_file.php`, `file_view.php`, `admin_upload_file.php`, `archive_file.php`, `approve_contractor.php`. These are historical aliases, not additional proven missing active handlers.

Missing dependencies: `includes/PHPMailer/src/{Exception,PHPMailer,SMTP}.php`; `assets/css/bootstrap.min.css`, `assets/css/custom.css`; `assets/js/bootstrap.bundle.min.js`, `assets/js/custom.js`; `assets/img/{logo,placeholder,file-icon}.png`; `assets/tinymce/js/tinymce/tinymce.min.js` and associated editor files. Includes emit root-relative browser paths; their navigation targets are present and must not be incorrectly classified missing by filesystem-relative scanning. External CDN dependencies exist but local UI was not runnable/visually verified.

Prior handoff mentions `nestcrafters_codebase.zip`, `download_codebase.py`, and `inspect_system.py`; none exists in the current workspace. Do not claim archive/helper availability or recursive transfer completion.

## Feature preservation checklist

| Domain | Preserve or replace deliberately | Gaps / intended additions |
| --- | --- | --- |
| Account | Role redirects, imported bcrypt passwords, public pending registration, nullable-email admin-created records | Approval/verification gate, rate limits, duplicate/risk review, recovery flow; no public admin signup |
| Contractors | Company/contact fields, two phone types, preferred flag, service selection, re-review, approval date, photos, admin notes/documents/jobs/logs | Repair create/services/IDs; decide photo limits and visibility; accurate actor/subject audit |
| Jobs | Optional assignment/date/terms/finances/materials; name/description/services required in UI; separate accepted/completed flags/dates; admin acceptance/completion/reopening; any-service suggestions | Unified transitions, approval eligibility, timestamp consistency, decimal validation, acceptance evidence and reassignment semantics |
| Documents | W9/Insurance/Other UI types; display names, dates, optional expiry, archive state, inline preview | Consistent size/MIME/date rules, archive exclusion, authenticated download, replacement/retention decision |
| Job collaboration | Notes with authors/dates; Before/During/After/Other media; multiple uploads, partial success, thumbnails/viewer, notes/media pagination, print | Recover intended contractor notes, repair upload; defined visibility/deletion/post-completion rights |
| Management | Unique services with counts and safe deletion; unique templates Jobs/Email/Contractor; Logs filters and history | Job-service deletion restriction, rich HTML sanitizer, template-use semantics, typed activity metadata |
| Dashboard/directory | Approval queue; preferred/search/service filters; missing W9 and +30-day insurance; assignment and acceptance queues; completed-job counts; directory printing | Correct state-0 filter, server pagination, overdue/completed metrics, expired insurance and active W9 semantics |
| Notifications | One proven admin-record-update mail call | All other required events, preferences, private delivery failures, in-app history, scheduler reminders |
| UI | Role navigation, accessible mobile workflows, print, validation/feedback | Tailwind replaces Bootstrap; browser/device verification pending; missing destinations must not become dead links |

## Security and consistency findings

Priorities describe source risk; production exploitability was not tested.

| ID / priority | Evidence | Required treatment |
| --- | --- | --- |
| R1 Critical | `admin_action.php:7-14` has auth only; approval, priority, admin Notes, files, services, quick approval branches lack role checks | Admin policies on every lookup/mutation, resource ownership, enforce contractor target role, direct denial tests |
| R2 High | `admin_ajax_dashboard_search.php:1-14` has DB include only and returns contact records | Auth/admin gate, private cache behavior, paginated/limited search |
| R3 High | `contractor_action.php:164-195` trusts POST user_id for services/approval | Session-bound owner; hostile-ID tests incl. targeting admin |
| R4 High | State-changing GET links, no CSRF token validation found in recovered portal PHP; unguarded test_mail | Non-GET mutations with CSRF and explicit verbs; omit diagnostics |
| R5 High | `index.php:35-48`, contractor handlers: no approved/verified gate; session roles stale | Refresh authorization from DB; limit pending accounts, invalidate revoked/blocked sessions |
| R6 High | Config/helper/test-mail store credentials; `display_errors=1`, raw SQL/SMTP errors, whole job POST logging, logs in snapshot | Environment secrets, privately managed rotation before rollout, private logs, redaction, no raw request/password/BLOB logging |
| R7 High | Jobs/terms/templates rendered raw; template JSON in single-quoted onclick; logs filter date values unescaped; raw flash HTML includes filenames/job names | HTML allowlist sanitizer, context-safe JSON/events, escaped plain text and feedback |
| R8 High | All SQL tables MyISAM/latin1 and no foreign keys; 15 orphan actor logs | Staged InnoDB conversion, integrity report and retention strategy before FK addition; encoding verification |
| R9 High | Contractor media handler parse/flow/signature/batch defects; dependency library missing | Implement intended workflow independently, test accepted ownership and batch partial failures |
| R10 Medium | `admin_job_add.php:80` binds approved_price as integer; admin_accept omits accepted_datetime; zero becomes NULL; edit lacks deposit rule | Decimal strings/cents, zero/null fidelity; coherent dates and amount rules |
| R11 Medium | `admin_assign_job.php:36` resets acceptance but retains completion; edit keeps stale dates after resets and optionally previous acceptance on reassignment | One transaction/locked transition action with historical evidence; decide D3 |
| R12 Medium | admin_register.php missing insert_id/service writes and weak rand() generated passwords; multiple mixed output-before-header paths | Validated atomic account/services/log creation; secure provisioning and proper redirect responses |
| R13 Medium | Document MIME is client-supplied, admin upload lacks cap, archived NOT NULL has no default but inserts omit it; expiry/type unvalidated | Content MIME validation, limits and enum/date rules, explicit archived=0, safe inline headers |
| R14 Medium | Photo handlers disagree 2/5 MiB, some leave approval unchanged, streams always JPEG | Unified photo workflow, dimensions/decoded-content limits, correct MIME, D5 |
| R15 Medium | Service deletion checks contractors but not jobs; contractor deletion blocks even completed assignments yet message suggests completing helps; leaves authored references | Restrict/retire referenced services; preserve historical actor/subject and define deletion/archive policy |
| R16 Medium | Admin edit logs/mails before user write, as target user; many contractor/document/template mutations have no log/mail | Atomic typed actor/subject audit, event only after commit; idempotent delivery/failure tracking |
| R17 Medium | Missing-W9 query counts archived W9; insurance query omits expired; job list metrics ignore completion and due-today time boundary | Define active documents/date rules; fix metrics with tested date-only logic |
| R18 Investigate | `spl/index.php` unrelated outbound redirects/logging | Owner provenance investigation; exclude from migration; no conclusion of compromise from source alone |
| R19 High — target design | Default Laravel queue/auth scaffold names jobs/users can collide with legacy Jobs/Users under case-insensitive table handling | Adapt auth migrations and explicitly name ancillary queue tables, match custom signed INT keys, test actual MySQL case behavior; see technical review RV1 |

## Decisions for review

Recommendations are proposed and have not been accepted merely by writing this audit. Required security/ownership/approval rules in AGENTS.md already apply.

| ID | Unresolved decision | Recommended starting position / impact |
| --- | --- | --- |
| D1 | What can approval 0/2 users do, and how should existing users acquire verified-email state? | User selected status/email verification only; M3 gates profile/services/documents/jobs by verified approval and allows status/logout/verification while pending. Imported accounts remain unverified under the existing requirement/default; a release exception has not been accepted. |
| D2 | Can contractors add Job_Notes before acceptance, after completion, or delete/edit their notes? Are Notes private to admins? | Treat account Notes as admin-only; all legacy Job_Notes shared with current assignee. Add append-only contractor job notes only for own eligible jobs; approve precise acceptance/completion rule before shipping missing feature. |
| D3 | Reassignment, reopening, acceptance retention, completion authority and evidence | Reset acceptance/completion on reassignment; retain prior transitions in separate history. Only admin completes/reopens. Do not transfer another contractor's acceptance implicitly; decide explicit admin override and terms-version evidence. |
| D4 | Is service matching ANY or ALL, and can admins assign unapproved/nonmatching contractors? | Preserve ANY-service suggestions; recommend approved contractors only for assignment, with any deliberate override explicitly authorized/logged. Dashboard selected-service search remains ALL selected. |
| D5 | Photo review and limits; admin vs contractor document caps; GIF, Other docs, expiry semantics | Propose JPEG/PNG/GIF profile ≤5 MiB with decoded pixel limits, every self profile/photo edit re-review; documents PDF/JPEG/PNG ≤5 MiB for both roles; media ≤10 MiB. Confirm limits/phone format handling and whether document changes trigger review. |
| D6 | Contractor/job/service/template deletion, spam archive/block, file replacement and historical access | Prefer account archive/disable; restrict referenced services; retain job/actor history. Define retention, purge authorization, document replacement/archive visibility, whether current assignee sees prior contractor media/notes. Preserve 15 orphan logs without invented users. |
| D7 | Event enablement, recipients, preferences and reminder schedule | See event matrix; configure central events + user preferences with security/verification exceptions. Decide old/new assignee recipients, completion recipient, admin-authored notes/media mail, expiry offsets/digest and deduplication. |
| D8 | Nullable-email contacts vs login accounts, admin provisioning/reset, duplicates and old unverified users | Preserve nullable-email records as managed contacts without login; verified invitation/recovery for usable email accounts. Decide manual admin-set password parity vs reset links, admin accounts/MFA, duplicate thresholds, risk-data retention. |
| D9 | Money/date/encoding/rich HTML | Proposed nonnegative decimals, deposit ≤known price on create/edit; choose deposit without price and currency. Confirm business timezone independent of audit timezone; verify latin1/UTF-8/entities before conversion; approve HTML allowlist and template categories. |
| D10 | Hosting/source/export recovery and release prerequisites | Identify compatible PHP/MySQL/Node, queue/scheduler/SMTP support later; recover missing files read-only only when authorized; obtain complete consistent export and validate local restore before relying on backup. No implementation or deployment authorization in this turn. |

The subsequent [technical review](review.md) records RV1–RV7 and current decisions. M1/M2/M3/M4/M5 were later authorized and completed locally, including native restore, identity/permissions, contractor/document workflows, jobs, notes and private media. D1 pending access is selected; D2–D10, source completeness and release facts remain unresolved. M0 remains in progress; M4.5 business notifications, M6 management parity, M7 device review and deployment remain separately gated.

## M5 audit checkpoint — 2026-10-01

The Laravel replacement now maps the legacy job surface to named admin/contractor routes and a transactional `JobManagement` action. It preserves `Jobs`, `Job_Services`, `Job_Notes`, and `Job_Media` IDs and fields, adds append-only `app_job_transitions` history, and stores new media privately with metadata while retaining legacy BLOB compatibility. Policies enforce current assignment, approval/verification gates, nested media scope, acceptance-before-media, and completed-job read-only contractor behavior. Rich content is sanitized only at render time; stored source values remain available.

The recovered legacy source still has the known missing/invalid contractor media handler and does not prove complete notification behavior. M5 therefore implements the reviewed replacement defaults and records them in [m5-jobs-notes-media.md](m5-jobs-notes-media.md); it does not claim to have recovered absent legacy handlers. Focused M5 plus ownership tests pass 9/207; the final suite passes 75/974. No legacy/production file or source SQL byte changed.
