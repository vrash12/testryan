# M3 — Authentication and permissions

Delivered locally: 2026-10-01 (Asia/Shanghai). Application: `laravel-app/`. The user authorized M3 after M2 and selected **account status and email verification only** for pending contractors. Production, the 60 legacy files, and the SQL export remain unchanged. M4 was not started at this checkpoint; its completed implementation is documented in [the M4 report](m4-contractor-workflows.md).

## Access contract

An active account can sign in with its existing compatible PHP password hash. Login rotates the session; logout invalidates it and regenerates the CSRF token. Database role, approval, lifecycle and session version are refreshed on each protected request. Unknown roles fail closed. A password reset increments the account's session version, ending its existing sessions on their next protected request.

| Account | Available after signing in |
| --- | --- |
| Unverified admin | Own status, verification and logout |
| Verified admin | Admin shell, account access list and controlled password setting |
| Unverified contractor, any approval | Own status, verification and logout |
| Verified contractor, approval 0 or 2 | Own status and logout; verification does not approve the account |
| Verified contractor, approval 1 | Contractor shell; policies permit only owned resources |
| Blocked, archived or unknown-role account | No login/recovery or protected access |

Approval values retain their legacy meanings: 0 = Not approved, 1 = Approved, 2 = Pending admin review. M3 creates new contractors at 0 and enforces all three states. Approval/profile/document editors belong to M4. Pending contractors have no profile, service, document, job or media access after sign-in, including through policies. The guest registration form may read selectable service names.

All **33 imported accounts remain unverified**, including both admins. No email proof, password reset, approval or lifecycle change was manufactured. The unanswered imported-verification preference uses the existing verification requirement and the documented secure default; a production rollout exception still needs an explicit decision. Actual legacy plaintext passwords are unavailable, so compatibility is proven with controlled fixtures and recognized source hashes.

## Implemented routes

Paths are relative to Laravel's public mount. There are 22 application routes, counting GET and POST separately; Laravel also supplies `/up`.

| Method | Path | Boundary / purpose |
| --- | --- | --- |
| GET | `/`, `/health` | Public landing and health |
| GET | `/login` | Guest sign-in form |
| POST | `/login` | Guest, CSRF, layered login throttle |
| GET / POST | `/register` | Guest service selection and atomic pending registration; bot check and throttles |
| GET / POST | `/forgot-password` | Guest recovery request; generic response, throttle |
| GET | `/reset-password/{token}` | Guest reset form |
| POST | `/reset-password` | Guest, hashed expiring single-use token and validated password |
| POST | `/logout` | Authenticated, CSRF; remains available to end a revoked session |
| GET | `/dashboard` | Current account; redirects to status or eligible role shell |
| GET | `/account/status`, `/verify-email` | Current account's approval and verification status |
| POST | `/email/verification-notification` | Current account, throttled resend |
| GET | `/verify-email/{id}/{hash}` | Current account, signed expiry, matching user/email, throttle |
| GET | `/admin` | Current, verified admin |
| GET | `/admin/accounts` | Verified admin, User policy; paginated account security list |
| GET / POST | `/admin/accounts/{user}/password` | Verified admin, bound target policy; current admin password required for POST |
| GET | `/contractor` | Current, verified contractor with approval 1 |
| GET | `/local/preview/{role}` | Existing local flag only; unauthenticated empty shell, no domain records |

Generated auth links use the configured application origin. Login discards intended redirects outside that origin/mount. The `/contractors/` helper checks the external path locally; it is not a production web-server configuration.

## Database additions and preservation

Migration `2026_10_01_000004_create_identity_and_notification_tables` adds four `app_` tables. `nestcrafters_local` now contains 19 tables, including `app_migrations`. All 77 legacy columns and 412 source rows are unchanged; no second users table or framework jobs table exists.

| Table | Key and relationships | Purpose |
| --- | --- | --- |
| `app_account_states` | Signed INT `user_id`, one-to-one restrictive FK to `Users.user_id` | Verified timestamp/address, session version, block/archive timestamps, private review signals and HMAC of registration IP |
| `app_password_reset_tokens` | Email primary key | Laravel broker's hashed token and creation time; 60-minute expiry |
| `app_notification_events` | ULID; signed INT subject FK to Users; unique deduplication digest | Durable type, subject, encrypted URL/token payload and creation time |
| `app_notification_deliveries` | ULID; restrictive event and recipient FKs; unique event/recipient | Recipient snapshot, pending/failed/delivered/skipped status, attempts, retry time and private error class |

The migration initializes identity rows for existing users without proof. A first import performed after all migrations also creates unverified identity rows inside its transaction, without model events or notification records. A verified import rerun does not change later identity state. This covers both upgrading the populated M2 database and reproducing the application on an empty local database.

The migration was applied only after the local connection guard and baseline reconciliation, then the baseline reconciled again. All 33 bcrypt source hashes, custom IDs/counters, original text/calendar/decimal/null values, and four private copies (2,899,381 bytes) remain intact. Mail events and reset tokens on the imported target remain empty. Source staging stays SELECT-only. Routine setup must not use `migrate:fresh` or rollback on that target.

## Identity implementation

Laravel's web guard uses `LegacyUserProvider` and mapped `Users/user_id/password_hash`. PHP `password_verify` supports compatible legacy hashes; a successful login may rehash to the configured cost. Failed login never rehashes. Compatible historical credentials beyond bcrypt's representable length keep their original hash rather than undergoing an unsafe rehash. New passwords require at least 12 characters, no NUL bytes and at most 72 UTF-8 bytes; password managers and paste work. No remember-token or updated-at column is invented.

Registration uses bounded FormRequests, database email uniqueness, an explicit contractor role/approval/priority, and one transaction for user, selected services, review signals, activity log and notification intents. Submitted admin/approval/owner/priority fields are prohibited. Exact normalized company matches and empty service selection become private review signals; they do not automatically reject or delete an account. Duplicate emails are rejected even when a concurrent request reaches the database constraint. Failed registration rolls back identity, pivots, audit and intents.

Verification links expire after 60 minutes, require the signed-in matching account and current email digest, and never change approval. Changing the email invalidates saved proof and old signed links. Recovery gives the same visible response for unknown/ineligible addresses, stores only a hashed token, allows the narrowly scoped unverified-address exception, and cannot reactivate blocked/archived accounts. Password mutation and token consumption are transactional and serialized.

The admin account list exposes only selected contact/security fields through the admin policy. Admin password setting preserves legacy capability with a validated current administrator password, server-bound target, session revocation and actor/target activity logging. Self-reset signs the administrator out. Passwords and reset URLs are absent from activity records, old input and public responses. Invitation, managed-contact editing, MFA and lifecycle/bulk-review screens remain later work.

## Policy foundation

These policies are registered through Laravel discovery. Resource CRUD/download/upload endpoints are later milestones, so policy tests use explicit test-only HTTP probes; they do not establish finished M4/M5 workflows.

| Policy / model | Verified active admin | Verified active approved contractor |
| --- | --- | --- |
| User | Read/manage eligible targets; password setting; contractor approval/deletion capability | Own profile only; no other user or directory |
| Job | Read/manage; accept on behalf; completion capability | Current assignment read/accept; media creation requires acceptance |
| ContractorDocument | Read/create/update/delete under parent scope | Own document read/create; no archive/delete capability |
| JobMedia | Read/create/delete under correct job parent | Current authorized assignment read; accepted owned job creation |
| JobNote | Read/manage under correct job parent | Read own assigned-job notes; writing denied pending D2/M5 |
| ContractorNote | Admin only | Denied |
| Service | Catalog management | Selectable catalog read |
| Template | Admin only | Denied |
| ActivityLog | Read; no arbitrary mutation/delete | Global log access denied |
| ImportedPrivateFile | Authorized mapped source record, private disk, recognized column | Own authorized photo/document/media source only |

Policies validate both ownership and a supplied parent. Even admins cannot resolve a child through a mismatched parent. Reassignment removes the former contractor's access immediately; re-review removes protected contractor access. Unknown private-file mappings or public disks fail closed. Transition validation, file MIME/size validation, references/retention and audit/intents must accompany later actions; a broad admin policy is not a substitute for those workflow checks.

## Registration protection and local configuration

| Control | M3 default |
| --- | --- |
| Login | 5/minute email+IP, 10/minute email, 20/minute IP |
| Registration form | 20/minute IP |
| Registration submission | 5/hour IP, 2/hour email, 30/hour global |
| Recovery request | 5/hour IP, 3/hour email |
| Password reset | 5/minute IP |
| Verification resend | 1/minute and 6/hour account |
| Verification link | 6/minute account |
| Admin password setting | 5/minute actor |
| Bot envelope | Honeypot, one-use session token, 2-second minimum age, 15-minute lifetime |

Email identifiers in throttle keys are digests. IP cache state and registration signals remain private. Failed requests count toward throttles; these are configurable implementation defaults, not permanent account locks. Safe friendly 419/429 pages, Retry-After and no-store headers remain enabled.

Environment keys added to `.env.example`: `REGISTRATION_ENABLED`, `REGISTRATION_ADMIN_MAIL`, `REGISTRATION_BOT_DRIVER`, `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`. Secrets remain in ignored `.env`. Local/testing defaults use an accessible arithmetic challenge plus the envelope and approval/verification gates. Public environments fail closed without a configured provider. The Turnstile adapter validates success, hostname and registration action server-side and handles provider failure; only HTTP fakes were used. Live provider keys, real hostname, quotas and risk retention are still release configuration decisions.

## Notifications and failure recovery

M3 implements `email.verification`, `password.recovery` and `registration.created`. Security mail may reach an eligible unverified account without granting work access. Registration notices go only to currently verified, active admins when enabled. If none are eligible, the event remains recorded with zero deliveries; there is no implicit future backfill. Remaining business events, preferences, expiry schedules and bulk review belong to M4.5 alongside their workflows.

Intents/deliveries are durable database records; the mutation commits before dispatch. Delivery uses a queue-compatible job with the existing synchronous local queue. Dispatch failures leave pending work; transport failures retain an error class and backoff privately. Event payloads use Laravel encryption under `APP_KEY`; never regenerate that key casually. Mail logs contain usable security links and must remain private.

```powershell
php artisan nestcrafters:retry-identity-mail
```

This command retries up to 100 due pending/failed deliveries. Delivery rechecks recipient address/lifecycle, current admin eligibility, link expiry and recovery-token validity. Verified addresses, changed recipients, expired links and revoked tokens are skipped. Local/testing identity mail refuses external transports and uses `log` or `array` only. No scheduler or database queue worker/table is introduced in M3. Future hosting must provision queue infrastructure explicitly with nonconflicting names.

`delivered` means transport handoff; locally it means capture, not arrival in an external mailbox. SMTP/crash ambiguity still precludes an exactly-once promise. Failure recovery and deduplication tests do not establish production SMTP delivery.

## Validation and limits

The full sequential suite passed **63 tests / 722 assertions**, covering foundation, parser/import and identity behavior. After the final first-import identity initialization, all **14 import tests / 292 assertions** passed again. Destructive resets are guarded and restricted to the two named disposable test schemas, using their dedicated limited account. Exported private records and plaintext credentials are not test fixtures.

Coverage includes both role logins, legacy cost compatibility and long credentials, session/CSRF rotation, approval 0/1/2, email/lifecycle changes, stale-session rejection, generic recovery, signed/expired/tampered/wrong-user links, one-use reset tokens, admin password controls, injected fields, bot failures, duplicate email/company handling, transaction rollback, rate limits, cross-owner/nested/reassigned records and durable mail retries/revoked-token skips.

Formatter, Composer validation/platform checks, asset build and Blade compilation pass. After final navigation/layout changes, the 11 foundation tests / 65 assertions passed again. Real HTTP checks pass health, reject missing CSRF with 419, return 404 for private paths and retain canonical auth links under a hostile Host header. The `/contractors/` helper passes login/form origin, compiled CSS, cookie scope and CSRF checks. Browser emulation checks public forms, own status, admin security and the approved contractor shell at 320/375/390/430/768/1280px. Authenticated QA uses synthetic users on a temporary disposable server/database; no imported password/approval changes. A synthetic verification message passes through Laravel Notifications into a private log, with no live SMTP or bot-provider request. Both role menus link to own Account status; the selected pending page still offers only verification/status/logout. [Mobile sign-in capture](m3-login-mobile.png).

Native iOS/Android keyboards, camera/file pickers, actual legacy plaintext login, live bot-provider behavior, job workflows and production mail remain unverified. Missing reference handlers, D2–D10 decisions, current source/export completeness, business timezone/currency, retention, sender/recipients and hosting/public-root/queue/scheduler facts remain as documented. The M3 checkpoint did not approve those decisions; see the M4 report for the subsequent contractor/document slice.

Framework references used for this implementation: [Laravel authentication](https://laravel.com/framework/docs/13.x/authentication), [verification](https://laravel.com/framework/docs/13.x/verification), [password recovery](https://laravel.com/framework/docs/13.x/passwords), [authorization](https://laravel.com/framework/docs/13.x/authorization), and [Turnstile server verification](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/).
