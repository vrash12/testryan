# M7 upload usability and reliability — 2026-10-02

This local continuation adds selected-file previews to contractor documents and job media, and per-file progress, partial success and safe retry to job media for both roles. Production, legacy source and the SQL export are unchanged.

## User workflow

- Choose JPEG, PNG or PDF files, each up to 10 MiB. Job selections allow at most ten files; documents remain a single-file form. Image thumbnails, filenames and sizes appear before submission. Remove unwanted files without uploading them. HEIC/HEIF photos receive guidance to export as JPEG; no silent conversion occurs.
- Job media sends one file at a time. Each row distinguishes transfer progress from server-confirmed saving. Successful files stay saved if another file fails validation. Rejected files can be removed and replaced.
- A connection failure or timeout leaves the result explicitly unconfirmed and pauses the queue. **Upload / retry remaining** resends unconfirmed files using their original reference and tag, skips saved files, and then continues waiting files. Keep the page open to preserve these references. Refresh after completion to see the saved media list.
- Selection references and private preview URLs live in page memory only. Refreshing or closing loses the selection; this is not a background/resumable upload service. Check saved media before selecting the same file again after a reload. Identical bytes intentionally selected with a new reference are a new upload.
- Documents still use the normal validated form submission. JavaScript-disabled browsers retain the existing atomic job batch POST and redirect; per-file partial results and safe browser retry require JavaScript and a secure context supporting UUID generation.

## Server guarantees

`JobMediaRequest` requires a UUID `upload_id` for JSON requests and validates the optional UUID `upload_group`. Existing authorization, approval/verification, acceptance/completion, detected MIME, size and mutation-throttle rules still apply. JSON responses contain only the saved media IDs and a generic confirmation, with private/no-store caching.

Migration `2026_10_02_000008_create_media_upload_receipts` adds `app_media_upload_receipts`. The primary key hashes actor + job + upload UUID; the fingerprint covers tag, notification group, original names and file bytes. A transaction inserts/locks the receipt and current job, saves private files and legacy-compatible BLOB/metadata rows, and records resulting media IDs. Retrying an identical committed request returns the original IDs without another file, activity entry or notification. A changed fingerprint or deleted saved media returns 409. Failed storage rolls back the receipt and database writes, allowing a safe retry. Ownership and current job state are rechecked before replaying a result.

Each rendered job-upload form has one notification group. Its first successful file creates the generic media notification; further files and retries in that form share it. It makes no claim about a final batch count. Each file still gets its own audit entry and receipt. Normal non-JavaScript batches retain one notification per successful batch. Recipient eligibility, preferences, opt-in delivery and existing throttles remain in force.

Receipt retention currently follows the uploading user (cascade on actual user deletion); job/media deletion preserves the receipt so replay cannot recreate deleted media. No automatic receipt purge is introduced. A future retention policy must consider how long a browser might retry an old request.

## Local runtime

The local MariaDB and port 8011 development server were offline at the start of verification and were restarted. The new migration was applied forward to `nestcrafters_local`; it does not reset/import domain data. MariaDB's restart reset `max_allowed_packet` to 1 MiB, so the previously documented local-only 32 MiB runtime setting was restored. It remains non-persistent across database restarts; configure this in the eventual hosting checklist. Local PHP reports 40M upload and POST limits.

## Verification boundaries

Feature coverage is in `MediaUploadRetryTest` (receipt replay/conflict, approval/assignment/acceptance/deletion, partial success, rollback/retry, notification grouping, admin and normal-form compatibility). `tests/js/uploads.test.js` covers selection boundaries and safe failure messages. Existing M4/M5 and business-notification regressions are also run sequentially against guarded disposable schemas.

Native iPhone/Android camera selection, Safari file handling, interrupted-network behavior on real devices, accessibility with assistive technology, and client acceptance remain M7 work. No external email or production rollout is authorized by this change.

### Executed checks

- **18 feature tests / 296 assertions:** MediaUploadRetryTest, M4ContractorWorkflowTest and M5JobWorkflowTest. **13 notification regression tests / 187 assertions:** M6NotificationsTest. Total: **31 tests / 483 assertions**, sequentially, no skips. Two JavaScript tests passed. Vite production build, Pint, Blade compilation, PHP/JavaScript syntax and main local health (HTTP 200) passed. Later copy-only validation labels and preview cleanup were checked by syntax/build and browser; no full-suite rerun is claimed for this continuation.
- The first attempts found local services offline. After restart, one new rollback test exposed a test-double restoration error, which was fixed before the passing run. These failures did not alter imported records.
- On the disposable 8013 review app, an approved contractor selected a real synthetic PNG, a fake PNG and HEIC. Preview/removal and HEIC guidance worked; the server rejected the fake image and retained the successful PNG. After choosing a PDF, stopping only the isolated review server produced an explicit unconfirmed result. Restarting it and clicking retry saved the PDF without resending the PNG. Refresh showed three media rows: one pre-existing fixture and exactly two new uploads. Database checks confirmed two receipts and one grouped media notification event.
- Document selection was single-file. HEIC submission stayed on the page with an actionable message and enabled submit button. Removing it and choosing a PNG showed the preview; normal submission as Other returned the private-storage confirmation and a fifth document in the disposable fixture.
- Desktop and effective **477 CSS px** checks found no horizontal overflow for the job upload and document pages. Browser zoom prevented treating the requested 320 px override as an exact 320 CSS px test. The temporary viewport override was reset. Screenshot: [upload selection](upload-selection-review.png). The fixture's original one-pixel media thumbnail did not render in the final screenshot; the newly uploaded synthetic PNG did. Native-device and full viewport-matrix acceptance remain open.
- The SQL export SHA-256 still matches `3ebef325d80b514f900f462673ba40398365fbbcdb4162c20b00771469495fc5`. No legacy file was edited. The main database received only the additive receipt migration; all upload mutations were confined to disposable test/review data.
