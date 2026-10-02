# Notification inbox management — 2026-10-02

The local notification center now supports All/Unread/Read views with inbox-wide counts, event-type filters, and inclusive arrival-date ranges. Dates are explicitly labeled with the application's configured timezone (currently provisional UTC). Pagination preserves filters. The old `?unread=1` link still works; an explicit `status` takes precedence.

Users can mark individual notifications read or unread, or check notifications and apply the action to a selection. The optional JavaScript Select this page/Clear selection control operates on the twenty visible records and announces the count. Individual checkboxes and the bulk form work without JavaScript. Selections do not carry across pages. Changing read status returns to the first filtered page so a later page emptied by the change is not mistaken for an empty inbox.

`POST /notifications/bulk` validates a read/unread action and 1–50 distinct ULIDs. It queries only the signed-in recipient's rows, locks them in a transaction, and rejects the whole request with 404 if any selected item is missing or belongs to another account. This restriction also applies to administrators: administering the system does not grant access to another person's inbox. Requests remain authenticated, verified, current-account checked, approval gated and rate limited. Pending contractors retain status/verification access only.

Read actions update only unread rows, preserving an existing read timestamp. Unread clears that timestamp. Neither action creates new events, delivery attempts or emails. Links still recheck current resource access; event payloads and private record details are not searchable or exposed. Status counts include only the recipient's own inbox, independently of the active type/date filter. Filter options are the fixed generic event labels, not data from other users.

## Timestamp correction

Browser review revealed that the original first TIMESTAMP column on MariaDB had implicitly acquired `ON UPDATE CURRENT_TIMESTAMP`. Reading a notification therefore changed its arrival date and sort order. Migration `2026_10_02_000009_preserve_notification_arrival_times` explicitly defaults `created_at` to CURRENT_TIMESTAMP without automatic updates. It preserves existing stored values and does not guess dates that may already have changed. The older single-read endpoint receives the same protection.

## Review fixture

`m7:prepare-review` now includes five synthetic inbox items across the isolated admin and approved contractor accounts, with both read and unread examples. It directly creates encrypted event/inbox records without delivery records or mail. Its existing local schema guards remain in place. Do not prepare or browse this fixture while the database feature tests are running: both use the disposable `m2_test` schema.

Validation results are recorded in TESTING.md and session-handoff.md. Production, legacy files and the SQL export remain unchanged. Main local records are not reset or re-imported; only the forward timestamp migration is applied there.
