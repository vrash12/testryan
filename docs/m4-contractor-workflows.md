# M4 contractor and document workflows

M4 is implemented in the isolated `laravel-app/` directory. The implementation keeps the legacy `Users`, `Services`, `ContractorServices`, `Files`, `Notes`, and `ActivityLogs` tables as the domain records and adds one private-upload metadata table for files created by the Laravel application. No legacy source, SQL export, or production system was changed.

## Delivered behavior

- Admins can search and filter contractors by name, company, email, approval state, priority, and archive state.
- Admins can create contractors in an unapproved state, edit their profile and service selection, change approval to not approved/approved/pending review, set priority, archive and restore an account, set a password through the existing account-access screen, and add private notes.
- Admins can permanently delete only a contractor with no assigned jobs or job-authored history. The action removes the contractor's legacy files/notes/services and private-upload metadata, keeps actor activity history, and compensates private storage cleanup after commit. Referenced accounts remain archive-only.
- Profile or service edits on a contractor set `Users.is_approved` to `2`, clear `approved_date`, increment `app_account_states.session_version`, and revoke the prior work session. Email edits also clear the email proof.
- Contractors can edit their own profile, profile photo, and service selection only while approved, active, and email-verified. The edit response updates that session's version and sends the contractor to the account-status page while review is pending.
- The service catalog supports admin create, rename, list, and delete. A service that is assigned to a contractor or job is protected from deletion.
- Contractors and admins can upload W9, insurance, or other permitted PDF/JPEG/PNG documents up to 10 MB. Uploads are stored on the private disk, metadata is recorded, and the legacy `Files.file_data` BLOB is retained for compatibility and recovery.
- Document preview and download are authenticated controller responses with ownership/admin policy checks, safe filenames, private cache headers, and `nosniff`. No storage path is exposed to the browser.
- Admins can archive documents. Permanently deleting an application-created document removes its database record and private copy; imported documents are archive-only so the M2 import provenance is not silently destroyed.
- Profile photos have authenticated inline and deletion routes. The newest Laravel private copy is used, with the imported private copy/BLOB as a fallback. A self-removal or replacement is a profile change and returns the account to review.
- Admin actions write `ActivityLogs` entries. Subject IDs are included in audit text without copying private payloads, passwords, file bytes, or tokens.
- All multi-record workflows use transactions. New private files are deleted when their database transaction fails.

## Route inventory

All routes below are behind `auth` and `account.current`; admin routes also require `role:admin` and `verified`, and contractor routes require `role:contractor` and `verified`.

| Route name | Method/path | Purpose |
| --- | --- | --- |
| `admin.contractors.index` | GET `/admin/contractors` | Search and filter contractors |
| `admin.contractors.create` / `.store` | GET/POST `/admin/contractors[/create]` | Create an unapproved contractor |
| `admin.contractors.show` / `.edit` / `.update` | GET/PUT `/admin/contractors/{user}[\/edit]` | Inspect or edit a contractor |
| `admin.contractors.approval` | POST `/admin/contractors/{user}/approval` | Set approval state and private reason |
| `admin.contractors.priority` | POST `/admin/contractors/{user}/priority` | Set priority flag |
| `admin.contractors.archive` / `.restore` | POST `/admin/contractors/{user}/archive|restore` | Reversible account deletion/restore |
| `admin.contractors.destroy` | DELETE `/admin/contractors/{user}` | Guarded permanent deletion when no job/history references exist |
| `admin.contractors.photo.destroy` / `contractor.profile.photo.destroy` | DELETE `/admin/contractors/{user}/photo` or `/contractor/{user}/profile/photo` | Admin or self profile-photo removal |
| `admin.contractors.notes.store` | POST `/admin/contractors/{user}/notes` | Add an admin private note |
| `admin.contractors.documents.index` / `.store` | GET/POST `/admin/contractors/{user}/documents` | Admin document workspace and upload |
| `admin.contractors.documents.archive` / `.destroy` | POST/DELETE `/admin/contractors/{user}/documents/{document}[/archive]` | Archive or permitted permanent delete |
| `admin.services.*` | GET/POST/PUT/DELETE `/admin/services...` | Minimum service catalog |
| `contractor.profile.edit` / `.update` | GET/PUT `/contractor/{user}/profile` | Own profile/photo/services |
| `contractor.documents.index` / `.store` | GET/POST `/contractor/{user}/documents` | Own document workspace and upload |
| `documents.preview` / `.download` | GET `/documents/{document}/preview|download` | Authenticated document response |
| `users.profile-photo` | GET `/users/{user}/profile-photo` | Authenticated profile-photo response |

## Data and storage

`2026_10_01_000005_create_contractor_upload_tables.php` creates `app_private_uploads`. It links an owner to a legacy source table/ID/column, private disk/path, byte count, SHA-256, MIME, safe original name, document type, expiry, and timestamps. The owner foreign key is restrictive. The source ID is deliberately not a foreign key because the table represents both `Users.profile_photo` and `Files.file_data` mappings and must coexist with imported provenance.

`ContractorManagement` is the transaction boundary for account changes, approvals, archive/restore, notes, and document writes. `PrivateUploadPolicy` and the legacy document/user policies are evaluated before binary responses. The private disk remains outside the public document root and no `storage:link` route is used.

## Verification

- `php artisan test --testsuite=Feature --stop-on-failure`: **64 tests, 804 assertions passed**; the focused M4 suite covers five tests and 86 assertions.
- `vendor/bin/pint --test`: passed.
- `php artisan view:cache`: passed.
- `php artisan route:list --except-vendor`: 53 application routes, including the M4 routes above.
- The existing M2/M3 import, ownership, authentication, notification, and error-handling tests remain green. The populated local target was not reset or used as a fixture for the new workflow tests.
- Local target check: 20 tables, 33 users, 2 documents, and zero new `app_private_uploads` records. All 60 legacy files match the source manifest and the SQL SHA-256 is unchanged. No production request/change occurred.
- Browser QA could not continue in this turn: Browser Use rejected the existing local tab navigation/refresh under its URL policy. HTTP and server-side workflow tests completed; no browser-policy workaround was attempted. Native upload/PDF/device checks remain unverified.

## Decisions carried forward

- Account archive is the normal deletion path. A guarded permanent-delete route is available only for contractors without job/history references; imported file metadata and private copies are removed for that explicit action while ActivityLogs remain as historical audit rows.
- Legacy `Notes` has a required `admin_id` and no visibility column. M4 therefore keeps contractor notes admin-private, matching the existing policy and legacy evidence. Whether contractors should read or author a shared note stream needs an explicit business decision before changing that schema contract.
- Business email events for approval, profile/service review, notes, and document expiry remain in M4.5. M4 records the local audit event but does not send mail.
- Imported document/profile BLOBs remain in the legacy tables and `app_private_files`. New uploads have a second metadata record in `app_private_uploads`; the import rerun guard should continue to refuse overwriting a target after application writes.
- Expiry-day semantics, document retention duration, profile-photo replacement retention, and admin bulk-review actions remain release decisions. The current form accepts optional past or future expiry dates for explicit review and exposes only individually authorized actions.
