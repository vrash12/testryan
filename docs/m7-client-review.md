# M7 client review checklist

Updated: 2026-10-01 (Asia/Shanghai).

This is the client-facing review gate for the local Laravel application after M6. It covers an authenticated admin, an approved contractor, and a pending contractor using synthetic local accounts. It does not authorize production access, deployment, external mail, scheduler/worker setup, or changes to the legacy files or SQL export.

## How to record the review

Use a fresh local session for each role. Record the viewport, browser/device, route name, observed result, and a screenshot only when it contains synthetic data. A checkpoint passes only when every required result in its row is visible and the server response also enforces the rule. Mark a row **blocked** when a prerequisite account, file picker, mail capture, or device is unavailable; do not convert a blocked row into a pass based on an HTTP status alone.

The M6 test suite and build checks cover the automated items listed below. The executed local review is recorded near the end of this document; rows that require a native device or an exact viewport remain explicitly blocked.

## Test identities and safety

The reviewer should use three disposable local accounts prepared by the maintainer:

- **Admin:** active administrator with verified email; has access to the admin shell and synthetic contractor records.
- **Approved contractor:** active, verified, approval `1`; owns at least one open assigned job, one accepted job for media testing, one W9, one Insurance file, and one job note/media record.
- **Pending contractor:** active, verified or unverified, approval `0` or `2`; should see account status and email verification only. It must not see profile editing, services, documents, jobs, notes, or media actions.

Do not use imported production-like contacts, real mailboxes, real document contents, or real credentials. Reset the synthetic database between destructive checks, or use a new synthetic record. Business mail is disabled by default; if a local capture is deliberately enabled, inspect only synthetic messages and then disable it.

## Checkpoint A — shared shell and responsive behavior

Run at 320, 375, 390, 430, 768, and 1280 CSS pixels. Test with keyboard focus as well as a pointer.

1. Open `home`, `login`, and `health`. Confirm readable text, visible focus, no horizontal scrolling, and useful validation/error states.
2. Sign in through `login`; confirm the role redirect is `admin.dashboard` or `contractor.dashboard` and the session does not expose the other role's navigation.
3. Open `dashboard`, `account.status`, `verification.notice`, and `logout` where applicable. Confirm a pending account can reach status/verification without gaining work access.
4. Expand and close the mobile navigation. Confirm Escape/keyboard focus behavior, no dead links, and links stay in the current role's route family.

**Pass:** all layouts remain usable at the six widths, links resolve to the route keys below, and role/session gates hold after refresh. **Fail:** overflow hides controls, an unhandled exception appears, a dead route is shown as available, or a role can reach another role's data.

## Checkpoint B — admin review and management

Sign in as the admin and use only synthetic records.

1. Open `admin.dashboard`. Confirm cards distinguish approval `0` (new/not approved) from approval `2` (pending admin review), open job states, overdue/upcoming dates, missing W9, and expiring/expired Insurance. Follow each card's link and confirm the filter is retained.
2. Open `admin.review.index`. Search by name, company, email/phone, approval state, priority, and one or more services. Selecting two services must match contractors offering **all** selected services. Confirm blocked and archived accounts are absent from the active review queue.
3. Open a review card and confirm contact, email verification, services, W9/Insurance metadata, and escaped private registration indicators. The indicators are review context; they do not delete or auto-reject a person.
4. Select one or more accounts, choose an action, tick the confirmation, and submit `admin.review.bulk`. Verify approve (`1`), reject/set not approved (`0`), and archive each write an audit entry. Archive must retain jobs, files, services, and account history.
5. Repeat with a stale, missing, duplicate, admin, blocked, archived, or already-approved ID. Confirm the entire selection is refused and no selected account is partially changed.
6. Open `admin.contractors.index`. Check search, approval, priority, include-archived, missing-active-W9, and ALL-service filters. Open `admin.contractors.print`; confirm the filter summary and first-1,000 cap state the full matching total.
7. Open `admin.services.index` and exercise search, create, rename, and safe delete. A service referenced by either contractor or job must not be deletable.
8. Open `admin.documents.index`. Filter active/archived, type, expiring, and expired documents. Confirm preview/download links require an authenticated admin and never reveal a storage path.
9. Open `admin.templates.index`, create/edit a Jobs template containing allowed formatting plus a script/event handler, preview it, and insert it from the admin job form. Confirm unsafe markup is removed in preview/insertion while the original editor value remains escaped and stored.
10. Open `admin.activity.index` and `admin.activity.print`. Filter by account, action text, and inclusive date range. Confirm associated orphan log rows remain readable, print output states its row cap, and no edit/delete control exists.

**Pass:** every mutation has visible feedback and a corresponding scoped audit result; filters and print output agree; private fields and files stay admin-only. **Fail:** a stale bulk selection partially commits, a blocked/archived account appears in active review, a service deletion breaks a pivot, or raw HTML/private paths reach the page.

### Admin route keys

`admin.dashboard`, `admin.review.index`, `admin.review.bulk`, `admin.contractors.index`, `admin.contractors.show`, `admin.contractors.edit`, `admin.contractors.approval`, `admin.contractors.archive`, `admin.contractors.restore`, `admin.contractors.print`, `admin.contractors.documents.index`, `admin.documents.index`, `admin.services.index`, `admin.services.create`, `admin.services.edit`, `admin.templates.index`, `admin.templates.create`, `admin.templates.edit`, `admin.templates.content`, `admin.activity.index`, `admin.activity.print`, `admin.jobs.index`, `admin.jobs.create`, `admin.jobs.show`, `admin.jobs.edit`, `admin.jobs.assignment`, `admin.jobs.acceptance`, `admin.jobs.completion`, `admin.jobs.notes.store`, `admin.jobs.media.store`, `admin.jobs.print`, and `admin.accounts.index`.

## Checkpoint C — approved contractor workflow

Sign in as the approved contractor and confirm the current user ID is used for every request.

1. Open `contractor.dashboard` and `contractor.jobs.index`. Confirm only assigned jobs appear, totals are not limited-card counts, and overdue/upcoming filters use calendar dates.
2. Open `contractor.jobs.show` and `contractor.jobs.print`. Confirm the job name, description, terms, services, price/deposit/material responsibility, notes, and media are readable without exposing another job.
3. Accept an assigned pending job through `contractor.jobs.acceptance`. Refresh and repeat the request; the acceptance timestamp remains stable and an activity/transition record is not duplicated.
4. Add a shared note through `contractor.jobs.notes.store`. Confirm it is append-only, escaped/sanitized, visible to the current authorized assignee and admin, and unavailable on another contractor's job.
5. Upload JPEG, PNG, and PDF media through `contractor.jobs.media.store` after acceptance. Try a 10 MB-plus file, a renamed executable, and an unaccepted job. Confirm MIME/size/acceptance checks reject them without a private path in the response.
6. Open `job-media.preview`, `job-media.download`, and `job-media.thumbnail`. Confirm inline/download headers are safe, PDFs have no thumbnail, and another contractor cannot access the same media ID.
7. Open `contractor.profile.edit` and `contractor.documents.index`. Change a profile/service/photo field and confirm approval returns to state `2`; upload W9/Insurance with optional expiry and confirm private preview/download ownership. Document-only changes should follow the current local default and must be noted if product review changes it.
8. Complete/reopen/reassign checks are admin-only. After an admin reassigns a job, confirm the former contractor loses current access and acceptance/completion state resets according to the transition history.

**Pass:** all displayed records belong to the current contractor, accepted media works, unaccepted/completed writes are refused, and profile/service changes enter review. **Fail:** an ID-only URL reveals another user's job/file, media bypasses acceptance, or a completed contractor job remains writable.

### Approved contractor route keys

`contractor.dashboard`, `contractor.jobs.index`, `contractor.jobs.show`, `contractor.jobs.acceptance`, `contractor.jobs.notes.store`, `contractor.jobs.media.store`, `contractor.jobs.print`, `contractor.profile.edit`, `contractor.profile.update`, `contractor.profile.photo.destroy`, `contractor.documents.index`, `documents.preview`, `documents.download`, `job-media.preview`, `job-media.download`, and `job-media.thumbnail`.

## Checkpoint D — pending contractor workflow

Sign in as the pending contractor (test both approval `0` and `2` if available).

1. Open `contractor.dashboard` or `dashboard`. Confirm the page explains approval and email verification state with the next action.
2. Open `account.status`, `verification.notice`, and `verification.send`. Confirm a pending user can request verification within its throttle and receives no work link.
3. Attempt `contractor.jobs.index`, `contractor.profile.edit`, `contractor.documents.index`, `documents.download`, and `job-media.download` directly and by changing IDs. Confirm denial/redirect without record existence leakage.
4. After verification alone, retry the protected routes. Confirm verification does not grant work access until approval `1`. After approval alone, confirm an unverified account still cannot work.

**Pass:** pending users see only status/verification/logout and safe navigation. **Fail:** any pending account can edit a profile, view a job/document/media, upload, or infer another user's record from an error.

### Pending route keys

`dashboard`, `account.status`, `verification.notice`, `verification.send`, `verification.verify`, `logout`; protected work keys must remain denied until both current active lifecycle, verified email, and approval `1` are true.

## Checkpoint E — browser/device and file handling

These checks are manual and are not replaced by HTTP feature tests.

- Use a real or emulated phone to open the six widths, mobile menu, keyboard focus order, back/refresh, and a slow connection. Confirm cards and forms do not require horizontal scrolling.
- Use the native camera/file picker where available. Select JPEG, PNG, PDF, an oversized file, a renamed executable, and a filename with Unicode/path characters. Confirm the visible error is useful and the server repeats the check.
- Open an uploaded image, PDF, and generated thumbnail on phone and desktop. Confirm orientation, fit, download filename, inline headers, and no public URL or cache leak.
- Test a job with long rich text, a note with `<script>`/event attributes, and a template with unsafe URLs. Confirm visible output is sanitized and text remains accessible.
- Test keyboard-only modal/menu/form submission, screen zoom, reduced motion, and screen-reader labels for checkboxes, file controls, errors, and status badges.

**Pass:** the same ownership, MIME, size, sanitization, and CSRF result holds through native controls. **Fail:** a device-specific path bypasses validation, content is clipped/inaccessible, or a private response is cacheable/public.

## Automated evidence already complete

The recorded M6 verification reports 110 tests and 1,392 assertions with no failures or skips, plus Pint, Composer/platform checks, Blade cache, PHP syntax checks, the Vite build, route inventory, UTF-8 checks, loopback health, preview, private-path and environment-path checks. M6 tests cover management, dashboards, review atomicity, job/document permission rechecks, notification switches/rollback/retry/privacy, expiry boundaries/deduplication, and rich HTML sanitization. These checks are local and synthetic; they do not prove real-device rendering, real SMTP delivery, or production behavior.

## Decisions requiring client review before the next implementation slice

The following are grouped so a decision closes a dependent workflow. D1 is already selected; it is recorded for context.

### Critical access and job behavior — D2, D3, D4

- **D2 — Notes:** Are contractor Job_Notes allowed before acceptance, after completion, or both? Are contractor notes append-only forever? Should account `Notes` remain admin-private? The current local default allows append-only shared notes on an assigned open job before acceptance, blocks contractor writes after completion, and allows the current assignee to read shared history.
- **D3 — Jobs/evidence:** Which edits (terms, description, services, price, deposit, materials, due date) require new acceptance? Should reopening retain acceptance for the same assignee? Should a completed job be reopened before any reassignment? The current local default is admin-only completion/reopen, reset on reassignment, retained transition history, and explicit admin acceptance on behalf.
- **D4 — Eligibility:** Are service matches suggestions only, may an admin assign outside them, and must assignment require approval `1` plus active account? The current local default preserves ANY-service suggestions, ALL-service directory filters, and approved active assignment with an explicit logged override if later added.

**Client checkpoint:** write the accepted rule for each before changing contractor job permissions or assignment UI.

### Data, files, and retention — D5, D6

- **D5 — Uploads/review:** Confirm document MIME/cap (current proposed PDF/JPEG/PNG ≤5 MiB), photo MIME/cap/dimension rules, GIF support, phone formatting, and whether replacing a document or changing a document expiry triggers review. Job media remains JPEG/PNG/PDF ≤10 MiB.
- **D6 — Retention/visibility:** Confirm archive versus permanent deletion, purge/retention period, document replacement behavior, and whether a former assignee may read historical notes/media. The local default archives accounts, preserves history/orphan associations, and protects imported files from hard deletion.

**Client checkpoint:** attach one accepted example for each file type and one retention rule for account, document, job, note, and media records.

### Mail, abuse, and account operations — D7, D8

- **D7 — Notifications:** Confirm recipients for registration, re-review, approval state, assignment/reassignment, job changes, acceptance, completion, notes/media, and expiry. Choose old/new assignee behavior, admin-authored note/media recipients, reminder offsets/digests, deduplication, sender, and business timezone. The master switch is off locally; local/testing rejects external transports.
- **D8 — Accounts/abuse:** Confirm bot provider and accessibility fallback, numeric throttle limits, signal retention, duplicate-company thresholds, admin recovery/MFA, nullable-email managed contacts, and imported-account verification rollout. No heuristic automatically deletes an account.

**Client checkpoint:** approve a recipient/settings table and a retention/provider owner before enabling any external transport or scheduler.

### Money, content, and release prerequisites — D9, D10

- **D9 — Money/dates/content:** Confirm currency, business timezone, whether a deposit requires an approved price, zero versus NULL semantics, source encoding conversion, and the allowed rich-HTML elements/URL schemes for jobs and templates. The local date comparisons currently use provisional UTC and preserve original HTML alongside sanitized rendering.
- **D10 — Readiness/hosting:** Confirm the complete source/export recovery path, supported runtime, public-root/subpath, private storage, queue/worker, scheduler, SMTP, backups, and credential rotation requirements. This checklist deliberately does not authorize or test deployment.

**Client checkpoint:** record the owner and evidence for each fact; do not mark a release gate complete from a local preview.

## Review outcome

At the end of the session, record each checkpoint as **pass**, **fail**, or **blocked**, attach synthetic evidence, and list every unresolved D2–D10 answer. A passing M7 client review confirms local usability and the agreed behavior for the tested paths. It does not accept production rollout, external mail, imported-account activation, or unresolved source/business decisions.

## Observed M7 local run — 2026-10-01 (Asia/Shanghai)

The guarded disposable fixture was prepared with `php artisan m7:prepare-review` and served from `http://127.0.0.1:8013` using `scripts/serve-m7-review.php`. The server used the isolated `m2_test` schema and a separate session cookie. The populated `nestcrafters_local` target, legacy files, SQL export, and production were not accessed for mutation. Health returned `200` with `status=ok`; a direct `/.env` request returned `404`.

At the default in-app browser viewport, the following manual rows passed with synthetic accounts:

- **A — shared shell/auth:** login, registration service search, password visibility, recovery, role redirects, accessible labels, and error/status structure rendered. The admin and contractor menus exposed only their role destinations. The pending account reached status/verification and sign-out only.
- **B — admin:** the dashboard showed real contractor/job/document metrics and urgency links; the review queue showed approval/verification, W9/Insurance, services, explicit bulk confirmation, and escaped private registration indicators. The role menu exposed Review queue, Contractors, Jobs, Services, Documents, Templates, Activity, Account access, and Account status.
- **C — approved contractor:** the dashboard showed assigned-job/document metrics; a long-title pending job wrapped correctly and displayed terms, sanitized content, services, financial values, notes, and media rules. Accepting the disposable job changed it to Accepted and revealed the tagged private-media controls with the 10 MB helper. The mutation is limited to `m2_test` and can be reset with the fixture command.
- **D — pending contractor:** the account-status page showed verified email plus “Awaiting review” and sign-out only. A direct visit to `/contractor/jobs` returned the protected 403 page without job data leakage.

Automated M6 evidence remains valid for the required feature tests, build, import reconciliation, private-path checks, and route/view validation: **110 tests / 1,392 assertions**, no failures or skips. This local run did not claim external mail delivery or production behavior.

The following rows remain **blocked/pending**, rather than passed: exact 320/375/390/430/768/1280 viewport matrix, native phone/file-picker/camera/PDF handling, Safari/Android checks, and client sign-off on D2–D10. The available in-app browser did not provide viewport/device emulation controls for this run, and no external device or mailbox was used.

## Upload continuation — 2026-10-02

The selected-file preview/removal, HEIC guidance, partial-success job uploads and explicit unconfirmed retry path were exercised through the in-app browser on the disposable fixture. Two files committed with two receipts and one grouped event; normal document submission also succeeded. No horizontal overflow at desktop/effective 477 CSS px; viewport reset. Exact 320 px and native-device acceptance remain pending. See [upload reliability evidence](m7-upload-reliability.md) for checks, screenshot and remaining limits.

## Notification inbox continuation — 2026-10-02

The notification page now offers status counts, update-type/date filters, current-page selection, bulk read/unread actions and individual reversible read state. Browser fixture review passed at the default viewport; timestamps remained unchanged after bulk and individual state changes. No private payload text was rendered. The 320 px check was interrupted by the browser runtime reset and is not claimed. See [notification inbox management](notification-inbox-management.md) for behavior and test evidence.

## Branding/document verification continuation — 2026-10-02

The database blocker is resolved. New logo placement, narrow/tablet/desktop document layouts, skip-link/form focus, synthetic image upload and authenticated PDF download have now been checked. The PDF fixture was repaired and independently rendered; in-app PDF viewing remains unverified. Focused regression passed 31 tests / 407 assertions plus 2 JavaScript checks. See [exact evidence and remaining checks](m7-branding-verification.md); M7 remains in progress for native devices, wider accessibility coverage and client acceptance.

