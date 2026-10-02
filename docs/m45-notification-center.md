# M4.5 local notification center and review controls

Implemented 2026-10-01 after the user requested continuation of unfinished features. This extends the M3/M6 event outbox and preserves production, legacy files, imported passwords, and existing role gates.

## Delivered behavior

- Admins and approved, verified contractors have a paginated notification inbox, unread filter, individual mark-read actions and header unread count. Reads are scoped to the authenticated recipient; another recipient's ID returns 404. Pending contractors retain account status and verification only.
- Inbox messages contain generic event labels and authenticated links. Job links recheck current assignment, role, approval and visibility. Deleted/unavailable records have no link. Verification/recovery tokens and private message bodies never enter the inbox. There is no historical backfill.
- Per-user email preferences cover only the event types relevant to that role. Preferences are checked both at event creation and before delivery/retry. Security verification and password recovery are not optional. Turning off mail leaves inbox records intact.
- Verified admins can inspect paginated failed/pending/delivered/skipped delivery metadata. The page omits recipient email addresses, encrypted payloads, tokens and exception details. A confirmed retry targets one failed delivery, is throttled, records an audit entry, dispatches after commit and rechecks current eligibility. Delivered means transport handoff, not mailbox receipt; ambiguous SMTP failures may produce duplicates when retried.
- Admins can block/unblock contractors with a required private reason and explicit confirmation. Blocking preserves records, revokes sessions and prevents login through existing account gates. Unblocking sets approval to pending admin review and does not clear an archive. Admin/self targets are prohibited. Blocked accounts are discoverable with the directory's Include blocked accounts filter.
- Bulk review supports block alongside approve/not-approved/archive. All 1–50 explicitly selected accounts must still be active pending contractors. Any invalid/stale selection cancels the whole transaction before mutation.

## Routes

| Method | Route | Access |
| --- | --- | --- |
| GET | `/notifications` | Own inbox; verified admin or approved contractor |
| POST | `/notifications/{notification}/read` | Own inbox item only |
| GET / PUT | `/notifications/preferences` | Own role-specific email preferences |
| GET | `/admin/notification-deliveries` | Verified admin |
| POST | `/admin/notification-deliveries/{delivery}/retry` | Verified admin; failed delivery plus confirmation |
| POST | `/admin/contractors/{user}/block` | Verified admin; contractor target plus reason/confirmation |
| POST | `/admin/review` | Existing verified-admin route; new `block` action |

## Storage and runtime configuration

Migration `2026_10_01_000007_create_notification_center_tables` adds two tables without changing legacy definitions: `app_notification_preferences` (user/event composite primary key) and `app_notification_inbox` (ULID, event/recipient uniqueness, timestamps). Both recipient foreign keys use the signed legacy Users key. Inbox event deletion and user deletion cascade to the new records; the existing guarded contractor-deletion workflow still governs domain deletion. The local target now has 23 tables.

`NESTCRAFTERS_NOTIFICATION_INBOX=true` is enabled only in the current local environment and offered in `.env.example`. Business email remains separately disabled with `NESTCRAFTERS_BUSINESS_NOTIFICATIONS=false`. Event-specific switches still control event creation. Local/testing transports remain restricted to log/array. No external messages, worker or system scheduler were enabled. The PHPUnit configuration explicitly disables the local inbox default; new tests enable it as needed against disposable schemas.

## Insurance reminder stages

`php artisan nestcrafters:notify-document-expiry --staged` records the most urgent current stage: 30-day (8–30 days ahead), 7-day (1–7 days), due today, or recently expired (1–30 days overdue). Each document/expiry-date/stage is recorded at most once. It does not backfill skipped earlier stages or repeat daily. The original command without `--staged` keeps its inclusive 0–30-day behavior; its key is shared with the 30-day stage. Choose one command mode for an eventual scheduler, rather than alternating modes. The command was tested against synthetic data, not run against imported local accounts.

Recipients remain active verified administrators. Owners, archived files, W9s, inactive contractor owners and out-of-window dates follow the existing exclusions. Delivery checks the current file type, expiry date, active owner and reminder window again. Dates use the configured application timezone. `--date` still accepts only today's date. Production timezone, recipients and scheduler installation remain review decisions.

## Verification and remaining boundaries

The first focused run passed 36 tests / 430 assertions covering inbox ownership, stale-job links, pending/unverified gates, preferences before creation/queued delivery, security-message isolation, transaction rollback, failure retry controls, blocking and bulk review plus existing notification regressions. Build, Blade and route compilation passed; full regression results are recorded in the session handoff after completion.

The actual local contractor inbox/preferences were inspected at desktop and effective 358 px narrow browser width without horizontal overflow (the browser's current zoom changes requested viewport sizes). The temporary viewport was reset. Screenshot: `docs/notification-center-preview.png`. These checks do not constitute native phone/PDF/file-picker acceptance or authenticated admin visual acceptance.

Remaining release work: business timezone/currency and retention decisions, live bot-provider configuration, SMTP/queue/scheduler hosting, external delivery proof, full native-device/file handling checks, source completeness, backup/rollback and explicitly approved deployment. Domain/IP blocklists, automatic spam rejection and automatic inbox purging are not introduced by the account-block control.

Final continuation verification: 120 tests / 1,508 assertions passed sequentially with no failures or skips. Pint, Vite build, Blade and route compilation passed; local health returned HTTP 200. All 60 legacy file hashes and the SQL export digest remain unchanged. The intermediate full run used an already-loaded outdated retry-job assertion; the final settled-file rerun passed. No production action or external email delivery occurred.
