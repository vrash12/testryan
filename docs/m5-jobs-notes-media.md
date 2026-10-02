# M5 jobs, notes, and media

Updated: 2026-10-01 (Asia/Shanghai).

M5 implements the first job workflow slice in the separate `laravel-app`. It uses the imported legacy `Jobs`, `Job_Services`, `Job_Notes`, and `Job_Media` tables and adds only application-owned transition and private-upload metadata. The populated local database is never reset by the M5 tests, and no production or legacy file was changed.

## Route and screen inventory

Admin routes under `auth`, `account.current`, `verified`, and `role:admin` provide:

- `GET /admin/jobs` — searchable/status-filtered job list with state counts.
- `GET|POST /admin/jobs/create` — job form and transactional creation, with optional approved contractor assignment and accept-on-behalf.
- `GET|PUT /admin/jobs/{job}/edit` — detail editing and service synchronization.
- `GET /admin/jobs/{job}` — detail, assignment, notes, tagged media, activity, and transition summary.
- `POST /admin/jobs/{job}/assignment` — assignment, unassignment, and reassignment.
- `POST /admin/jobs/{job}/acceptance` — admin acceptance on behalf of the assigned contractor.
- `POST /admin/jobs/{job}/completion` — complete or reopen.
- `POST /admin/jobs/{job}/notes`, `POST /admin/jobs/{job}/media`, `DELETE /admin/jobs/{job}/media/{media}` — nested note/media actions.
- `DELETE /admin/jobs/{job}` — guarded deletion; imported media is retained as historical evidence.
- `GET /admin/jobs/{job}/print` — print-friendly authorized view.

Contractor routes under `auth`, `account.current`, `verified`, and `role:contractor` provide assigned-job index/detail/print, acceptance, append-only notes, and tagged media upload. A contractor must be the current assigned owner, must be approved/verified through the existing account gate, and must accept before uploading media. Completed jobs remain readable; contractor note/media writes are rejected.

Shared authenticated media routes stream private files only after nested job ownership is checked:

- `GET /job-media/{media}/preview` — inline response.
- `GET /job-media/{media}/download` — attachment response.
- `GET /job-media/{media}/thumbnail` — bounded JPEG thumbnail for image media; PDFs return 404.

## Data and transitions

`JobManagement` is the transaction boundary for job mutations. It preserves legacy IDs and columns, validates services and money with two-decimal decimal arithmetic, and records `ActivityLogs` entries for important changes. Reassignment clears acceptance/completion timestamps and appends a transition; acceptance and completion are separate idempotent transitions. Reopening clears only completion state and leaves acceptance intact.

Migration `2026_10_01_000006_create_job_transitions_table.php` creates `app_job_transitions` with actor, action, from/to status, contractor IDs, JSON state snapshots, metadata, and timestamps. It intentionally has no foreign key to `Jobs`, so a guarded job deletion can retain an auditable history row after the job and its children are removed. The target therefore has 21 tables after the forward-only M5 migration.

Job notes remain append-only in `Job_Notes`. Admins can add notes; the current assigned contractor can add notes until completion. Notes are escaped/sanitized at render time and remain visible to the assigned owner and administrators. Reassignment changes who can see the job and its shared notes without rewriting the note history.

## Media and storage

Uploads accept detected `image/jpeg`, `image/png`, and `application/pdf` only, with Laravel request validation and a 10 MB per-file limit. Files are written to the private disk under a generated job-scoped path and retain the legacy `Job_Media.file_data` value for compatibility. `app_private_uploads` records owner, source mapping, path, byte size, SHA-256, detected MIME, and safe original name. Responses use authenticated policy checks, safe names, `no-store`, and `nosniff` headers. Imported `app_private_files` media cannot be deleted through the new workflow.

Descriptions and terms use the Symfony HTML Sanitizer allowlist through `App\Support\JobContent`; original values remain in the database. Blade screens share the existing role-aware layouts and Tailwind components and include mobile-friendly cards, action controls, empty states, validation feedback, and print output.

## Permission and decision boundary

| Action | Admin | Assigned approved contractor |
| --- | --- | --- |
| List/view jobs | All | Own assigned jobs |
| Create/edit/assign/delete | Yes | No |
| Accept | On behalf of an assigned contractor | Own pending assignment |
| Complete/reopen | Yes | No |
| Add notes | Yes | Own job before completion |
| Upload media | Yes | Own accepted job before completion |
| Preview/download/thumbnail | All authorized jobs | Own assigned jobs |

The M5 implementation adopts the reviewed working rules: pending/review contractors remain outside work routes; shared job notes may be added before acceptance; completed jobs are read-only to contractors; reassignment resets acceptance/completion and records history; admin deletion remains available but imported evidence is protected. These decisions are local implementation defaults pending product review.

## Validation and remaining work

The focused M5 plus ownership run passes **9 tests / 207 assertions**. The final sequential suite passes **75 tests / 974 assertions**. Formatter, strict Composer validation/platform checks, Blade cache, PHP syntax checks, and the Vite build pass. Native device file selection/PDF behavior, business notifications/preferences, admin review queues, dashboards, template management, and release deployment remain M4.5/M6/M7/M8 work. Browser navigation through the existing in-app tab remains limited by its URL policy; authenticated HTTP and private-storage checks are automated.
