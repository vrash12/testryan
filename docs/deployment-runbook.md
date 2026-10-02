# M8 deployment preparation — NestCrafters

Prepared 2026-10-02. This runbook is a release preparation artifact. It does not connect to, change, or deploy the production site.

## Current release boundary

The application is a separate Laravel project in `laravel-app/`. The imported local database is `nestcrafters_local`; the disposable test/review databases are `nestcrafters_m2_test` and `nestcrafters_m2_source_test`. Production remains outside this workspace's commands. The local build and health checks are evidence for preparation, not a release approval.

The source/export baseline remains unchanged: `nestcrafters_database.sql` SHA-256 is `3ebef325d80b514f900f462673ba40398365fbbcdb4162c20b00771469495fc5`, and the recovered legacy file manifest remains 60 files. Reconfirm both against a current owner-provided backup immediately before any cutover; this supplied snapshot is not proof of a current production backup.

## Hosting facts that must be confirmed

Record an owner and evidence for each item before using strict preflight or scheduling a maintenance window:

| Area | Required fact | Local preparation |
| --- | --- | --- |
| Runtime | PHP 8.3+ with required extensions, Composer 2.x, supported MariaDB/MySQL | Laravel 13.34.0 and PHP 8.4.10 were verified locally |
| Web root | Only `laravel-app/public` is exposed; the source workspace, `.env`, `vendor`, storage, legacy files and SQL are outside the web root | `public/.htaccess` and front controller are present |
| Mount | Keep `/contractors/` or approve a URL change; set `APP_URL` and `SESSION_PATH` consistently | Loopback subdirectory helper was previously verified |
| HTTPS | TLS termination, secure cookies, trusted proxy behavior and redirect policy | Strict preflight requires HTTPS and `SESSION_SECURE_COOKIE=true` |
| Database | Current backup, database name/host, least-privilege account, case-sensitivity setting, packet limit and enough disk | Legacy `Users`/`Jobs` names remain reserved; queue tables use `app_` names |
| Private files | Persistent writable private root outside `public`; backup and restore procedure; no public symlink | `FILESYSTEM_DISK=private`, `serve=false`, authenticated file controllers |
| Mail | Approved sender, recipient policy, SMTP/API transport, SPF/DKIM/DMARC and bounce handling | Local log/array sinks are deliberately rejected by strict preflight |
| Queue | Worker runtime, `QUEUE_CONNECTION`, failed-job store and queue alerting | Shared-host bounded worker and prefixed queue migrations are prepared; host activation pending |
| Scheduler | Cron invokes `php artisan schedule:run` every minute, with timezone agreed | Opt-in daily staged insurance reminder and minute retry/worker schedules are implemented; host cron not installed |
| Secrets | APP_KEY, database, mail, bot-provider and storage credentials injected by the host secret manager; rotation owner named | No credentials are stored in this repository |
| Monitoring | Private logs, health endpoint, uptime/queue/mail alerts, retention and access owner | `/health` is liveness-oriented and intentionally redacts infrastructure details |

## Read-only preflight

Run from `laravel-app` on the intended release checkout:

```powershell
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan release:preflight --json
php artisan release:preflight --strict --json
```

`release:preflight` does not mutate domain records. It checks the application key without printing it, debug mode, URL shape, private disk, storage writability, session cookie settings, mail/queue/bot transport, database connectivity, the migration table and the compiled asset manifest. The normal local command permits local sinks and sync queues. `--strict` is the release gate and must remain blocked until the host facts above are true. Run cache commands only on the release checkout after its environment has been injected; never cache a local `.env` into a production artifact.

After the preflight, perform a read-only HTTP check from the public host:

```powershell
Invoke-WebRequest https://<approved-host>/health
Invoke-WebRequest https://<approved-host>/.env -ErrorAction SilentlyContinue
```

Expect `/health` to return 200 JSON with `status=ok` and a direct `.env` request to return 404/403. Verify the `/contractors/` mount, compiled asset URLs, secure cookie attributes and a synthetic login before enabling real users.

## Backup and transfer procedure

1. Freeze the release commit and record the Git commit, application build manifest, migration list, SQL export digest, current database server version and UTC cutover time.
2. Take a native database backup with the host's supported tool, including schema, data, triggers, routines, events and character-set/session options. Verify it can be listed and restored into a disposable database. The supplied SQL export did not prove all of those objects.
3. Back up the private storage root and its metadata together. Verify byte counts and SHA-256 values for a sample of profile photos, documents and job media; preserve permissions and ownership.
4. Export the current application configuration names (never secret values), queue/mail status, current migration table and active session policy. Do not copy `.env` into source control or a public artifact.
5. Reconcile source and target row counts, IDs, orphan policy and private-file mappings. Do not run the guarded importer against production during a release window without a separately reviewed current-source procedure.

## Cutover sequence (requires separate approval)

1. Announce the approved maintenance window and stop new writes using the hosting layer or a temporary maintenance page. Do not improvise an in-app data freeze.
2. Take and verify the final database/private-storage backups.
3. Place the release artifact with only `public/` exposed. Install dependencies from the lock files, build assets, inject secrets, and run strict preflight.
4. Run `php artisan migrate --force` and record the output. Never use `migrate:fresh`, import commands or fixture commands on the production database.
5. Follow [Brevo/shared-host setup](brevo-shared-hosting.md): install the one-minute scheduler with its bounded database worker, or use a supervisor when supported and disable the bounded-worker switch. Confirm failed-job storage and mail transport before opening traffic.
6. Run the post-deploy checks below, then remove maintenance mode and monitor logs, queue depth, failed jobs, login errors and private-file responses.

## Rollback

If health, login, authorization, files, mail or queue checks fail, keep writes frozen. Record the failure and timestamp, stop the new worker, and restore the previous application artifact. Database rollback is migration-specific: use an approved backward migration only when it is known to be non-destructive; otherwise restore the verified database backup into a replacement database and switch the connection atomically. Restore the matching private-storage snapshot with the database version. Restart the prior worker/scheduler, rerun post-deploy checks, and keep the incident and backup evidence private. Never delete current data or run an unreviewed `migrate:rollback` to guess at recovery.

## Post-deploy checks

- `/health` returns 200 and the public `/contractors/` route/assets load without mixed content.
- Admin and contractor test accounts redirect to the correct dashboard; pending and unverified accounts see only the allowed status/verification flow.
- A contractor cannot read another contractor's profile, documents, jobs or media; authenticated preview/download responses do not expose storage paths.
- Login throttling, CSRF, secure cookie flags, upload MIME/size limits and notification inbox ownership remain active.
- A synthetic approved job can be accepted, media can be uploaded privately, and the resulting activity/notification intent appears exactly once.
- Queue worker processes one synthetic notification, the scheduler records a dry-run/expected invocation, and failed-job handling is visible to the operator.
- Database row counts, migration status, private-file checksums and error logs match the pre-cutover record.

## Current M8 status

Preparation artifacts, a read-only preflight command, build verification and this rollback/runbook are complete locally. Hosting facts, final backup evidence, owner decisions, maintenance window, strict preflight, production deployment and post-deploy sign-off remain pending. No production action is authorized by this milestone continuation.
