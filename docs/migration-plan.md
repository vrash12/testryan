# Laravel migration plan and implementation order

Prepared: 2026-10-01 (Asia/Shanghai). Originally a documentation proposal; the user subsequently authorized M1 through M5 locally. [M2 report](m2-data-migration.md) records the import, [M3 report](m3-identity-permissions.md) records authentication/permissions, [M4 report](m4-contractor-workflows.md) records contractor/documents, and [M5 report](m5-jobs-notes-media.md) records jobs/notes/media. M4.5 business events, M6 management and deployment remain pending. The user selected pending status/verification only; [route/feature/risk audit](legacy-audit.md), [SQL model](database-audit.md) and [permissions/events](permissions-notifications.md) remain the contract with D2–D10 unresolved.

## Recommended architecture and structure

Create a separate `laravel-app/` directory only after implementation is requested. Keep the legacy snapshot and SQL outside its public document root and out of commits; do not run or include legacy PHP from Laravel. Select supported Laravel/PHP/Tailwind versions against local and intended hosting runtimes at M1, rather than choosing versions from an outdated snapshot. Use server-rendered Blade with Tailwind and progressive enhancement for search, upload progress, viewers and accessible dialogs. A SPA, API/mobile app, PWA, realtime transport or additional role system is not necessary for feature parity.

```text
laravel-app/
  app/
    Models/                     User, Job, Service, ContractorDocument, JobMedia,
                                JobNote, ContractorNote, Template, ActivityLog,
                                explicit legacy pivots and approved ancillary models
    Enums/                      ApprovalStatus, MediaTag, DocumentType, derived JobStatus
    Http/
      Controllers/Auth/         Registration, sessions, verification, recovery
      Controllers/Admin/        Dashboard, contractor/review/approval/priority/password,
                                jobs/assignment/acceptance/completion, notes, media,
                                documents, services, templates, activity, notifications
      Controllers/Contractor/   Dashboard, profile/services, jobs/acceptance,
                                permitted notes/media, documents, preferences
      Controllers/              Shared authorized preview/download/photo/thumbnail
      Requests/Auth|Admin|Contractor/  Scoped validation and request authorization
      Middleware/               Role, approved-contractor and account-lifecycle gates
    Policies/                   User, Job, ContractorDocument, JobMedia, JobNote,
                                ContractorNote, Service, Template, ActivityLog
    Actions/
      Contractors/              Register/Create/Update/Review/Archive, password workflows
      Jobs/                     Create/Update/Assign/Accept/Complete/Reopen/Delete
      Documents/                Store/Archive/Replace with staged storage cleanup
      Notifications/            Record intents, resolve recipients and preferences
      Import/                   Legacy mapping/reconciliation without event delivery
    Services/                   Private file handling, HTML sanitization, risk indicators
    Events/                     Committed business changes, independent of transport
    Notifications/              Email/in-app events with safe payloads
    Mail/                       Custom mailables only where Notifications needs them
    Jobs/                       Delivery/thumbnail tasks after commit, bounded retries
    Console/Commands/           Import/check/expiry scan (isolated environment guards)
  config/                       Domain/upload/event/abuse settings, secrets via environment
  database/
    migrations/                 Exact legacy mappings then documented ancillary changes
    factories|seeders/          Synthetic users/jobs/files/events, no private dump
  resources/
    views/components/           Tailwind shell, forms, status badges, cards/tables,
                                dialogs, timeline, pagination, viewer/upload feedback
    views/auth|admin|contractor/ Role screens and print views
    css|js/                     Vite/Tailwind and modest accessible enhancement
  routes/
    web.php                     Public/shared groups plus role groups
    admin.php|contractor.php    Explicitly registered named role routes
    console.php                 Scheduler registration per selected Laravel version
  storage/app/private/          Uploaded originals and derived assets; never public symlink
  tests/
    Feature/Auth|Authorization|Contractors|Jobs|Documents|Notifications|Import/
    Unit/                       State, date/decimal, mapping and sanitizer rules
    Browser/                    Representative mobile tasks once tooling is selected
  .env.example                  Safe placeholders; no production credentials
  README.md                     Exact reproducible setup/build/test/import instructions
```

Names are organizational recommendations, not an instruction to create empty abstractions. Keep controllers thin and use one shared action per business transition across admin/contractor entry points. Avoid parallel implementations of the same acceptance, profile or document rule. Define explicit route/model binding for custom keys and nested resources. A file's public identifier never substitutes for policy checks.

The [technical review](review.md) adds mandatory scaffold checks: adapt default auth migrations to Users and configure database queues to use a separate table such as app_queue_jobs. The names users/Users and jobs/Jobs must not depend on case differences for isolation. New actual foreign keys must match signed legacy INT keys and explicit columns; inspect scaffold migrations before executing them.

Treat the existing `/contractors/` URL as the external base path unless a different URL is explicitly accepted. Example route paths in the audit are application-relative. Confirm reverse-proxy/web-server prefix handling, asset URLs, authenticated downloads, redirects and signed verification/reset links in a local equivalent configuration. Give the portal a dedicated session cookie/configuration independent of other lazula.net applications. Expose only Laravel's public front controller, never the project root, dump, legacy includes or private storage. The recovered source has no .htaccess/deployment descriptor, so hosting rewrites cannot be inferred. [Laravel public-root guidance](https://laravel.com/framework/docs/deployment#nginx).

## Data preservation and import sequence

1. Preserve the reference SQL bytes/hash and source snapshot unchanged. Record a read-only source manifest; recover missing application handlers/dependencies and absent promised helpers/archive through a separately authorized read-only recovery task. Investigate `spl/` provenance; never copy it to Laravel public routes.
2. Restore the dump only to a dedicated isolated staging database with credentials that cannot reach production. The dump contains DROP statements; reject any production host/schema target. Match source MyISAM/latin1 first to establish a faithful baseline; explicitly control connection charset/SQL mode/TIMESTAMP timezone. Restore binary SQL as bytes, not through lossy UTF-8 file decoding. Use backups and a binary-safe native export for later release transfer if possible.
3. Compare exact table/column/index definitions, 412 row counts, custom IDs and next auto IDs, NULL/empty/zero, enums, dates, decimals, text and BLOB checksums. Check inferred relationships and preserve/report the 15 orphan log references. A successful SQL parse is not restore proof. Reconcile counts with an actual consistent source snapshot before release; empty job media/notes need synthetic fixtures.
4. Create a separate target schema with explicit legacy names/keys. Convert to InnoDB deliberately so transactions work; retain staging source unchanged. Plan utf8mb4 conversion only after source text/connection encodings and already-escaped fields are established. Map historical log associations without creating fake actor identities. Add constraints after reconciliation, using nullable historical actor references or legacy IDs where needed.
5. Preserve every legacy password_hash verbatim. Configure auth with user_id/password_hash mapping and compatible bcrypt; validate controlled known-password fixtures and driver rehash behavior. All exported prefixes are `$2y$10$`; do not force resets without demonstrated incompatibility. Existing nullable-email contacts remain readable/admin-editable and need no fake email or fabricated verification.
6. Move Files.file_data, Users.profile_photo and Job_Media.file_data to private storage only through a reversible mapping. Save original table/key/public ID, raw length and SHA-256, disk/key/MIME/filename/size, processing result and rollback reference. Verify restored bytes match before removing any BLOB. Keep original uploads distinct from derived thumbnails; unknown/bad files enter a private review path without disappearing.
7. Import in dependency order: Users, Services, Templates; ContractorServices, Jobs, Files, Notes, ActivityLogs with orphan-aware mapping; Job_Services, Job_Notes, Job_Media. Keep original IDs and timestamps; report validation exceptions rather than coercing. No registration, review, assignment or expiry email should fire during import.
8. Make import repeatable with a manifest/checkpoint, explicit conflict handling, stable public/file IDs and checksum-based verification; reruns do not duplicate pivots/events/files or overwrite later target edits silently. Use database transactions for records and compensating cleanup for staged filesystem writes. Run clean-schema migrations and repeat import before release.

Target constraint, status, encoding, storage and retention changes require their own documented migration report. Never clean suspicious users, delete orphan logs or rewrite accepted/completed flags merely to make an import pass.

Use dedicated import mapping, not public registration/profile validation: historical rows remain readable even when new-account rules are stricter. Preserve original rich text before sanitizing rendered content. The new workflow validation must not silently discard records, manufacture email verification or alter acceptance evidence during import.

## State contracts to implement after review

| Workflow | Invariant / proposed transition |
| --- | --- |
| Register | Contractor role only; state=0, explicit priority=0, user/services/review intent atomic; verification separate |
| Self profile/services/photo update | Session owner; meaningful changes trigger state=2 per agreed photo/no-op rule; consistent approved_date semantics; admin event after commit |
| Approval | State validated 0/1/2; date set/cleared consistently; record real actor, target, old/new/reason; blocked lifecycle remains separate |
| Create job | Name/description/valid services, optional due/terms/assignment; preserve NULL vs decimal zero; deposit/price validation on create and edit; immediate admin acceptance requires valid contractor and date |
| Assign/reassign | Require existing eligible contractor/job; proposed new assignment resets active acceptance/completion and archives prior history; explicit override decision D3/D4 |
| Accept | Assigned pending job only; current eligible contractor or authorized admin; lock/conditional update makes repeat request idempotent; actor, terms version, time, log and event consistent |
| Complete/reopen | Admin-only proposed; accepted/assigned preconditions and coherent dates/history; never pretend completion transfer preserves original consent |
| Job media | Own accepted job for contractor; admin existing job; MIME/tag/10 MiB per file; batch successes durable, failures visible, retry deduplicated |
| Notes | Separate private account Notes from shared Job_Notes; scoped author/visibility rules D2, plain-text escaping, no unsupported delete/edit permissions |
| Documents | Own/admin, MIME/date/type/cap validation; explicit archived=0; replacement/retention and active W9/expiry semantics decided |
| Delete/archive | Reference/retention policy and actor/subject evidence; non-GET/CSRF, explicit confirmation, parent/child integrity; preserve log history |

Concurrent profile approvals, double accepts, assignment/upload races, and bulk review must recheck state inside the transaction, not rely on a stale form or session. Private storage writes need failure compensation; mail dispatch waits for commit. Target activity logs distinguish actor from subject; original legacy prose remains available independently.

Persist durable delivery intent with the mutation/audit and process it recoverably after commit. After-commit dispatch alone is not a persisted retry record. Define unique event/recipient/channel keys and attempt tracking; external mail delivery can remain ambiguous on worker failure, so do not promise exactly-once SMTP delivery. Verification/invitation/recovery need explicit unverified-address exceptions to ordinary workflow-mail eligibility.

## Implementation order and acceptance gates

| Stage | Vertical slice and deliverables | Gate before progressing |
| --- | --- | --- |
| M0 finish | Review audit D1–D10; recover missing reference files; isolated restore/text/BLOB validation and completeness manifest | Source/data confidence and documented decisions; current M0 is not complete |
| M1 foundation | Runtime/hosting compatibility, separate Blade/Tailwind app, safe env/mail/storage/session setup, shared responsive shell | Reproducible local setup, asset build, private/public boundary; no production dependencies |
| M2 schema/import | Explicit legacy models/schema; repeatable data import; InnoDB, encoding and file mapping staged | Exact counts/IDs/bytes/statuses verified; no event spam; transactions proven |
| M3 identity + early M4.5 — complete locally | Login/hash/role policies, pending status/verification only, approval gates, bot/throttle/duplicates, admin credentials and durable identity mail | 63-test M3 baseline; historical M4 checkpoint 64 tests/804 assertions; final import rerun passed; direct cross-owner/stale-state tests and synthetic local mail capture |
| M4 catalog + contractor slice | Minimal service catalog first; admin create/edit/review/priority; self profile/services/photos; documents and private previews; account Notes | Atomic review transitions, limits/ownership/logging; preserve admin nullable-email contacts |
| M5 jobs slice — complete locally | Create/edit/assignment; acceptance and completion/reopen; finances/terms; permitted notes/media/preview/download/print; append-only transition history | 75-test sequential suite; focused M5/ownership state, race/idempotence, MIME/size and authorized nested-resource checks; native device checks remain M7 |
| M4.5 alongside each slice | Emit domain events, central preferences/recipients, local delivery/in-app status, safe retry/failure handling; expiry scheduler | No precommit or duplicate sends; failure doesn't misreport mutation; all enabled events covered |
| M6 management/dashboard parity | Full service/template management, admin/contractor dashboards, search/filter/print/log pages, missing-W9/expiry/overdue metrics | Every route/feature mapped to implemented replacement or accepted omission; HTML sanitized |
| M7 local review | Import reconciliation + relevant feature/unit/build checks, direct policies, accessibility and phone/desktop workflows | Record viewport/device evidence and limitations; client workflow acceptance |
| M8 separate rollout | Current consistent backups/source manifest, hosting env/private storage/queue/scheduler/mail, migration/final transfer/rollback/health checks | Concrete rollout reviewed and separately authorized; no production changes from this audit |

Notifications and registration protection begin with identity and each domain slice, not after all workflows are completed. Build mobile layouts and accessibility throughout, reserving M7 for verification rather than a late redesign. A minimum service management slice precedes jobs/contractor selection; complete service/template UX follows in M6.

## Review and unresolved work

The detailed decision register is in the [legacy audit](legacy-audit.md#decisions-for-review), with current acceptance in [review.md](review.md). D1 pending access is selected and implemented; imported verification follows the existing requirement without a blanket exception. Resolve notes, transition/history, eligibility, uploads/review, retention, recipients/schedules, provisioning/abuse configuration, timezone/currency/HTML and source/hosting recovery before dependent slices. Secure ownership, CSRF, validation, private storage and approval gating remain required fixes. M4 and M5 are complete locally; stop before M4.5/M6 or production until separately requested.

The completed [technical review and concrete recommendations](review.md) are the next review baseline. Archive-first behavior must not silently remove existing admin hard-delete capabilities; preserve a guarded delete route or document an explicitly accepted replacement. Missing third-party legacy libraries need not run in Laravel, but missing business behavior still needs recovered evidence or an accepted replacement. Nonblocking preference details can be settled at their slice; do not claim complete source recovery, completed M0 or product approval based on this technical review.

The original audit was static/syntax-only. Subsequent M1/M2/M3/M4/M5 provide the separate Laravel/Blade/Tailwind app, exact schema, 412-row local import, four imported private copies, tested identity/permissions, contractor management, service selection, private documents, jobs, notes and media. The M5 suite and final sequential suite pass 9/207 focused and 75/974 overall; staging is read-only and reference hashes remain unchanged. See [M5](m5-jobs-notes-media.md) for current evidence. M4.5 business notifications, dashboards/templates, source completeness and deployment remain later work.

## M5 implementation checkpoint — 2026-10-01

The M5 jobs slice is complete locally. `JobManagement` owns transactional create/edit/assign/accept/complete/reopen/note/media/delete operations; policies and stale-row locks enforce current ownership and acceptance. Admin and contractor Blade screens, print views, media preview/download/thumbnail routes, and sanitized rich-content rendering are in place. Migration `2026_10_01_000006_create_job_transitions_table.php` adds append-only `app_job_transitions` with no Job foreign key so guarded deletion can retain history. The local target is migrated forward to 21 tables; imported domain rows and private copies were not reset or re-imported.

M5 defaults follow the current review boundary: pre-acceptance shared job notes, acceptance-required contractor media, completed contractor jobs read-only, explicit admin completion/reopen, reassignment resets active acceptance/completion while preserving transition history, and imported media evidence is deletion-protected. These are implementation defaults pending product review, not a claim that missing legacy handlers were recovered. Business-event notifications/preferences, review queues, dashboards, service/template management, native-device checks and release work remain M4.5/M6/M7/M8.
