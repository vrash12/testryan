# Permissions, notifications, and registration protection

Audited: 2026-10-01 (Asia/Shanghai). This separates observed source access from required target permissions. Proposed choices marked D1–D10 refer to [review decisions](legacy-audit.md#decisions-for-review). The original audit did not test notification transport or authenticated production workflows; later local implementation evidence is identified separately below.

Current local implementation: [M3 identity/permissions](m3-identity-permissions.md), [M4 contractor workflows](m4-contractor-workflows.md), [M5 job workflows](m5-jobs-notes-media.md), and [M6 management/dashboards](m6-management-dashboards.md). The user selected pending account status/email verification only; protected contractor resources require current active, verified, approved ownership. M4–M6 implement the contractor/job/file workflows, management pages, safe review and opt-in business mail described below. Identity and business mail have local capture/retry tests. Production delivery, recipient acceptance and the remaining M4.5 features are unresolved.

## Permission matrix

The matrix preserves the original audit and target requirements, including proposals still marked D1–D10; it is not a claim that every proposed feature is implemented or accepted. Current implementation boundaries are recorded in the milestone reports and the M6 section below. For the target, `own` means the current authenticated user's record or a job whose current contractor_id equals that user. Role/approval are read from the database for every request; route binding alone is not a policy. Child records must belong to their route parent as well as the user's authorized job/profile. Default deny for unlisted operations. An admin cannot use contractor endpoints merely by posting an ID; admin workflows use separate routes/actions.

| Resource/action | Legacy admin | Legacy contractor | Target admin | Target approved, verified contractor | Target unapproved/unverified contractor |
| --- | --- | --- | --- | --- | --- |
| Login/status/logout | Login, dashboard | Login regardless of approval | Verified role redirect + current-session protection | Own dashboard | Own status/verification/logout only; selected D1 |
| Public registration | Public endpoint available to guest | Guest creates role=0/state=0 | No public role elevation | Guest registration only | Verification/review pending; cannot self-approve |
| Directory/search, contact details, print | All | AJAX leaks directory to guest; pages otherwise admin | All with admin gate | Deny | Deny |
| Create/edit contractors | All via admin pages | Profile own only; some action branches unsafe | Managed contacts/accounts per D8 | Own profile/services only, returns state=2 | Deny; no pending profile onboarding |
| Approve/priority | All | Unsafe admin_action permits arbitrary targets | All authorized contractor targets | Deny | Deny |
| Password provisioning/reset | All ≥8 via explicit admin branch | No recovery/change handler | Current admin password, actor audit and session revocation | Secure token recovery implemented | Narrow verification/recovery exception; no work access |
| Delete/archive/block accounts | Hard delete guarded; any assigned job prevents delete | Deny | Policy-controlled lifecycle/retention D6; confirm bulk targets | Deny | Deny |
| View profile photo | Any user ID | Any user ID via shared admin_action | All | Own only unless broader access accepted | Deny |
| Upload/remove photo | Admin update + delete | Own; alternate paths disagree on review/limit | All managed contractors | Own; review behavior D5 | Deny |
| Select services | Any managed contractor | Own profile path; update_services can mutate arbitrary user ID | All | Own, re-review | Deny after sign-in; guest initial selection allowed |
| Manage service catalog | All | Unsafe admin_action add/edit | All; deletion restricts jobs and contractors | Read selectable options only | Deny after sign-in; guest registration reads service names |
| Contractor documents read/download | All | Own enforced preview | All | Own | Deny |
| Contractor documents upload | All, no size ceiling | Own, 5 MiB | All, agreed MIME/cap/date rules D5 | Own | Deny |
| Archive/delete/replace documents | Admin UI, unsafe action branches | No intended UI; arbitrary actions exposed through admin_action | All within retention D6 | Deny archive/delete unless deliberately enabled D6; replacement proposed | Deny |
| Account Notes read/create | All; authored by admin | No account Notes UI; unsafe add_note can forge write | Admin-only recommended D2 | Deny | Deny |
| Jobs list/detail/print | All | Own assignment only; no approval gate | All | Own assignment only | Deny protected jobs per AGENTS.md |
| Job create/edit/assignment | All; eligibility inconsistent | Deny | All, validate contractor role/eligibility D4 | Deny | Deny |
| Accept job | Two admin branches plus create/edit | Own assigned unaccepted job, no approval/completion guard | On behalf, require valid assignment/transition | Own pending job; idempotent date/log | Deny |
| Complete/reopen/reassign | Edit/assign | No contractor handler | Admin-only recommended D3; history preserved | Deny | Deny |
| Job notes read | All | All notes on own job | All | Shared notes on own job; D2/D6 history decision | Deny |
| Job notes add/edit/delete | Add via two handlers; no edit/delete | Add UI points to missing handler | Add; editing/deletion not established, D2/D6 | Append to own job if D2 accepted; prerequisites unresolved | Deny |
| Job media preview/thumbnail/download | All, download file missing | Own assigned job; download missing | All with safe private response | Own assignment (acceptance needed to upload, not legacy read); D6 history | Deny |
| Job media upload | Any existing job, batch | Intended own accepted job; handler broken | Any existing job with enum/MIME/10 MiB checks | Own accepted job, 10 MiB/file; post-completion policy D3 | Deny |
| Job media delete | All scoped to parent job | No handler | All within retention D6 | Deny unless explicitly approved | Deny |
| Templates CRUD | All | Deny | All, sanitized HTML and unique names/types | Deny | Deny |
| Activity log read/mutate | Read all, no mutation UI | No log page | Read all; append through actions, no arbitrary edit/delete | Deny global logs | Deny |
| Notification configuration | No settings | No settings | Central enabled events/recipient rules; restricted delivery diagnostics | Own permitted preferences/inbox | Own verification/status communications only |
| Spam review/bulk actions | No dedicated feature | No feature | Review/approve/reject/archive/block with per-record policy/audit | Deny | Deny |

Ownership-safe read responses should avoid revealing another user's existence. Search/thumbnail/print/download endpoints require the same policy as detail pages. Public alphanumeric IDs and UUIDs are identifiers, not authorization. Private files must not be copied to a public disk or exposed by raw storage keys.

## State and permission boundaries

Approval names: `0 = Not approved / newly pending`, `1 = Approved`, `2 = Pending admin review`. Use an enum/constant mapping rather than boolean casts. The UI must explain 0 versus 2 consistently. Admin review decisions are separate from email verification. Approval does not imply verification, and verification does not imply approval. Archived/blocked accounts are separate lifecycle flags/states and must lose session/work access.

Jobs derive display state from assignment + accepted/completed flags; proposed order: completed, accepted, assigned pending, unassigned. Validate impossible combinations rather than hiding them behind a label. Preserve acceptance and completion timestamps separately; idempotent retries must not move them. Confirm D3 before implementing reassignment/reopening or allowing uploads/notes after completion. Admin-created immediate acceptance must set a timestamp and record admin actor evidence.

## Observed email behavior

The only recovered business call is `admin_update_contractor.php:245 → sendAdminNotification()`. It sends a synchronous HTML change summary to two hardcoded admin recipients, including no-change edits, before the final Users update. It logs SMTP failure privately but ignores the helper's returned failure, and mistakenly attributes the activity to the target contractor. Actual delivery is unverified because PHPMailer is missing locally.

`admin_assign_job.php:56` contains a commented `sendJobAssignmentNotification` placeholder. `includes/test_mail.php` sends a diagnostic message if executed, but it is not a workflow event. Registration, contractor self-edit, approval, acceptance, completion, notes/media, and expiry have no proven business mail call in the recovered snapshot. Email/Contractor templates are not wired to mail delivery. Template availability does not establish an event.

## M6 local implementation checkpoint

M6 adds the following local defaults to the earlier identity foundation. They implement requirements from AGENTS.md and the migration review; they do not turn proposed recipients, business rules or production mail settings into accepted decisions. Registration's existing administrator notice has its independent `REGISTRATION_ADMIN_MAIL` control. Verification and password recovery retain their narrow signed/token exceptions and are independent of the business master switch.

Business notification intent is disabled by default with `NESTCRAFTERS_BUSINESS_NOTIFICATIONS=false`. Each event also has an environment switch, defaulting to true behind that master switch. These environment values map to `config/notifications.php`; they are not per-user preferences or an admin settings screen.

| Implemented event | Trigger and eligible recipients | Environment switch |
| --- | --- | --- |
| `contractor.profile_changed` | Meaningful profile/services/photo change or photo removal → active verified admins excluding actor | `NOTIFY_CONTRACTOR_PROFILE_CHANGED` |
| `contractor.approval_changed` | Changed approval state, including 0 → active verified affected contractor; account-status link only | `NOTIFY_CONTRACTOR_APPROVAL_CHANGED` |
| `job.assigned` | New assignment or reassignment → current active verified assignee | `NOTIFY_JOB_ASSIGNED` |
| `job.updated` | Changed description/name/terms/date/price/deposit/material responsibility or selected services → current active verified assignee | `NOTIFY_JOB_UPDATED` |
| `job.accepted` | First valid acceptance, including admin on behalf → active verified admins excluding actor | `NOTIFY_JOB_ACCEPTED` |
| `job.completed` | Changed state to complete → active verified admins and assignee excluding actor | `NOTIFY_JOB_COMPLETED` |
| `job.note_added` | Committed shared job note → assignee for an admin action, admins for a contractor action; actor excluded | `NOTIFY_JOB_NOTE_ADDED` |
| `job.media_uploaded` | Committed upload batch → same counterpart rules as shared notes; one event per batch | `NOTIFY_JOB_MEDIA_UPLOADED` |
| `document.expiring` | Active Insurance expiring from today through day 30, inclusive, owned by an active contractor → active verified admins | `NOTIFY_DOCUMENT_EXPIRING` |

Unchanged approval, profile, assignment, job details, acceptance or completion does not create another intent. Reopening followed by completion is a new completion episode; ULIDs distinguish meaningful changes at the same instant. Unassignment, reopening itself, job/media deletion, contractor-document upload/replacement/archive, block/unblock, and owner expiry receipts have no business event in this implementation. Admin-private account Notes never become contractor mail. Templates of type Email/Contractor are manageable but are not notification bodies.

Workflow mutation, audit and encrypted `app_notification_events` intent commit together, with per-recipient `app_notification_deliveries` outcomes and dispatch after the outer transaction commits. Rollback retains neither intent nor queued work. A dispatch failure leaves pending work recoverable through `nestcrafters:retry-identity-mail`, which now processes both identity and business deliveries. Failed transport stores exception class and bounded retry timing privately; delivery addresses and error fields are hidden from ordinary serialization. No eligible recipient or a disabled event creates no intent or later backfill. An existing delivery that loses eligibility is marked skipped, rather than paused until permissions return.

Delivery rechecks the current address, active account, verification and event/master switches. Job mail requires the recipient's current job view policy, so reassignment, re-review and loss of approval revoke old contractor delivery. URLs use the recipient's current admin/contractor role. Profile review mail requires a current active contractor subject and an admin recipient. Approval mail permits the affected pending verified contractor to open account status and grants no work access. Mail uses generic text and authenticated links, with no private descriptions, terms, note text, document blobs or attachments. Local/testing only permits `log` or `array`; external transports are rejected before sending. A crash after transport handoff can still be ambiguous, so exactly-once SMTP delivery is not promised.

`nestcrafters:notify-document-expiry` records a single reminder per document/expiry-date pair. Archived files, non-Insurance types, null/expired dates, day 31 or later, administrator-owned files and inactive owners are excluded. Delivery rechecks type, owner, archive state, unchanged expiry and the current 0–30-day window. Optional `--date=YYYY-MM-DD` must be a valid calendar date equal to the current configured business date; future/past recording is refused before consuming a reminder key. Date calculations use the configured application timezone, provisionally UTC. No scheduler or external queue worker was enabled, and business mail remains disabled against imported accounts.

### Safe review delivered in M6

The active, verified admin-only `/admin/review` queue covers contractor states 0 and 2, excludes blocked/archived accounts, and supports search, approval/priority/service filters and pagination. Contact, services, document metadata and escaped private registration indicators help review; indicators do not automatically reject or delete a person. Bulk actions require 1–50 distinct explicit selected IDs and confirmation. The controller locks users/account states, checks every selected record's current role/activity/pending state and policy before mutating any, then records all changes in one transaction. A stale, missing or unauthorized selection changes none of the selected accounts.

Actions are approve, set not approved and archive. Set not approved maps to legacy state 0 with an optional audited reason; it does not create a separate permanent rejection state. Archive preserves records and relationships. Approval changes use the enabled event above; archive has no mail event. Existing archive/restore and blocked-account enforcement remain, but explicit block/unblock management controls are not implemented. Notification-producing profile/job/note/media/review mutations share an authenticated 30-per-minute write limit in addition to identity throttles.

### Remaining M4.5 and release scope

- Per-user email preferences, in-app inbox/history with unread state, and an admin delivery-error/status UI remain unimplemented. Durable private event/delivery tables are operational records, not a user inbox.
- Reminder stages at +7 days/day-of/expired, repeated reminders, owner recipients, other document types and scheduler installation remain decisions/work. Current expiry deduplication sends one enabled reminder per document and expiry date, rather than one per stage.
- Live bot-provider configuration, additional risk signals/retention, explicit block/unblock controls, reason semantics, recipient acceptance and hosting/mail/queue/timezone require implementation or review. Material-change acceptance reconfirmation and withdrawal/reopen/deletion notices are unresolved.
- M5 permits append-only shared notes on an assigned open job, contractor media only after acceptance, and admin completion/reopen/reassignment using documented local defaults. These defaults do not close D2–D10 or authorize production rollout. Pending contractors retain only the user's selected status/verification experience.

M6 notification tests cover local array-mail rendering, switches, meaningful/no-op transitions, rollback, stale recipient permissions, private failures/retries, external-transport rejection, shared-note/upload privacy and expiry boundaries/deduplication. Full checkpoint evidence belongs to [the M6 report](m6-management-dashboards.md); no external mail or production workflow was exercised.

## Required notification events

These requirements come from AGENTS.md and existing architecture/UI/testing guidance. The recipient/default columns are recommendations pending D7. Security verification/recovery messages should not be disabled through ordinary marketing/workflow preferences.

| Event | Trigger and recipient proposal | Record/link and delivery rule |
| --- | --- | --- |
| Registration submitted | After durable user/services save → enabled admin review recipients; contractor confirmation/status proposed | Review entry/status page; no private attachments or passwords |
| Email verification / email changed | Account created or usable email changed → that account's address | Expiring signed verification; throttle resend, invalidate prior proof for changed address |
| Profile/service review requested | Successful self-edit → enabled admin recipients; contractor status visible | Contractor review detail; coalesce duplicate/no-op updates per agreed policy |
| Approval granted/revoked/rejected | Meaningful reviewed transition → affected contractor; admin audit always | Status page; old/new state and reason safe for contractor; state 0 alone does not identify rejection |
| Job assigned/reassigned/unassigned | Committed assignment change → new contractor; decide withdrawal notice to prior contractor | Authorized job link; former contractor notice must not include ongoing confidential job data |
| Job changed | Material field/service/terms/date/financial change → current assignee | Diff/summary with sanitized content; decide fields/accepted-job reconfirmation and no-op suppression |
| Job accepted (self/admin behalf) | First valid committed acceptance → relevant admins; optional contractor receipt | Job link, actor and acceptance time; distinguish administrator action |
| Job completed/reopened | Committed admin transition → admins per requirement; contractor receipt recommended | Job link, time and actor; confirm reopening recipients |
| Job note added | Committed permitted note → admins/assigned contractor excluding actor as configured | Shared job link; account-private Notes must never be sent to contractor without visibility decision |
| Job media uploaded/deleted | Successful per-file/batch changes → relevant users excluding actor, as enabled | Job/media link; consolidate batch mail; never attach sensitive blobs by default |
| Contractor document uploaded/replaced/archived | Document metadata change → relevant admin/owner as enabled | Authorized document/profile link; scope independent from job-media event |
| Document expiry | Scheduler finds active Insurance or enabled other expiring documents | Start with +30-day reminder; decide +7/day-of/expired repeats, owner/admin recipients and timezone; exclude archived/NULL-expiry; deduplicate document/version/offset |
| Credential reset/account blocked | Security action → affected email account if configured safe; admin audit | Never send chosen password/hash, SMTP diagnostics, private review heuristics or direct storage paths |

Implementation requirements:

- Use domain events/actions independent of delivery. Commit resource updates, actor/subject audit and durable notification intent in the same InnoDB transaction; dispatch after commit and recover pending intents if immediate dispatch fails. After-commit callbacks alone do not close the gap between committed state and successful queue dispatch. An email error must not undo or falsely report a committed mutation. [Laravel queued notifications](https://laravel.com/framework/docs/11.x/notifications#queued-notifications-and-database-transactions).
- Central enabled events and recipient rules, plus allowed user email preferences; decide defaults before sending. Ordinary workflow delivery requires a usable eligible verified address and current permission. Initial verification, changed-address proof, secure invitation and password recovery must be able to reach an unverified usable address through narrowly scoped signed/tokenized, throttled messages; those flows grant no work access and cannot reactivate blocked/archived accounts. Handle nullable-email contacts without delivery attempts.
- Suppress duplicate intents using an event/recipient/channel key and track delivery attempts. A worker crash after an external SMTP send but before recording success can make the outcome ambiguous; deduplication and stable identifiers reduce retries but do not guarantee exactly-once mailbox delivery. Record uncertainty privately and define retry handling rather than claiming transactional external delivery.
- Record event/related IDs, deduplication key, attempted recipients, channel, queued/sent/failed/skipped state, retries and safe timestamps. Private delivery errors redact credentials and confidential payloads; restricted admins see actionable processing status only.
- Local development uses mail fake/sink and isolated queue/scheduler; import must suppress workflow mail and expiry backfill until deliberately enabled. Use retry/backoff, queue health monitoring and failed-job handling based on actual hosting support.
- Add in-app inbox/history with unread state and policy-checked links as required by UI guidance; database notification records must contain no paths/credentials. Permission is rechecked at link/open time after reassignment or account review.
- Escape email interpolation and sanitize rich content; keep attachments and private notes behind authenticated links. Prevent notification amplification with mutation/upload limits, batch aggregation and idempotent actions.

## Anti-spam and authentication requirements

Legacy registration has a duplicate-email query, minimum public password length, role=0/status=0 defaults, prepared inserts and phone digit stripping. Login uses `password_verify`, generic invalid-credential messages and session regeneration. There is no recovered registration/login throttle, CAPTCHA, honeypot, verification token, duplicate-company review, review risk metadata or account block/archive workflow. The portal must not adopt the unrelated `spl/` visitor filtering.

| Control | Required target behavior / review inputs |
| --- | --- |
| Login rate limits | Layer per normalized identifier and trusted client IP, bounded backoff, generic feedback, no account lockout an attacker can force indefinitely; audit repeated failures privately |
| Registration throttling | Per IP/identifier, global burst protection, bot challenge before creation; avoid duplicate requests producing duplicate accounts or admin mail |
| Bot control | Server-verified CAPTCHA or equivalent (honeypot/time/challenge design must be evaluated); mobile/accessibility fallback and useful retry; choose provider/data flow D8 |
| Verification | Signed expiring verification link, rate-limited resend, unverified email does not grant job access; email change revokes previous proof; imported accounts need rollout decision D1/D8 |
| Duplicate detection | Normalize trim/case and enforce email uniqueness under chosen collation; preserve raw legacy values. Company/contact similarities create review signals, not automatic rejection; do not apply provider-specific dot/plus equivalence globally |
| Suspicious registration indicators | Timestamp, selected services/profile completeness, duplicate matches, request velocity and suspicious input/domain signals; minimal IP/UA metadata if justified with defined retention. Explain flags to admin; no automatic deletion based on appearance |
| Review gate | Pending 0/2 excluded from protected contractor work. Review/Approve/Reject/Archive/Block have distinct audit reasons; legacy approval flag alone cannot encode all new lifecycle actions |
| Bulk review safety | Explicit selected records/action summary, confirmation for destructive operations, per-record authorization/eligibility recheck, idempotent execution and audited success/failure totals; preserve records/relationships |
| Other sensitive limits | Password recovery/reset/resend, uploads, note/media creation and events; requests cannot turn mail or BLOB handling into unbounded resource consumption |
| Secure auth/session | CSRF, session regeneration/invalidation, configured secure HttpOnly/SameSite cookies, fresh role/approval checks, password validation, escaped errors, safe return URLs; no passwords in rendered preserved form input/logs |
| Server validation | Real email syntax, bounded field lengths/types, valid service/contractor IDs, allowable enums/dates and upload MIME/content; client fields cannot set admin/approval/owner/actor IDs |

Use sanitized fixtures for suspicious inputs; keep exported contacts/hash values and operational logs out of tests/version control. Suspicious-looking source records justify review capability but do not prove an individual is malicious. Exact limits, bot provider, reasons, retention, bulk transaction granularity and admin account recovery/MFA remain D8 decisions.

## Technical-review status

[review.md](review.md) records the original technical recommendations, verification exceptions and remaining decisions. Subsequent user instructions authorized M3–M6 and selected D1 pending status/verification only. The milestone reports and M6 checkpoint above describe implemented local defaults; D2–D10 remain proposed where marked, and M4.5 is incomplete. A database queue must use a nonconflicting table such as app_queue_jobs; legacy Jobs remains reserved for contractor work. Production delivery and deployment remain separately unauthorized.

## Superseding M4.5 continuation — 2026-10-01

The earlier remaining-feature list is superseded by [the notification-center report](m45-notification-center.md): per-user email preferences, own inbox/unread state, admin delivery metadata/retry, reversible contractor block controls and optional staged Insurance reminders are now implemented locally. Pending contractors remain status/verification only. Unblocking requires new approval review. External mail, live bot-provider configuration, source completeness and deployment decisions remain open. Existing event recipient rules are preserved; this continuation does not add owner expiry receipts or domain/IP blocklists.
