# M2 — Local schema and data migration

Completed: 2026-10-01 (Asia/Shanghai). The user explicitly requested M2 and population from the supplied SQL. Work is confined to `laravel-app/`, documentation and new loopback databases. Production and all 60 legacy files are unchanged. M3 and deployment remain pending.

## Result and source

The application now uses `nestcrafters_local`, populated with all **412 exported rows across 11 legacy tables**. The native reference restore is `nestcrafters_m2_legacy`. Its dedicated `nestcrafters_stage` account has SELECT-only access after reconciliation. The original M1 database is still empty and was not repurposed. The target has 15 tables: 11 domain tables, three import/file tracking tables and `app_migrations`.

The source remains `nestcrafters_database.sql`, 3,077,625 bytes, SHA-256 `3ebef325d80b514f900f462673ba40398365fbbcdb4162c20b00771469495fc5`. No record bodies, password hashes, credentials or file bytes are included in this report or automated fixtures. Runtime secrets remain in ignored `.env`; databases and private storage contain the local reference data.

| Legacy table | Verified source / target rows | Model and key | Preserved next ID |
| --- | ---: | --- | ---: |
| Users | 33 / 33 | User, user_id | 62 |
| Services | 36 / 36 | Service, service_id | 44 |
| Templates | 3 / 3 | Template, template_id | 5 |
| ContractorServices | 117 / 117 | ContractorService pivot, user_id + service_id | — |
| Jobs | 1 / 1 | Job, job_id | 8 |
| Files | 2 / 2 | ContractorDocument, file_id | 5 |
| Notes | 0 / 0 | ContractorNote, note_id | 3 |
| ActivityLogs | 215 / 215 | ActivityLog, log_id | 216 |
| Job_Services | 5 / 5 | JobService pivot, job_service_id | 74 |
| Job_Notes | 0 / 0 | JobNote, note_id | 4 |
| Job_Media | 0 / 0 | JobMedia, media_id | 11 |

There are two admins and 31 contractors. Contractor approval counts remain 0=28, 1=2, 2=1. One account has a NULL email. These historical rows were imported directly, without public registration validation, new approval transitions or manufactured email verification.

## Schema and relationship mapping

`database/schema/legacy.php` holds reviewed schema definitions only. Three migrations create the domain tables, the tracking tables, then the reference constraints. All 77 legacy columns retain their types, order, defaults, nullability and signed INT/custom key mappings. Original indexes and source next-ID values are preserved. The documented changes are MyISAM → InnoDB, latin1 → utf8mb4/utf8mb4_unicode_ci, 12 restrictive domain foreign keys, and a unique index on `Files.unique_file_id`. This export satisfies the added constraints; new uniqueness/collation and deletion behavior must be respected by later actions.

| Child column | Parent column | Target constraint |
| --- | --- | --- |
| ContractorServices.user_id | Users.user_id | Restrict delete/update |
| ContractorServices.service_id | Services.service_id | Restrict delete/update |
| Jobs.contractor_id | Users.user_id | Nullable; restrict delete/update |
| Files.user_id | Users.user_id | Restrict delete/update |
| Notes.user_id | Users.user_id | Restrict delete/update |
| Notes.admin_id | Users.user_id | Restrict delete/update |
| Job_Services.job_id | Jobs.job_id | Restrict delete/update |
| Job_Services.service_id | Services.service_id | Restrict delete/update |
| Job_Notes.job_id | Jobs.job_id | Restrict delete/update |
| Job_Notes.created_by | Users.user_id | Restrict delete/update |
| Job_Media.job_id | Jobs.job_id | Restrict delete/update |
| Job_Media.uploaded_by | Users.user_id | Restrict delete/update |
| ActivityLogs.user_id | Users.user_id | Logical relationship only; no FK |

All 12 constrained relationships have zero orphans. The **15 ActivityLogs references to absent users 11/12/14 remain intact**. The associated-user relationship returns NULL for those entries. Do not create invented accounts or interpret every legacy user reference as an unambiguous actor. Restrictive FKs prevent silent history loss; they do not settle D6 retention or remove the need for a reviewed admin deletion action.

Models explicitly map legacy table/column names, composite and custom pivot keys, inverse relations and available timestamps. Ordinary domain models disable automatic timestamps where the export lacks Laravel's pair; Templates keeps both. User authenticates through `password_hash`, has no assumed `remember_token` or `updated_at`, and remains guarded against mass assignment. Passwords, BLOBs and internal storage paths are hidden from normal model serialization. DECIMAL prices are cast as strings with two decimal places, preserving zero and NULL distinctly. Approval/media enums and a read-only derived job status supply labels without changing stored states or timestamps.

Ancillary tables use the `app_` prefix to avoid Users/users and Jobs/jobs collisions on this Windows MariaDB (`lower_case_table_names=1`). File sessions/cache and synchronous queues remain unchanged; no auth, notification or queue schema is created in M2.

## Text, dates and passwords

The export declares latin1 tables but no export connection charset. All non-BLOB values are valid UTF-8; 58 ActivityLogs action cells contain non-ASCII UTF-8. The native restore uses connection `SET NAMES latin1` so literal bytes survive in the original latin1 schema. The prepared target import stores those same valid UTF-8 bytes in utf8mb4. Full row checksums match the parsed dump, native restore and target. Invalid UTF-8 is rejected for review rather than guessed or silently converted.

Historical HTML, entities, quotes and control characters are retained. They are not decoded or sanitized during import. Later rich-content rendering requires the reviewed allowlist; original HTML must remain recoverable. No domain HTML is rendered by the current placeholder screens.

Both import connections use session `time_zone='+00:00'`. Exported date/time calendar strings are preserved, including acceptance/completion evidence. This does **not** prove that the export's original TIMESTAMP values represent UTC instants. Business timezone, original exporter timezone and currency remain unresolved.

All 33 source password hashes are byte-identical bcrypt `$2y$10$` values and are recognized by PHP. A synthetic known-password bcrypt fixture verifies `password_verify` and Laravel's identity/password-field mapping. Original plaintext passwords are unknown, so actual legacy credential login is not claimed; M3 will implement and test login without a blanket reset.

## Private files and reversibility

All original BLOB columns remain intact in the target and reference restore. Four additional private copies are recorded in `app_private_files`: two documents and two profile photos, totaling **2,899,381 bytes**. Detected types are three JPEGs and one PDF; no current file is flagged for MIME review. There are no exported Job_Media records, so a synthetic PNG media fixture covers that mapping.

Each mapping records the source table/ID/column, private disk/path, byte length, SHA-256, detected and declared MIME, original filename when supplied, and review flag. Copies use `imports/{source SHA}/{table}/{ID}/{BLOB SHA}.bin` beneath `storage/app/private`. Existing bytes are never replaced; mismatches abort. No public symlink, direct file route or authenticated file UI was added. M4/M5 must provide policy-checked downloads/previews and safe response filenames/content types before exposing files.

`app_import_runs` records one unique export digest and reconciliation report. `app_import_rows` records all 412 source keys and source/target row digests. A report copy is saved privately at `imports/{source SHA}/report.json`; a report-file failure does not misreport a committed import as a database failure.

## Import operation and failure behavior

From `laravel-app/` on this configured machine:

```powershell
php artisan migrate
php artisan nestcrafters:import-legacy 'C:\Users\MAURICIO\Documents\ChatGPT\ryan\nestcrafters_database.sql'
```

Migrations have already run and the import has already populated the target. A rerun verifies the existing baseline and returns `verified-rerun`; it is not a refresh or synchronization command.

The importer allows only local/testing, loopback MySQL/MariaDB, explicitly listed separate source/target database names, and the actual local server hostname. Test imports additionally require `_test` schemas. Independent database advisory locks reject overlapping imports before source or file changes. The parser accepts only the 11 reviewed schemas and literal INSERT values; dump DROP statements and foreign-key toggles are recognized but never executed. Arbitrary statements, altered DDL, expressions, duplicate keys and truncated input are rejected. The current reader supports this export format up to 64 MiB, not general mysqldump syntax or streaming large production transfers.

The native restore creates an empty reference schema or resumes an exact 11-table partial restore whose existing rows match the source. Unknown tables or altered rows abort without overwrite. MyISAM staging is not transactional; partial native inserts can persist after failure, and only verified rows may be resumed. This machine's completed reference is now read-only, so restoration of a different snapshot requires a separately provisioned, reviewed local baseline.

New target imports require empty domain tables, use prepared inserts in one InnoDB transaction, and suppress model events/mail/notifications/queues. A failure rolls back target records and removes only new private copies; cleanup failures are logged privately without hiding the original error. Existing files are retained. An identical completed import rechecks all rows, next IDs, engines, charsets, ledger and file bytes. Later edits, extra rows, changed counters or file bytes cause refusal rather than replacement. After application use begins, use a separate reconciliation/change-transfer process instead of forcing this baseline importer over edited data.

The initial local server's 1 MiB `max_allowed_packet` was too small for the document INSERT. It was raised **at runtime on this local server only** to 33,554,432 bytes (32 MiB). No server configuration file or production setting was changed. A restart may reset it; a local database administrator must configure at least 32 MiB and reconnect before import. The importer preflights both connections and does not change global server settings.

```sql
-- Local administrative setup only, before reconnecting import sessions:
SET GLOBAL max_allowed_packet = 33554432;
```

The account `nestcrafters_local` is restricted to the target schema. `nestcrafters_stage` now has SELECT only on the staging schema. Dedicated synthetic-test credentials are restricted to `nestcrafters_m2_test` and `nestcrafters_m2_source_test`. Passwords are random and stored only in ignored environment configuration. See the [application setup](../laravel-app/README.md) for reproduction without exposing credentials.

## Verification completed

- `php artisan test --compact`: **31 passed, 367 assertions**; the 14 MariaDB import feature tests ran against disposable schemas, alongside four parser tests and the 13 existing M1 checks.
- Import tests cover every fixture column, NULL/empty/zero, Unicode/quotes/control bytes/HTML, all model relationships, controlled bcrypt compatibility, signed keys/restrictive FKs, preserved orphan logs, next IDs, migrations rollback/rebuild, unchanged reruns, refusal of local/source edits, changed private files, overlapping imports, storage-failure rollback and no mail/events.
- The full supplied export was restored natively, populated, rerun and reconciled with per-row checksums; all 77 original column definitions matched staging and target. All 12 domain references have zero orphans, and all four private copies match source BLOB bytes and lengths.
- Actual imported user/job edits and service detachment were performed inside an explicitly rolled-back transaction. The subsequent full baseline verification passed; no demonstration edits remain.
- The command rerun succeeds using the now SELECT-only staging account. Formatter, strict Composer validation and PHP platform checks pass. No dependency or frontend change required a new asset build; M1's existing build remains in use.
- Loopback `/health` and `/up` return 200; guest admin/contractor routes redirect to login; the local admin preview remains a placeholder. Private/import/environment/SQL URL probes return 404.
- All 60 legacy reference hashes and the original SQL digest were rechecked unchanged. No production request, mail delivery, production modification or deployment occurred.

Migration rollback/rebuild was tested only on disposable schemas. Rolling back migrations on the populated target would remove its tables; it is not a nondestructive undo operation and does not remove private copies. For a future rollout, preserve a current independent backup and retain the old system until reconciliation/rollback is reviewed. The supplied export still lacks evidence of triggers/views/routines/events and snapshot consistency; M2 validates its contents, not production completeness.

## Review boundary and next implementation order

M2 is complete for this local export. The browser shell intentionally does not yet display imported accounts/jobs or permit sign-in. Next is M3 identity and permissions, with early M4.5 verification/rate limits/bot controls; then M4 contractors/documents/service selection, M5 jobs/collaboration and M6 dashboards/templates/catalog.

Before dependent workflows, resolve D1–D10 in [the review](review.md): pending-account/onboarding and imported verification, account lifecycle/deletion, notes and job transitions, assignment eligibility, upload limits, notification recipients/schedules, recovery/bot settings, timezone/currency/HTML rules, and hosting/source facts. M2 does not accept those proposed defaults. Source completeness and a current consistent release snapshot remain M0/M8 work. Stop before M3 until the user requests it.
