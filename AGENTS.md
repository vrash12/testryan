# NestCrafters Contractor System

## Project objective

Rebuild the existing NestCrafters contractor portal in Laravel with Tailwind CSS while preserving the current workflows, permissions, data, and business rules. The legacy PHP application and `nestcrafters_database.sql` are reference material for the migration.

## Source of truth

- Legacy application files: `nestcrafters_codebase/`
- Database export: `nestcrafters_database.sql`
- Existing production URL: `https://lazula.net/contractors/`
- The legacy system uses MySQL tables including `Users`, `Jobs`, `Services`, `ContractorServices`, `Job_Services`, `Job_Notes`, `Job_Media`, `Files`, `ActivityLogs`, `Templates`, and `Notes`.

Do not modify the production system during local development. Treat the legacy code as behavior to understand, not as a pattern to copy blindly.

## Required roles

The application has two roles:

- **Admin:** manage contractors, approvals, services, jobs, notes, documents, media, templates, logs, and passwords.
- **Contractor:** manage their profile and services, upload documents and profile photos, view assigned jobs, accept jobs, view job details, add job media, and read or add permitted notes.

Use Laravel authentication, authorization policies, middleware, and server-side validation. Every record lookup and mutation must enforce ownership or admin permission.

## Business rules to preserve

- New contractors start pending approval.
- Approval states are not approved, approved, and pending admin review.
- Editing a contractor profile or service selection returns the contractor to admin review.
- Jobs have separate acceptance and completion states.
- Jobs may be unassigned, pending, accepted, or completed.
- Admins can accept a job on behalf of a contractor.
- Contractors must accept a job before uploading job media.
- Job media accepts JPEG, PNG, and PDF files up to 10 MB.
- Contractor documents include W9 and insurance files with optional expiry dates.
- The admin dashboard highlights insurance documents expiring within 30 days.
- Jobs include a name, description, terms, services, due date, approved price, deposit amount, material responsibility, notes, and media.
- Activity logs record important admin and contractor actions.
- Email notifications must cover registration, approval changes, job assignment, job changes, job acceptance, completion, notes/media, and document expiry where the event is enabled.
- Registration and authentication must include anti-spam controls, rate limits, email verification, duplicate-account detection, and an admin review gate.
- Existing passwords use PHP password hashes and must be migrated without forcing a reset unless a hash is incompatible.

## Laravel implementation direction

- Use Laravel migrations, models, relationships, policies, form requests, notifications, mail, queues where useful, and Blade components.
- Implement notifications with Laravel Notifications/Mail and queue delivery where supported. Keep event creation separate from delivery and record delivery failures privately.
- Use Tailwind CSS for all new UI. Avoid adding Bootstrap to new screens.
- Preserve legacy table names during the first migration unless a mapping layer or a documented migration makes a rename safer.
- Prefer private filesystem storage for uploaded documents and media. Provide authenticated download and inline preview routes.
- Store file metadata in the database and avoid exposing storage paths directly.
- Use transactions for workflows that update a user and related services, or a job and its services.
- Use prepared Eloquent/query-builder operations. Do not interpolate request values into SQL.
- Add CSRF protection, secure sessions, authorization checks, upload MIME validation, rate limiting on authentication, and escaped output.
- Add registration throttling, email verification, CAPTCHA or an equivalent bot-control step, duplicate-account checks, suspicious-registration indicators, and safe admin bulk review/archive actions.
- Keep secrets in environment variables. Never commit database, SMTP, or FTP credentials.
- Disable `display_errors` in production and send detailed errors to private logs.

## Migration workflow

1. Read the legacy PHP files and SQL export before changing behavior.
2. Document each legacy route and map it to a Laravel route/controller/action.
3. Build the database migrations and import a copy of the exported data.
4. Implement authentication and role authorization.
5. Implement admin and contractor workflows one vertical slice at a time.
6. Verify each workflow against the legacy behavior and database records.
7. Add responsive Tailwind screens and accessible states.
8. Run feature tests for permissions, approval transitions, job transitions, uploads, and data ownership.

## Verification checklist

- Admin and contractor login redirect correctly.
- Unapproved contractors cannot access protected contractor functionality.
- A contractor cannot view or mutate another contractor's files, profile, jobs, or media.
- An admin can perform every existing admin action.
- Job acceptance and completion timestamps remain consistent.
- Upload limits, MIME checks, expiry dates, and authenticated file access work.
- Activity logs are written for important mutations.
- Existing records imported from SQL remain readable and editable.
- Mobile and desktop layouts work without horizontal scrolling.
