# NestCrafters — Local Laravel application

M1/M2/M3/M4/M5 are complete locally. The Blade/Tailwind application has mapped legacy models, a verified 412-row import, login/registration, verification/recovery, admin password setting, ownership policies, contractor management, service selection, private documents, profile photos, jobs, notes, private media, transition history and guarded deletion. Four imported private file copies remain intact. Business-event delivery, dashboards/templates and release work are later milestones; production, legacy files and the SQL export are unchanged. See [M2 reconciliation](../docs/m2-data-migration.md), [M3 identity/permissions](../docs/m3-identity-permissions.md), [M4 workflows](../docs/m4-contractor-workflows.md), and [M5 jobs/notes/media](../docs/m5-jobs-notes-media.md).

## Confirmed runtime

Checked before application code on 2026-10-01:

| Tool | Local version |
| --- | --- |
| PHP | 8.4.10, CLI; C:/xampp-new/php/php.exe |
| Composer | 2.8.12 |
| Node | 24.13.1 |
| npm | 11.8.0 |
| MariaDB server/client | 10.4.32; loopback TCP port 3306 |
| Laravel installed | 13.34.0; skeleton 13.0.0 |
| Tailwind installed | 4.3.3, Vite plugin 4.3.3 |
| Vite installed | 7.3.6; Laravel Vite plugin 2.1.0 |

Dependencies are pinned by composer.lock and package-lock.json. The required PDO MySQL, OpenSSL, mbstring, DOM/XML, ctype, fileinfo, curl and zip extensions are present. Official references: [Laravel release requirements](https://laravel.com/framework/docs/releases), [Tailwind with Vite](https://tailwindcss.com/docs/installation/using-vite).

## Run the existing local setup

The ignored .env already contains a generated application key and a random password for a dedicated local database account. Do not overwrite it or regenerate its key during ordinary development.

From this directory in PowerShell:

```powershell
php artisan serve --host=127.0.0.1 --port=8011
```

Open http://127.0.0.1:8011. Built assets are present locally, so no Vite server is required. For live asset development, use composer dev (server and Vite only; no queue worker or migrations).

| URL | Purpose |
| --- | --- |
| / | Landing page, sign-in/registration and local preview links |
| /local/preview/admin | Admin shell, no authentication or records created |
| /local/preview/contractor | Contractor shell, no authentication or records created |
| GET/POST /login | Active sign-in with legacy-compatible password verification |
| GET/POST /register | Contractor registration, service selection, bot check and pending review |
| GET/POST /forgot-password | Generic, throttled recovery request |
| GET /reset-password/{token}, POST /reset-password | Hashed single-use token and validated new password |
| /account/status, /verify-email | Own email proof and administrator-review status |
| POST /email/verification-notification | Throttled verification resend |
| GET /verify-email/{id}/{hash} | Signed, expiring current-account email proof |
| /dashboard | Authenticated role redirect |
| /admin | Current, verified admin shell |
| /admin/accounts | Verified admin account-security list |
| GET/POST /admin/accounts/{user}/password | Controlled password setting; current admin password required |
| /contractor | Current, verified, approved contractor shell |
| /admin/jobs | Admin job list, filters and state counts |
| /contractor/jobs | Assigned contractor jobs |
| /admin/jobs/{job}, /contractor/jobs/{job} | Authorized job detail, notes, services, activity and tagged media |
| /admin/jobs/{job}/print, /contractor/jobs/{job}/print | Print-friendly job views |
| POST /logout | CSRF-protected logout and session invalidation |
| /health | HTML status; generic JSON when Accept is application/json |
| /up | Laravel liveness endpoint |

Previews require both APP_ENV=local and APP_SHELL_PREVIEWS=true. They render an empty view and never set an authenticated user or session. They return 404 in testing/production even if the flag is true. Protected routes remain protected after visiting a preview. Unimplemented navigation/actions are disabled and labeled. Phone shortcuts contain only Dashboard and More until real destinations exist.

Pending contractors can sign in to status/verification/logout only, as selected by the user. Work access requires email proof and approval 1; review 2 removes it. All 33 imported accounts, including admins, start unverified with their hashes unchanged. Use the existing password to sign in, then request verification; if it is unavailable, use ordinary recovery. A separate synthetic local admin account is available for local development login; it is not a production account or authentication bypass. User maps Users/user_id/password_hash without mass-assignable fields, remember_token or an updated_at assumption. Previews display no records. Account access and the implemented M4-M6 domain destinations are available to authorized roles; shell previews remain record-free.

## Reproduce on another local machine

1. Install the compatible PHP/Composer/Node/npm tools and enable the PHP extensions above.
2. In laravel-app, run composer install and npm ci.
3. Copy .env.example to .env only if .env does not already exist; then generate the initial key with php artisan key:generate.
4. Provision the two isolated local databases/accounts below, replacing each password placeholder with a different random password. Put them only in .env as DB_PASSWORD and LEGACY_DB_PASSWORD. Confirm the connection names/host/database values from .env.example.
5. On the local database server, configure at least 32 MiB max_allowed_packet before opening import connections. Run php artisan migrate, then the import command below using the absolute reference SQL path.
6. After reconciliation, revoke staging CREATE/INSERT so the baseline is SELECT-only. Run npm run build and php artisan serve --host=127.0.0.1 --port=8011.

```sql
CREATE DATABASE nestcrafters_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'nestcrafters_local'@'127.0.0.1' IDENTIFIED BY '<unique-target-password>';
GRANT ALL PRIVILEGES ON nestcrafters_local.* TO 'nestcrafters_local'@'127.0.0.1';
CREATE DATABASE nestcrafters_m2_legacy CHARACTER SET latin1 COLLATE latin1_swedish_ci;
CREATE USER 'nestcrafters_stage'@'127.0.0.1' IDENTIFIED BY '<unique-staging-password>';
GRANT SELECT, INSERT, CREATE ON nestcrafters_m2_legacy.* TO 'nestcrafters_stage'@'127.0.0.1';
SET GLOBAL max_allowed_packet = 33554432;
```

```powershell
php artisan migrate
php artisan nestcrafters:import-legacy 'C:\Users\MAURICIO\Documents\ChatGPT\ryan\nestcrafters_database.sql'
```

After a successful first import, a local database administrator can freeze the freshly provisioned staging account:

```sql
REVOKE INSERT, CREATE ON nestcrafters_m2_legacy.* FROM 'nestcrafters_stage'@'127.0.0.1';
```

Current setup: DB_CONNECTION=mysql, loopback 127.0.0.1:3306, target schema/account nestcrafters_local. It has 21 tables: 11 domain, three import/file, four identity/token/event/delivery, app_private_uploads, app_job_transitions and app_migrations, with all 412 reference rows. The M3 migration or a subsequent first import initializes unverified identity states without mail. Staging nestcrafters_m2_legacy is a native MyISAM/latin1 copy with SELECT-only access. The old nestcrafters_m1 remains empty. Accounts are limited to their schemas. Windows MariaDB uses lower_case_table_names=1; default users/jobs migrations remain absent. See [migration mapping](database/migrations/README.md).

The packet increase on this machine is runtime-only; a database restart may reset it. Both import connections preflight the packet limit. Rerunning the command verifies the unchanged baseline and creates no duplicates. Changed target/source rows, counters or private files cause refusal without overwrite; this command is not ongoing synchronization. It accepts only the reviewed export format on explicitly allowed local schemas, suppresses events/delivery and serializes concurrent runs. Do not run migrate:fresh or rollback on the populated target as an ordinary setup step.

M3 sign-in writes an activity log and may safely rehash a valid password; registration and password changes also edit the baseline. After using these workflows, the strict M2 reconciliation command will correctly refuse to report an unchanged export. It must not be used to overwrite active application data. Use the immutable staging/export and a new isolated target for future migration rehearsals.

APP_TIMEZONE=UTC is provisional. M2 preserves exported calendar values using +00:00 import sessions and exact decimal amounts; business timezone/currency and original exporter timezone remain unresolved. UTF-8 bytes and original HTML are retained, without guessed conversion or import-time sanitization.

## Private storage, sessions and errors

- The private/default disk and local alias point to storage/app/private, outside public. Automatic serving and public storage links are disabled. M4 file controllers enforce ownership and policy checks before streaming or previewing a binary.
- M2 retains all original BLOBs and records four verified private copies in app_private_files, totaling 2,899,381 bytes. M4 adds app_private_uploads for new profile/document metadata and paths; M5 uses the same metadata table for new job media. Internal paths and BLOBs are hidden from normal model serialization. Imported documents/media remain evidence-protected and all file responses are authenticated.
- Sessions/cache use files; queues remain sync locally. M3 delivery jobs are queue-compatible and durable intents can be retried; no worker/database queue schema exists. Future tables are app_queue_jobs, app_queue_batches and app_queue_failed_jobs.
- The dedicated nestcrafters_session cookie is encrypted, HTTP-only and SameSite=Lax. SESSION_SECURE_COOKIE=false is appropriate for the loopback HTTP server; a future HTTPS environment must set it true.
- APP_DEBUG=false is configured locally. Friendly 403/404/419/429/500/503 and fallback error pages show no exception details. Detailed errors stay in ignored storage/logs/laravel-YYYY-MM-DD.log. The public entry point disables raw PHP display_errors.
- Templates escape text. Web routes retain Laravel sessions and CSRF middleware. Responses include no-store, nosniff, frame denial and same-origin referrer headers.
- Only public/ is a web root. Never serve the workspace root, vendor, .env, storage, legacy PHP or SQL snapshots. .env, dependencies, builds and runtime/private files are excluded from Git.

## Local mail testing

MAIL_MAILER=log and MAIL_LOG_CHANNEL=mail capture Laravel Mail/Notifications output in ignored, private storage/logs/mail.log. This is the local mail sink; no SMTP service, production recipient or external delivery is used.

```powershell
php artisan nestcrafters:mail-test
Get-Content -LiteralPath storage/logs/mail.log -Tail 25
```

The command sends only a synthetic message to preview@nestcrafters.test, and refuses to run outside local or with an external transport. Capture was verified. Mailpit placeholders remain for later optional setup; it is not running. M3 identity delivery/failure tracking is described below; business preferences and queue hosting remain later work.

M3 verification, recovery and enabled registration notices use durable encrypted intents and private per-recipient delivery status. Security URLs appear in the private mail capture: inspect it locally without sharing the log or tokens. A synthetic verification notification was captured during QA. Local/testing identity delivery refuses external transports; current recipients and token validity are rechecked before each attempt. Retry due pending/failed mail with `php artisan nestcrafters:retry-identity-mail`. M6 implements business-event and safe review foundations. Per-user preferences/inbox/block controls and release queue hosting remain pending M4.5 work.

Registration defaults to a local-only arithmetic challenge, honeypot, one-use timed envelope, layered rate limits and duplicate-email constraints. Public environments require `REGISTRATION_BOT_DRIVER=turnstile` with environment-only `TURNSTILE_SITE_KEY`/`TURNSTILE_SECRET_KEY`, or registration must be disabled with `REGISTRATION_ENABLED=false`. No live provider keys are configured or tested. `REGISTRATION_ADMIN_MAIL` controls notices to active verified admins; zero eligible recipients does not create later backfill. Review signals are private. See the M3 report for throttle limits and rollout decisions.

## Health and subdirectory verification

Local /health checks application response, SELECT 1 database connectivity, private-directory writability, built assets and the selected log mail transport. It returns 503 if a check fails; failures log details privately. JSON is limited to status=ok/unavailable. Outside local, /health reports only application liveness and does not inspect/disclose infrastructure. It is not a production readiness or mail-delivery probe.

To reproduce the /contractors/ mount check on loopback:

```powershell
php -S 127.0.0.1:8012 -t public scripts/serve-subdirectory.php
```

Open http://127.0.0.1:8012/contractors/. The local-only router sets front-controller variables and a mount-specific SESSION_PATH without changing .env, and serves static assets only from public. It is a test helper, not a production server configuration. Named URLs, compiled assets and /contractors session-cookie path were verified over HTTP. A future host must configure a public document root/mount plus APP_URL including /contractors and SESSION_PATH=/contractors; deployment remains unapproved.

## Verification

The foundation tests use isolated in-memory configuration. M2/M3 use synthetic fixtures in **nestcrafters_m2_test** and **nestcrafters_m2_source_test**, never the imported target/staging databases. Provision a separate loopback user limited to those two disposable schemas and put credentials in M2_TEST_USERNAME/M2_TEST_PASSWORD in ignored .env. The guard verifies server identity/loopback/test names before resets. Tests use fake private storage/mail/notifications/queues. Without credentials, MariaDB import/identity tests explicitly skip; a skipped run does not verify those milestones. Run sequentially; do not add --parallel against shared schemas.

For a new machine, optional local administrator setup is:

```sql
CREATE DATABASE nestcrafters_m2_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE nestcrafters_m2_source_test CHARACTER SET latin1 COLLATE latin1_swedish_ci;
CREATE USER 'nestcrafters_m2_test'@'127.0.0.1' IDENTIFIED BY '<unique-test-password>';
GRANT ALL PRIVILEGES ON nestcrafters_m2_test.* TO 'nestcrafters_m2_test'@'127.0.0.1';
GRANT ALL PRIVILEGES ON nestcrafters_m2_source_test.* TO 'nestcrafters_m2_test'@'127.0.0.1';
```

```powershell
php artisan test --compact
php vendor/laravel/pint/builds/pint --test
composer validate --strict --no-check-publish
composer check-platform-reqs
npm run build
```

Historical M5 verification: the sequential feature suite **75 passed / 974 assertions** (including five M4 and seven M5 workflow tests); the focused M5 plus ownership run **9 passed / 207 assertions**. Formatter, strict Composer/platform checks, asset/Blade build, PHP syntax checks, actual export/private-file reconciliation, HTTP health/CSRF/canonical-origin and responsive UI checks pass. Authenticated workflow tests use synthetic disposable accounts and fake private storage; the populated target is not reset. See [M3 report](../docs/m3-identity-permissions.md), [M4 workflow report](../docs/m4-contractor-workflows.md), and [M5 workflow report](../docs/m5-jobs-notes-media.md). Native file-picker/PDF/device checks remain M7 work. M6 is now delivered locally below; remaining M4.5 and production rollout require separate authorization.

## M6 management and notification configuration

M6 exposes real role dashboards, service/template management, filtered activity/print, contractor directory/review/print and document expiry views. Template/job originals remain stored while rendered previews/insertion are sanitized. Admin bulk review requires an explicit confirmed selection and rejects the whole action if a selected account is no longer eligible. All admin endpoints and their JSON/print variants enforce current verified admin policies.

Business mail defaults off with NESTCRAFTERS_BUSINESS_NOTIFICATIONS=false. Individual NOTIFY_* switches are listed in .env.example; enabled notifications use current verified recipients and shared encrypted event/delivery tables. Local/testing permits only log or array mail. Enable synthetic local exercises only in ignored environment configuration; private captures contain authenticated links. Existing imported users remain unverified and no business mail was sent to them.

Use `php artisan nestcrafters:notify-document-expiry` for current-date Insurance reminders (today through day 30 inclusive). An explicit --date must equal today's date in APP_TIMEZONE; invalid/non-current dates are rejected before intent creation. The command does not enable a scheduler. `php artisan nestcrafters:retry-identity-mail` now retries both identity and business messages and leaves outcomes private. Queue/scheduler/mail hosting and per-user preferences/inbox/block controls remain later work.

See [M6 report](../docs/m6-management-dashboards.md) for current evidence, local recipient defaults and remaining M4.5/M7/M8 boundaries. Historical milestone test counts above are retained as historical checkpoints; the M6 report records the current final run.

M6 final verification: **110 tests / 1,392 assertions** passed sequentially without failures or skips. Formatter, 137 PHP syntax checks, Blade compilation, strict Composer/platform checks, Vite build and loopback health/preview/private-path checks pass. All 412 imported rows, four private copies, the original SQL digest and 60 legacy file hashes remain unchanged. Native/device checks remain M7; remaining M4.5 and production rollout are not completed or authorized.

## M7 disposable review fixture

The local M7 review uses only the guarded `m2_test` schema. From `laravel-app`, prepare synthetic identities and records, then serve the review app on its isolated port:

```powershell
php artisan m7:prepare-review
php -S 127.0.0.1:8013 -t public scripts/serve-m7-review.php
```

The command refuses a reset unless the database is the approved loopback test schema. It creates synthetic admin, approved, pending, and unverified contractor accounts with the fixture password printed by the command, plus representative jobs/documents/media/templates/activity. The review server sets a separate session cookie and supports no production or external-mail operation. Reset the fixture before repeating destructive checks. See `../docs/m7-client-review.md` for route-by-route checkpoints and the remaining native-device/client-review gates.

For a single working account on the main local server, open `http://127.0.0.1:8011/login` and use `admin.local@nestcrafters.test` with the local password supplied for this workspace. This account is stored only in the local `nestcrafters_local` database and is not part of the SQL export or production.

## Notification center and review controls

Run `php artisan migrate` to add the inbox and preference tables. `NESTCRAFTERS_NOTIFICATION_INBOX=true` enables future in-app updates independently of business email; the existing `NESTCRAFTERS_BUSINESS_NOTIFICATIONS` switch remains off by default. Open `/notifications` or the navigation bell for your own inbox and email preferences. Admins can open Email delivery status from the inbox, retry failed recipient deliveries, and block/unblock contractor accounts from their detail pages. Include blocked accounts in directory filters to find them. Pending contractors cannot access the inbox.

`php artisan nestcrafters:notify-document-expiry --staged` adds deduplicated 30-day/7-day/due-today/recently-expired stages. This is an explicit command, not an installed scheduler. Keep local mail on log/array. Full route/permission/data and release boundaries: [M4.5 continuation](../docs/m45-notification-center.md).

## Brevo / shared hosting

Brevo API, prefixed database queue tables, opt-in insurance/retry schedules and a bounded shared-host worker are implemented. Follow [the setup guide](../docs/brevo-shared-hosting.md) for protected environment settings, cPanel cron and real-mail acceptance checks. Local defaults remain log mail, synchronous delivery and disabled schedules until hosting credentials/configuration are supplied. `php artisan nestcrafters:brevo-check` prints configuration readiness without secrets or network calls; `--send-to` explicitly sends one synthetic test only on a configured hosting environment.
