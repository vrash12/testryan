# M1 local foundation report

Completed: 2026-10-01 (Asia/Shanghai). Authorization: the user explicitly requested M1 only after the audit/review. Application files are confined to laravel-app; root documentation and .gitignore were updated. No production requests/changes, legacy changes, SQL restore/import, domain workflows or deployment occurred. M0 recovery/restore and D1–D10 decisions remain open.

This is the historical M1 checkpoint. The user subsequently requested M2, which now supplies the verified local schema/import described in [M2 report](m2-data-migration.md). Current setup is in laravel-app/README.md; M3 and production work remain pending.

## Version confirmation before implementation

| Runtime | Confirmed local version | Selection/check |
| --- | --- | --- |
| PHP | 8.4.10 CLI, ZTS x64 | C:/xampp-new/php/php.exe; required extensions present |
| Composer | 2.8.12 | Package platform requirements pass |
| Node | 24.13.1 | Asset installation/build pass |
| npm | 11.8.0 | npm ci lock format; install/build/audit verified |
| Database | MariaDB 10.4.32 server and client | TCP 127.0.0.1:3306; lower_case_table_names=1 |
| Framework | Laravel 13.34.0, skeleton 13.0.0 | composer.lock pins dependencies |
| UI | Blade, Tailwind 4.3.3 + Vite plugin 4.3.3 | No Bootstrap, SPA framework, remote fonts or CDN runtime |
| Assets | Vite 7.3.6 + Laravel Vite plugin 2.1.0 | package-lock.json pins dependencies |

Framework selection used [official Laravel requirements](https://laravel.com/framework/docs/releases) and [official Tailwind Vite setup](https://tailwindcss.com/docs/installation/using-vite). Composer checks the installed dependency graph against the actual PHP runtime. No production runtime compatibility is claimed.

## Delivered structure

| Area | Files/behavior |
| --- | --- |
| Routes | routes/web.php: landing, health, sign-in view, protected dashboard/role shells, logout, local-only previews; Laravel /up liveness |
| Authentication structure | Laravel web session guard/provider; User maps Users, user_id and password_hash; no remember token, factory, seeded account or credential submission endpoint |
| Authorization | EnsureRole middleware; unknown/missing roles fail closed; contractors require approval 1; LocalShellPreview requires local+flag and does not authenticate |
| UI shell | Guest and role-aware app layouts; desktop sidebar; native collapsible mobile menu; Dashboard/More phone shortcuts; disabled feature placeholders |
| Shared Tailwind components | Branding/icons, buttons, cards, badges, alerts, fields, error summary/flash feedback, empty states, navigation and head/layouts |
| Error handling | Friendly403/404/419/429/500/503 plus generic4xx/5xx; APP_DEBUG=false; raw PHP error display disabled; details in private daily logs |
| Local database | New empty nestcrafters_m1 schema and dedicated loopback-only user; random credential only in ignored.env; root account/server configuration unchanged |
| Schema boundary | No migrations executed; default users/jobs/cache migrations removed before execution; app_migrations and app_queue_* names reserved; empty seeder |
| Storage | Default private and local alias outside public; serve=false; no public link/download/upload route |
| Sessions/cache/queues | Encrypted file sessions; dedicated nestcrafters_session cookie, HTTP-only/Lax; file cache; sync queues; no worker or framework DB tables |
| Mail | Laravel log mailer to private storage/logs/mail.log; local synthetic test command refuses nonlocal/external transports |
| Health | Local dependency checks and503 on failure; generic JSON; nonlocal public liveness without DB/infrastructure disclosure |
| Local setup | README with versions, fresh-machine setup, database provisioning, development commands and mount test helper |

No fake counts, contractors, jobs or credentials are presented. Shell cards show an unavailable marker. Preview banners identify their lack of live data. Sign-in inputs and actions are disabled with an explanation; real account workflows belong to M3 after M2.

## Local route review

| Method/path | Access | Result |
| --- | --- | --- |
| GET / | Public | Landing; preview links appear only in local with flag |
| GET /login | Guest | Sign-in structure; signed-in users redirect to dashboard |
| GET /dashboard | Authenticated | Role redirect; unknown role403 |
| GET /admin | Authenticated admin | Empty admin shell; other roles403 |
| GET /contractor | Authenticated contractor, approval 1 | Empty contractor shell; pending/review/unknown approval403 |
| POST /logout | Authenticated + CSRF | Logout, invalidate session, regenerate CSRF token |
| GET /local/preview/admin or contractor | Local environment + flag | Empty role preview; no identity/session authorization granted |
| GET /health | Public | Local checks; nonlocal application liveness only |
| GET /up | Public | Laravel liveness |

There are no registration, login POST, record mutation, upload, storage download or legacy PHP routes. Policies for domain records will be added with their resources in later milestones.

## Verification evidence

- 13 Laravel feature tests (71 assertions) passed in the first verification pass: guest boundaries, both role shells/redirects, unknown role denial, unapproved contractor denial, local preview gating/non-authentication, logout, generic public health, local dependency failure, private error handling, private-storage serving boundary, subdirectory route/cookie behavior, and local mail guard/message intent.
- npm run build succeeds with a small CSS/JS bundle; Blade template compilation succeeds.
- Composer platform checks pass; composer validate --strict passes after refreshing the lock metadata; Composer reported no known advisories. Full npm audit reports zero advisories at this checkpoint.
- Actual MariaDB connection confirms version 10.4.32 and zero tables in nestcrafters_m1. No SQL export is executed.
- A synthetic Laravel mail message was captured in storage/logs/mail.log; no external send. MAIL_MAILER=log is the provided local mail testing mechanism; Mailpit is optional and not installed.
- Loopback HTTP: /health and /up200; .env, SQL snapshot and private storage path404; POST logout without CSRF419 with the friendly session-expiry page.
- A separate loopback /contractors/ helper returned200, generated mount-aware links/assets, served the compiled CSS successfully and scoped the dedicated session cookie to /contractors. Framework subdirectory tests also pass. This does not prove the eventual hosting configuration.
- Browser emulation at 320,375,390,430,768 and1280px: landing, login structure, health and both role previews fit without page horizontal overflow. The 320px contractor and desktop admin shells were visually inspected. Mobile More opens navigation, moves focus to the native summary and synchronizes expanded state; Enter closes it. Remaining native-device/screen-reader/full-workflow checks are deferred.
- Git ignore checks confirm .env, dependencies and private reference files are excluded. Final handoff checks match all 60 legacy files to their original sizes/SHA-256; the SQL SHA-256 remains 3ebef325d80b514f900f462673ba40398365fbbcdb4162c20b00771469495fc5.

## Remaining boundaries and decisions

M1 is a runnable local foundation, not the rebuilt contractor system. M2 requires an authorized, verified isolated restore/import and exact schema mapping. M3 requires actual credential handling, hash compatibility, verification, rate limits, bot control and an agreed pending-account experience. No working account is supplied or inferred from legacy data.

Business timezone/currency, note visibility, reassignment consent, upload/re-review details, notification recipients/schedules, retention/deletion and hosting facts remain in [review.md](review.md). APP_TIMEZONE=UTC is a provisional local default. The user has not approved every D1–D10 proposal by requesting M1. A future HTTPS host must configure secure cookies, the correct public mount, private storage permissions, DB/mail and queue/scheduler support. No deployment is authorized.

Reproduction instructions: [application README](../laravel-app/README.md). Milestone status: [milestones](../milestones.md). Stop after M1; subsequent milestones require a new instruction.
