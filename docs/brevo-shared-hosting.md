# Brevo and shared-host background processing

## Prepared in the application

- `MAIL_MAILER=brevo` uses Symfony's maintained Brevo API transport over HTTPS. Verification, password reset, registration and enabled business notifications share this transport. Brevo's API key is distinct from an SMTP key.
- `BREVO_API_KEY` is environment-only. Local/testing environments reject the Brevo transport, including direct Mail calls. Provider calls have a 20-second total duration limit.
- Migration `000010` adds `app_queue_jobs`, `app_queue_failed_jobs` and `app_queue_batches`. It does not rename or alter legacy `Jobs`.
- Database jobs fan out to one delivery per worker job. Delivery row locks, current-recipient/approval checks, token expiry checks and staged reminder deduplication remain in place. A five-minute unique-job lock avoids repeated retry enqueues. Worker crashes after provider acceptance can still cause ambiguous duplicates; this is not exactly-once delivery.
- The opt-in schedule checks due failed/pending delivery records each minute and runs a bounded database worker. Insurance reminders run daily at the configured business time, using the existing 30-day, 7-day, due and recently-expired stages and existing administrator recipient rules.
- No provider account, API credentials, DNS records, host cron, or external mail were configured by this local change. The user selected shared hosting and deferred sender, test inbox and timezone configuration.

## 1. Brevo account and DNS

In Brevo, add the sending domain, complete the DNS authentication records shown for that domain, and verify the chosen sender. Preserve existing mail DNS records; do not create a second SPF record. Confirm DKIM/DMARC status in Brevo. Create a transactional API key and enter it only through the host's protected environment configuration or the non-public `.env` file. Do not put the key in chat, source control, a URL, or the public directory.

Official references: [Brevo transactional API](https://developers.brevo.com/docs/send-a-transactional-email), [Symfony Brevo integration](https://symfony.com/doc/current/mailer.html).

## 2. Hosting prerequisites and settings

Ask the hosting provider for the PHP CLI executable path and confirm PHP 8.3+, required Composer extensions, HTTPS requests to `api.brevo.com`, one-minute cron, process execution for Laravel scheduled commands, and sufficient process runtime. PHP PCNTL support is needed for enforced worker timeouts; the provider HTTP timeout still bounds calls. A host that forbids worker processes or only permits infrequent cron cannot provide this setup's expected one-minute mail processing. Do not claim that the worker is installed until host execution is observed.

Use the approved deployed Laravel directory outside the public web root; expose only its `public/` directory. Add these settings to the host environment, retaining its existing APP_KEY, database credentials and other configuration:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-APP-HOST/contractors
APP_TIMEZONE=YOUR_BUSINESS_IANA_TIMEZONE
MAIL_MAILER=brevo
BREVO_API_KEY=YOUR_PRIVATE_API_KEY
MAIL_FROM_ADDRESS=YOUR_VERIFIED_SENDER
MAIL_FROM_NAME="NestCrafters"
QUEUE_CONNECTION=database
DB_QUEUE_TABLE=app_queue_jobs
DB_QUEUE_RETRY_AFTER=90
CACHE_STORE=file
NESTCRAFTERS_BUSINESS_NOTIFICATIONS=true
NESTCRAFTERS_BACKGROUND_ENABLED=true
NESTCRAFTERS_SHARED_HOST_WORKER=true
NESTCRAFTERS_INSURANCE_SCHEDULE=true
NESTCRAFTERS_INSURANCE_TIME=09:00
```

Replace placeholders. Choose the reminder time and timezone before enabling it; 09:00 is an editable default. Verification/password resets are independent of the business-mail switch. Business event switches and recipient preferences still apply. Start staging with synthetic accounts only: the retry sweep processes existing eligible pending/failed deliveries, so review the backlog before first activation. Do not enable production delivery against an unreviewed local import.

After deploying through the existing release/backup procedure, use the host's PHP CLI in the Laravel directory:

```sh
php artisan migrate --force
php artisan config:cache
php artisan schedule:list
php artisan nestcrafters:brevo-check
php artisan release:preflight --strict
```

The Brevo check does not contact the provider unless `--send-to` is given. Preflight checks configuration/table presence; it cannot prove a cron installation, DNS verification, API validity or inbox delivery.

## 3. cPanel cron

Set minute, hour, day, month and weekday to `*` (every minute). Use absolute paths supplied by the host. The following is a template, not an installed command:

```sh
cd /home/ACCOUNT/nestcrafters && /ABSOLUTE/PHP/PATH artisan schedule:run >> /home/ACCOUNT/nestcrafters/storage/logs/scheduler.log 2>&1
```

Keep that log private and arrange rotation. Use the same hosting user for cron, cache and web requests. One cron entry is sufficient: reminders enqueue, the retry sweep enqueues due deliveries, then the worker drains `default` jobs and stops when empty or after roughly 45 seconds (an in-flight job may finish after that). Laravel cache locks prevent overlapping copies of each scheduled task. Do not also add an independent queue cron or another scheduler on this single-host configuration. This uses a persistent database queue with short-lived workers, not a permanently running daemon. Normal delivery may wait about a minute, longer during backlog/provider throttling.

If the host supports Supervisor later, disable `NESTCRAFTERS_SHARED_HOST_WORKER` and manage `php artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=30` with the supervisor. Retain the scheduler cron. Keep worker timeout below the 90-second reservation retry period and restart workers after deployments.

## 4. Live acceptance checks (still pending)

1. On a correctly configured staging deployment, send one synthetic message to the chosen test inbox:
   `php artisan nestcrafters:brevo-check --send-to=YOUR_TEST_INBOX`.
2. Confirm receipt and spam placement. Brevo API acceptance and the app's historical `delivered` status mean transport handoff, not inbox receipt.
3. With synthetic accounts, exercise real registration/verification, reset-password request and completion, approval/job notifications, and one insurance reminder. Check correct HTTPS/subpath links, recipient rules and deduplication on a repeat reminder.
4. Observe cron logs across at least two minutes; verify queue rows drain and the administrator delivery view shows successful handoff. Check `php artisan queue:failed` for infrastructure failures. Notification transport failures live in the separate delivery table and are retried with the existing exponential backoff.
5. Check Brevo's transactional logs for delivery/bounce/blocked status, suppress invalid recipients and monitor quota. Free-plan sending is capped at 300/day; delayed reset links can expire. Automated Brevo bounce/complaint webhooks are not part of this integration yet; provider-log monitoring is required for rollout.
6. Remove cron or disable background settings and rebuild config cache to pause scheduled work. Disabling business mail prevents new enabled business delivery; it does not disable verification/password recovery. Do not switch to a log fallback and mistake it for a successful live delivery.

Live activation awaits the user's provider key, verified sender, test inbox, hosting path/PHP details and business timezone/time. No production change is included here.

## Brevo account inspection — 2026-10-02

The logged-in Brevo account has no API keys. Its sole verified sender is `Sample <trial@test-site-ryan.com>`, with the default DKIM signature and a DMARC warning that the `rua` tag is missing. Opening the API key creation flow reveals a required phone-verification dialog; the user must complete this before a key can be created. The local ignored `.env` now uses that verified sender address and the NestCrafters display name, while retaining `APP_ENV=local`, log mail and disabled background scheduling. `BREVO_API_KEY` remains empty pending phone verification and confirmation to create a credential. No real mail was sent. Domain authentication and a final branded sender still need completion before live activation.

## Local verification — 2026-10-02

The user supplied a Brevo API key, which has been stored only in the local ignored `laravel-app/.env`; the configuration cache was cleared. Local log mail remains enabled and no provider request or real email was sent. Since the credential was shared in chat, rotate it in Brevo before live use and replace the local/host environment value privately. Do not record the credential in documentation.

- 41 targeted tests / 409 assertions passed across Brevo transport, shared-host schedules, actual database queue processing, identity delivery/recovery, business notification eligibility, inbox/preferences and staged reminders. API calls used Symfony MockHttpClient; mailbox receipt is not claimed.
- Real database worker tests used only the disposable m2_test schema and confirmed all three mail families drain from the queue, retries deduplicate, and stale reset tokens are skipped.
- Composer strict validation/platform requirements, Pint and PHP syntax passed. Composer reported no vulnerability advisories during dependency installation.
- Migration 000010 was applied to the local main database, adding only the three queue tables. Local defaults remain log mail/sync queue/disabled background schedules; the read-only Brevo check correctly reports missing hosting configuration.
- The SQL reference digest remains unchanged. No legacy or production files, provider settings, DNS or real email recipients were changed.
