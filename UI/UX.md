# NestCrafters UI/UX Direction

## Goal

Create a clear, modern Tailwind interface for the contractor management portal while keeping the existing feature set familiar to current users. The interface should make status, next actions, permissions, and deadlines easy to understand. Phones are a primary device: contractors should be able to manage assigned work from a job site, and admins should be able to manage approvals and jobs from their phones.

## Visual language

- Use a restrained neutral palette with one primary brand color and semantic colors for approval, warning, error, and completion states.
- Use consistent spacing, compact 4–8 px corners, solid outlines, hard offset shadows, and readable typography following the mini neo-brutalist direction below.
- Use status badges with text and color; never communicate status through color alone.
- Use icons with visible labels or accessible names.
- Keep forms and tables visually calm. Avoid dense Bootstrap-style pages.
- Design mobile-first, then enhance for desktop.

## Brand reference and mini neo-brutalist direction — 2026-10-01

The public [NestCrafters reference site](https://nestcrafters.com/) establishes the visual anchor: deep blue-green navigation, bright construction orange-red calls to action, white space, cool blue-gray image overlays, bold condensed headings, and direct service-first messaging. The portal should carry those recognizable cues into a compact mini neo-brutalist system rather than reproducing the marketing site or its imagery.

### Typography update — 2026-10-02

The portal uses **DM Sans Variable** as its shared local typeface. It replaces the browser-dependent system stack across body text, navigation, forms, buttons, tables, and headings. DM Sans keeps the bold, practical tone of the mini neo-brutalist system while improving lowercase readability and reducing platform-to-platform layout shifts. The variable font is bundled through `@fontsource-variable/dm-sans`, so the local review server and the future production asset build use the same font files without a third-party request. Italic faces are available for notes and supporting copy. Print-only legacy views may continue to use a system fallback until their typography is brought into the shared shell.

### Color tokens

Use these as starting tokens for the Tailwind theme. They are visual approximations from the live reference and must remain subject to contrast testing against actual text sizes and states.

| Token | Value | Use |
| --- | --- | --- |
| `brand-ink` | `#062F3D` | Header, sidebar, primary text, dark panels |
| `brand-deep` | `#0B4658` | Hover, active navigation, secondary dark surfaces |
| `brand-orange` | `#E84B2A` | Primary calls to action, important links, attention accents |
| `brand-orange-dark` | `#B93620` | Hover/pressed orange and dark-on-light emphasis |
| `paper` | `#FFFDF7` | Page background and cards |
| `mist` | `#DCE5E7` | Cool neutral panels, disabled surfaces, image-backed sections |
| `signal-yellow` | `#F5C84B` | Pending/review attention, with text labels |
| `signal-green` | `#287A5B` | Approved/completed state, with text labels |
| `signal-red` | `#B8322A` | Errors/blocked/expired state, with text labels |
| `outline` | `#141A1D` | Borders, focus rings, and text on orange/yellow |

### Shape, depth, and type rules

- Use near-square cards with a small 4–8 px radius. Reserve larger rounding for media or an explicitly friendly onboarding state.
- Use solid 2 px `outline` borders and a hard offset shadow such as `4px 4px 0 #141A1D` on primary cards, buttons, dialogs, and selected filters. Avoid soft drop shadows and glass effects.
- Keep one strong visual block per view: a dark header, orange action, or yellow review strip. Do not make every card loud.
- Use oversized, tight, high-weight headings for page titles and short labels. Pair them with plain readable body text; do not use all caps for long passages.
- Keep layouts slightly asymmetric through a featured action, offset callout, or alternating card alignment while preserving a predictable reading and keyboard order.
- Use texture through borders, labels, and construction-inspired microcopy. Do not add decorative illustrations that compete with job data, forms, or deadlines.

### Component treatment

- Buttons use a solid fill, dark outline, short uppercase label where appropriate, and a pressed state that removes the offset shadow while moving the control 2 px toward the shadow.
- Inputs and filters use white/paper fills, dark 2 px borders, visible labels, and an orange focus ring paired with a dark outer outline. Error fields add a red border and text explanation.
- Status badges combine a short text label with the semantic color. Approval, verification, job, and document state must remain understandable in grayscale.
- Tables become bordered cards on phones. Keep the record name, state, due date, and primary action in the first block; place secondary fields behind an explicit disclosure.
- Keep the role shell dark and the page canvas light. Use orange for the next action, yellow for review, and green only for confirmed completion.

### Guardrails

- Preserve WCAG contrast, 44 px touch targets, visible focus, reduced-motion behavior, and the existing keyboard/mobile requirements.
- Do not use the live site's logo, character illustration, photographs, or third-party content in the authenticated portal without an approved asset/license decision.
- Keep all status color paired with text, icons, or structure. Neo-brutalist styling is an information hierarchy, not a reason to reduce accessibility or hide controls.

## Shared application shell

- Responsive top bar with NestCrafters branding, page title, role-aware navigation, notifications, and account menu.
- Desktop sidebar for primary navigation; collapsible mobile navigation.
- Consistent breadcrumbs or back links for nested pages.
- Main content constrained to a readable max width.
- Flash messages for successful, failed, and pending actions.
- Confirmation dialogs for destructive actions.
- Loading, empty, error, and success states for every data-driven view.

## Admin experience

### Dashboard

Show approval queue, jobs by status, upcoming due dates, expiring insurance documents, recent activity, and quick actions. Make each summary card link to its filtered detail view.

### Contractors

Use a searchable, filterable table with company, contact, services, approval status, priority, document status, and last activity. The contractor detail screen should group profile, services, jobs, files, notes, and activity into clear tabs or sections.

### Jobs

Use a table or card view with job name, contractor, due date, status, price, and last update. The job detail page should show a clear status timeline, job information, services, financial details, terms, notes, and media. Keep accept, complete, edit, and delete actions visible only when authorized.

### Services, templates, and logs

Use simple management screens with inline search, clear add/edit forms, usage counts, and safe deletion rules. Logs should support filters for user, action, and date.

## Contractor experience

### Registration and profile

Use a step-based or clearly grouped form for personal details, company information, contact numbers, services, password, and profile photo. Explain that profile or service changes require admin review.

### Dashboard

Lead with approval status, profile completeness, assigned jobs, upcoming due dates, expiring documents, and the next recommended action. Use cards for urgent items and a compact job list for routine work.

### Job details

Place the job status and due-date urgency at the top. Use a timeline for acceptance and completion, then show description, services, terms, financial details, notes, and media. Make the accept-job action prominent until completed.

### Documents and media

Show document type, filename, upload date, expiry date, and status. Explain accepted file types and size limits before upload. Provide preview, download, replace, archive, and delete actions according to permissions.

### Notifications

Provide a notification center or inbox with unread count, event type, date, related record, and a direct link to the relevant job or contractor. Add clear email preference controls for admins and contractors where the event is configurable. Show delivery or processing errors to admins without exposing SMTP details. Keep urgent approval, assignment, and expiry items visible on the dashboard.

### Registration and spam review

Make the registration form clear about approval and email verification. Include a visible verification state, useful errors, rate-limit feedback, and a bot-control step that remains usable on phones. In the admin review queue, show registration date, email/domain, company, selected services, missing documents, duplicate indicators, and risk signals. Use explicit Review, Approve, Reject, Archive, and Block actions with confirmation for destructive or bulk operations.

## Forms and interaction rules

- Label every field and show inline validation near the error.
- Preserve entered values after validation failures.
- Explain irreversible actions before confirmation.
- Use optimistic feedback only after the server confirms success.
- Disable duplicate submissions while a request is in progress.
- Use paginated tables for large collections.
- Provide search and filters without losing the current filter state.
- Use keyboard-accessible dialogs, menus, tabs, and upload controls.
- Maintain visible focus states and sufficient color contrast.

## Responsive behavior

- Implement Tailwind styles from the smallest viewport upward. Use content fit to choose breakpoints, starting with phone layouts below 640 px, tablet layouts from 640 px, and expanded desktop layouts from 1024 px.
- Support widths from 320 px upward, portrait and landscape orientation, browser text enlargement, and mobile browser controls changing the available height.
- Preserve every authorized workflow on mobile, including admin actions. Do not hide required functionality at smaller breakpoints.
- Replace wide tables with labeled cards on phones. Show the record name, status, deadline, and primary action first; place secondary details in an expandable section.
- Keep search visible above lists. Open advanced filters in an accessible sheet with Apply, Clear, and an active filter count. Preserve filters and scroll position when returning from a detail page.
- Use one-column forms on phones and grouped columns on larger screens. Maintain a logical reading and tab order.
- Scale media previews within the viewport. Wrap long filenames, company names, and addresses without overlapping controls.
- Avoid horizontal page scrolling. A deliberately scrollable component must have a clear affordance and remain keyboard accessible.

## Phone navigation and touch interaction

- Give contractors a compact bottom navigation for Dashboard, Jobs, Documents, and Account. Give admins Dashboard, Contractors, Jobs, and More. Show only implemented destinations that the current role can access.
- Keep desktop navigation in a sidebar. On phones, use a clearly labeled More menu for services, templates, activity logs, and other secondary destinations.
- Highlight the active destination and retain a visible page title and back link on detail screens.
- Make buttons, menu items, checkboxes, and icon controls at least 44 by 44 CSS pixels, with spacing that prevents accidental taps.
- Use visible controls for all actions; hover must never be required. Avoid swipe-only and drag-only interactions.
- Keep the main job action within easy reach using a bottom action bar when useful. Add content padding and device safe-area insets so navigation and actions do not cover content.
- Adapt fixed controls when the keyboard opens. Users must be able to see the focused input, validation messages, and submit button.
- Reserve the primary action for the next valid step, such as Accept Job or Save Changes. Put destructive and infrequent actions in a labeled secondary menu.

## Mobile forms and readable content

- Use at least 16 px text for inputs and body content. Use comfortable line height and persistent labels; placeholders provide examples only.
- Set appropriate HTML input types, inputmode, and autocomplete values for email, telephone, password, dates, and currency. Show currency units beside amounts.
- Use a searchable service selector with large selectable rows and visible selected items. Do not require precise taps on small checkboxes.
- Group long registration and job forms into understandable sections. If using steps, show progress, allow Back without losing values, and validate before advancing.
- Put an error summary at the top of a failed form with links to invalid fields. Keep field errors beside the controls and bring the first error into view.
- Allow password managers and pasted passwords. Provide an accessible password visibility toggle.
- On phones, show short job summaries first, followed by expandable description, terms, financial information, and material responsibility. Preserve the full content and make print views usable from mobile browsers.
- Provide explicit warnings before navigation would discard unsaved edits. Never store passwords or private documents in browser local storage.

## Camera, document uploads, and previews

- Offer Take Photo and Choose File where supported. Keep a normal file picker available when camera capture is unavailable.
- Explain JPEG, PNG, and PDF support and the 10 MB job media limit before selection. Explain profile photo limits independently.
- Detect unsupported phone photo formats, including HEIC, and show a useful error. Do not claim support until conversion and server validation are implemented.
- Let users preview selected files, remove them before submission, and choose Before, During, After, or Other tags.
- Show upload progress, per-file success or failure, and a retry action where the server can safely prevent duplicate records.
- Preserve successful uploads when another selected file fails. Never indicate success before the server confirms it.
- Provide responsive image previews and an authenticated PDF view or download fallback. Keep filenames and expiry dates readable.
- Explain why job media upload is unavailable before acceptance and provide a link to the authorized acceptance action.

## Feedback and slow connections

- Show meaningful loading placeholders while lists load and clear progress messages during saves or uploads. Avoid layout jumps when content arrives.
- Use server pagination and thumbnail previews rather than loading all records or full-size images into lists.
- Display a persistent error with Retry when a request fails. Preserve form values, filters, and selected files when technically possible.
- Distinguish offline or connection failure from validation and permission errors. A lost connection must not make an action appear completed.
- Keep important approval and upload results in the page, even if a transient notification is also shown.
- Offline editing, background synchronization, push notifications, and installation as a PWA require separate implementation and verification; the responsive interface must not imply these capabilities exist.

## Mobile acceptance checks

- Review representative screens at 320, 375, 390, 430, 768, and 1280 px, including portrait and landscape phone layouts.
- Verify login, registration, profile updates, service selection, document uploads, job acceptance, media uploads, contractor approval, job editing, and activity filtering on a phone-sized viewport.
- Check long content, empty lists, failed requests, validation errors, pending approval, overdue jobs, and expired documents.
- Verify that all controls remain usable with touch, keyboard navigation, enlarged text, and the on-screen keyboard open.
- Confirm that bottom navigation, action bars, and dialogs respect device safe areas and never obscure controls or messages.
- Check Safari on iOS and Chrome on Android for file selection, camera capture, date inputs, PDF viewing, downloads, and session behavior when device access is available. Record any checks performed only in browser emulation.
- Mobile layouts pass only when users can complete the same authorized tasks without clipped content, hidden actions, or horizontal page scrolling.

## Accessibility

- Use semantic headings in order.
- Associate labels, descriptions, errors, and controls programmatically.
- Provide text alternatives for icons and images.
- Do not rely on color alone for approval, job, or document status.
- Ensure modal focus management and keyboard escape behavior.
- Announce important async results to screen readers.

## Migration design principle

Improve layout, clarity, and feedback while preserving the meaning of existing data and actions. Do not remove a legacy feature until its replacement is implemented, tested, and accepted.

## M1 implemented shell — 2026-10-01

This is the historical M1 checkpoint. The current M3 screens below replace the disabled sign-in structure.

The separate laravel-app now has a neutral/green Tailwind shell with guest and role-aware layouts, desktop sidebar, native collapsible phone menu, safe-area phone shortcuts, role badges, flash/error feedback and shared form/empty-state components. Admin and contractor navigation/actions are disabled placeholders; they do not imply working business features. Phone shortcuts currently contain Dashboard and More because those are the implemented destinations. Extend them to the specified role-specific primary destinations as later workflows become available.

Local-only preview banners explain that no accounts or records are connected. Cards show an unavailable marker rather than fake counts. The sign-in form is disabled until account setup is implemented in M3. Landing, sign-in structure, health and both shells were checked in browser emulation at 320, 375, 390, 430, 768 and 1280 px without horizontal page overflow. Native mobile navigation supports keyboard activation and synchronized expanded state. Device, upload, real authentication and domain workflow checks remain pending; see [M1 report](../docs/m1-foundation.md).

## Audit-derived screen coverage — 2026-10-01

See [legacy-audit.md](../docs/legacy-audit.md) for every source endpoint and [permissions-notifications.md](../docs/permissions-notifications.md) for target access. Legacy assets/source remain incomplete; Laravel checks are recorded by milestone. New screens preserve intended workflows while repairing broken links/handlers.

- Use consistent approval labels for 0 (Not approved/newly pending), 1 (Approved), 2 (Pending admin review); explain verification independently. The user selected pending account status/email verification only. Profile/services/documents require verified approval; no pending onboarding exception is implemented.
- Preserve admin dashboard missing-W9, active insurance within 30 days, approval/review queues, unassigned jobs, pending acceptance and preferred contractor search. Decide expired-insurance/archived-W9 treatment and use date-only deadlines.
- Distinguish ANY-service job suggestions from ALL-selected-service dashboard filtering. Show assignment eligibility and preferred status clearly; confirm override rules D4.
- Provide working contractor job index, filtered contractor directory print, accept-on-behalf and authenticated media download replacements for missing legacy destinations. Preserve 10-note and 8-media-per-tag pagination behavior or a reviewed equivalent; keep filters on return/print.
- Keep admin-private contractor Notes separate from shared Job_Notes. Do not present contractor add/edit/delete or post-completion actions until D2/D3 permissions are resolved.
- Explain acceptance on behalf, re-review and reassignment consequences with actor/time history; do not transfer acceptance or show stale completion dates silently. Display decimal zero distinctly from an unset amount.
- Show true batch upload results and retry behavior. Photo limits (legacy 2/5 MiB) and document caps must be unified under D5 before help text is finalized; media remains JPEG/PNG/PDF up to 10 MiB per file.
- Support managed contacts without email/login; distinguish review, rejection, archive, block and hard deletion. Bulk actions show exact selected targets, consequences and per-record outcomes. Never show internal risk indicators to contractors or expose SMTP details.

Current decisions are in [review.md](../docs/review.md); D1 pending access is selected and the other workflow choices remain proposed. Verification is usable before jobs. If accepted, show pre-acceptance questions separately from media, explain re-review suspension and confirm reassignment/reopening/material-change consequences. Archive and permanent delete remain visibly different. Navigation, assets and signed/authenticated links must work beneath `/contractors/`.

## M3 implemented identity screens — 2026-10-01

Sign-in, grouped contractor registration, searchable service selection, forgot/reset password and own account status are active Tailwind screens. Pending accounts see separate email verification and administrator-review cards, resend and sign out only. Verified admins have Account access navigation, paginated readable security cards and a password form with current administrator password and session consequences. Remaining domain destinations retain disabled placeholders.

Forms use CSRF, persistent labels, email/telephone/password autocomplete, 16px inputs, linked error summaries/inline errors and safe feedback. Password values never repopulate after validation or enter local storage. Shared Show/Hide controls support password managers and keyboard input; submit buttons prevent duplicate submissions and recover on page restoration. Service selection uses large rows and an announced count. The local security question is an accessible test fallback; public server-provider verification fails closed without configuration.

Public forms and synthetic authenticated status/admin security screens were checked in browser emulation at 320/375/390/430/768/1280px. Authenticated headers wrap role/sign-out controls at narrow widths; account cards stack badges beneath contacts on phones. No horizontal page overflow remained. Phone menus, service selection and password visibility work. Native keyboards/devices, uploads and business screens remain later verification. See [M3 report](../docs/m3-identity-permissions.md) and [mobile sign-in capture](../docs/m3-login-mobile.png).

## M4 contractor and document screens — 2026-10-01

The admin shell now links to contractor search, contractor detail/edit/create screens, the minimum service catalog, private document workspaces, approval/priority controls, reversible archive/restore, and private notes. Contractor navigation links to the own profile/service form and own document workspace after the approved/verified gate. Forms use large service rows, linked validation feedback, multipart upload controls, explicit document types/expiry dates, and safe status badges. Cards replace wide data tables on the contractor list and document screens; filenames and addresses wrap on small screens.

Documents provide authenticated Preview and Download actions with private cache headers. Imported documents remain visibly archive-only; new documents may be deleted by an authorized admin. Profile photos use a private authenticated response and never a public storage URL. Native camera/file-picker behavior, upload progress, and device PDF handling remain M7 verification items.

## M5 jobs, notes, and media screens — 2026-10-01

Admin navigation now exposes Jobs with searchable/status-filtered cards, create/edit forms, assignment controls, acceptance-on-behalf, completion/reopen, guarded deletion, activity and transition history, notes, tagged media, and print. Contractor navigation exposes My jobs with pending/accepted/completed badges, due dates, services, acceptance, shared notes, tagged uploads, authenticated Preview/Download/Thumbnail controls, and print. The shared job detail view uses stacked cards and responsive grids so long descriptions, terms, filenames, notes, and financial values remain readable on phone widths.

The job form explains required services, optional due date/material responsibility, decimal price/deposit rules, and assignment eligibility. Media help text states JPEG/PNG/PDF and 10 MB per file, shows the Before/During/After/Other tag choice, and explains that contractor media is available only after acceptance. Completed contractor jobs remain readable but show read-only feedback for notes/media. Admin actions expose actor/state feedback after assignment, acceptance, reopening, and completion; destructive deletion is explicit and imported evidence is protected.

Descriptions, terms, and notes render through the shared sanitizer boundary while preserving the original stored value. Print views remove edit controls and use readable headings, service/financial summaries, notes, and media references. Authenticated binary routes use private/no-store responses; image thumbnails are bounded to 120 px and PDFs use inline preview/download fallback. Native camera/file-picker, PDF, and device accessibility checks remain M7 verification items.

## M6 management and dashboard screens - 2026-10-01

Implemented role dashboards use genuine totals independently of limited alert cards, status/urgency text, filtered destinations and role-specific next actions. Admin navigation now includes Review queue, Templates, Documents and Activity; implemented Jobs links and phone primary shortcuts are active. Previews remain synthetic shells. Services, templates, activity logs, contractor review and global document lists support preserved search/filters/pagination. Rich HTML preview is sanitized; the template toolbar edits escaped source and explicitly appends sanitized content to a job draft.

Review cards show verification independently of approval, contact/domain/date, all selected services, W9/Insurance and escaped private indicators. Bulk actions require explicit selected accounts and confirmation and describe approve/not-approved/archive consequences. Server-side checks cancel the entire action if any selection is stale or ineligible. Directory and activity print views state both the matching total and their explicit caps. Historical logs label account associations honestly.

Cards stack and wrap at small widths; template controls use available width. M6 HTTP/permission and view-compilation checks are covered, but no new device or browser-emulation pass is claimed. Native phone/keyboard/camera/PDF and full responsive acceptance are M7. The disabled notification icon is still accurate: preferences/inbox/unread state, delivery-error UI and block controls remain M4.5. See [M6 report](../docs/m6-management-dashboards.md).

## M7 local review checkpoint — 2026-10-01

The isolated M7 fixture and default local browser review exercised the shared shell, role redirects, admin review/dashboard, approved-contractor job acceptance and media gate, and pending account-status/direct-denial behavior. Long job content wrapped and role navigation stayed scoped. Exact six-width rendering, native file-picker/camera/PDF, Safari/Android, and safe-area/device checks remain pending because the available review browser did not expose viewport/device controls. Do not treat the default viewport pass as client sign-off; use `docs/m7-client-review.md` for the executed rows and blocked checks.

## Admin design implementation — 2026-10-01

The first local admin design slice covers the shared shell, contractor directory, and dashboard headings/metrics. Admin-scoped CSS applies navy navigation, paper surfaces, orange actions with dark readable text, 2 px outlines, compact corners, and hard shadows. Mobile navigation keeps dark text on its light background; the desktop sidebar uses light text on navy. The sidebar and expanded mobile menu can scroll on short screens. Reduced-motion preferences suppress control movement.

The existing search, approval, priority, archive, W9 and ALL-service filters remain available. Routes, authorization and database behavior were not changed. Vite build, Blade compilation and Pint pass. Live visual inspection is incomplete because the in-app browser connection timed out; no new responsive pass is claimed. Further admin screens inherit shared components but have not received individual layout redesigns.

## Collapsible navigation — 2026-10-01

Clicking the desktop NestCrafters logo toggles the sidebar between 256 px and a 72 px icon rail, following the supplied reference. The compact rail has a light background, an active indicator, accessible labels/native hover titles, unique section icons, and account initials/sign-out at the bottom. Only the collapsed display preference is persisted in localStorage. At phone widths, clicking the logo opens the existing mobile menu; Escape closes it. The dashboard remains reachable through its explicit navigation link.

Distinct section icons: Dashboard/grid; Review queue/clipboard check; Contractors/group; Jobs/briefcase; Services/tools; Documents/file; Templates/layered pages; Activity/pulse; Account access/lock; Account status/shield check; Profile & services/person edit. Admin and contractor route permissions remain unchanged.

Verified in the local contractor browser: 72/256 px toggle, persistence after reload, five distinct section icons, no desktop horizontal overflow, and mobile logo open/Escape close at 390 px without horizontal overflow. Vite, Blade cache, JavaScript syntax and Pint pass. Screenshot: navigation-rail.png in docs. Admin icons share the same mapping but a new authenticated admin browser pass has not been performed.

## Dashboard refinement — 2026-10-01

The user-supplied contractor mascot is the shared portal logo, stored unchanged at `laravel-app/public/images/nestcrafters-mascot.png`. The shared `brand.blade.php` component uses a 44 × 64 px contain box to preserve its proportions and transparency beside the NestCrafters wordmark. The compact navigation rail shows the mascot alone; existing accessible toggle labels remain on the parent buttons. The same component supplies guest and mobile headers. Asset HTTP delivery, desktop rendering, sidebar collapse/expand, Vite build, and Blade compilation were verified.

Both role dashboards now use shared dashboard metric and empty-state components, navy introductory panels, orange primary actions, restrained hard shadows, and clear section headings. Admin information is grouped into team/workload, upcoming deadlines, document watch and recent activity. Contractor information highlights assigned work, the next required action, profile/document readiness and due-date lists. Counts, ownership scopes, approval/expiry rules and route filters remain sourced from the existing controller; no synthetic trends or inferred percentages were added. Fixed the admin dashboard's unsupported danger badge tone to use its existing error tone.

Validation: 16 tests / 103 assertions (FoundationTest and M6DashboardTest), Vite build, Blade compilation, and Pint pass. Browser verification completed for the authenticated local admin and contractor dashboards: neither page overflowed horizontally at 320, 375, 390, 430, 768, or 1280 px. Desktop and 390 px screenshots were visually inspected; long imported contractor names wrap in the admin approval queue. These are browser viewport checks, not native-device acceptance. The temporary viewport override was successfully reset. Screenshots: docs/contractor-dashboard-design.png, docs/admin-dashboard-design.png, and docs/admin-dashboard-mobile.png. The main local preview remains available on port 8011.

## Notification center and account access — 2026-10-01

The navigation bell is now active for authenticated workspaces with a capped unread badge, a distinct Notifications navigation item, a paginated inbox and role-specific email preferences. The inbox uses the navy/orange dashboard language and a clear empty state; no historical updates are invented. Pending contractors still only see their account-status/verification flow. Delivery status is linked only for admins and distinguishes transport handoff from mailbox receipt. Retry controls explicitly disclose ambiguous-send duplication. Contractor blocking requires a reason and confirmation; unblocking clearly requires approval review.

Contractor inbox/preferences were visually checked at desktop and effective 358 px browser width without horizontal overflow. Viewport override reset; browser zoom prevented claiming the requested widths as exact CSS widths in this continuation. Admin workflow responses are covered by feature tests; new native-device/admin visual acceptance is not claimed.

## Unified brand system, homepage and workflow screens — 2026-10-01

The navy/orange mini neo-brutalist system now applies to both roles and all public pages instead of only the admin shell. Global tokens replace the M1 green palette (`brand-700` is `#062F3D`), and a `portal` body class styles every select, textarea, text/date/file input and checkbox consistently (2 px ink border, orange focus with dark outline, red invalid state), so older per-view classes cannot drift. Buttons have primary (orange), secondary (white), dark (navy) and danger (red outline) variants; permanent deletes use danger. Shared `page-header`, `job-card`, `empty-state` (with optional actions) and `alert` (info/success/warning/error with icon) components replace ad-hoc headings and cards.

- **Shell:** the contractor sidebar now uses the same dark shell as admin. Phone bottom navigation highlights the active destination for every item (contractor Jobs/Documents/Account previously had none). The header shows the section and page title, a notification bell with an orange unread count, and keeps Sign out on phones (desktop uses the sidebar footer).
- **Homepage:** navy hero with Sign in / Register, an explicitly labeled example job card (no live data), the four onboarding steps (register, verify email, admin approval, accept and document work), contractor and administrator capability lists, and a "have these ready" strip (W9, insurance with expiry, JPEG/PNG/PDF up to 10 MB). The guest header now offers Sign in and Join as contractor; System health moved to the footer. Copy avoids currency symbols because currency remains an open decision.
- **Sign-in / registration / status:** sign-in pairs the form with the contractor onboarding steps. Registration shows its three-stage progress and highlights selected service rows. Account status is a step tracker (created, email verification, administrator review, workspace access) with the resend action inline.
- **Jobs:** list cards show status, due-date urgency (overdue/today/within 7 days), contractor (admin), approved price and services, with the primary action first; jobs awaiting contractor acceptance get an orange edge. Status tiles act as filters. Job detail adds a back link, a four-step progress timeline (created, assigned, accepted, completed with timestamps), a prominent Review and accept callout for pending contractor jobs, a media gate that links to the acceptance action, balance-after-deposit, and a separated Delete job zone that states the imported-media retention rule.
- **Documents and profile:** documents show W9 and insurance readiness (missing, current, expiring within 30 days, expired) above the upload form and flag expiry per file; permanent deletion now confirms. The profile form is grouped into details, company, phones (type suggestions via datalist, free text still accepted), photo and services, and warns before saving that changes return the account to admin review.
- **Contractor directory:** search, approval and Filter stay in one row; priority/W9/archived/blocked and ALL-service filters move into a "More filters" disclosure with an active count that opens automatically when filters are set.

No routes, controllers, policies, validation rules or database records changed. The disposable review server now also serves PNG/SVG so the logo renders during M7 review.

## Upload feedback — 2026-10-02

Documents and job media now show selected image previews, filename/size and Remove controls, with explicit JPEG/PNG/PDF and HEIC export guidance. Job uploads show separate per-file progress and server-confirmed status, preserve successful files during partial failure, and offer Upload / retry remaining plus a refresh link. Unconfirmed uploads keep their original token/tag and pause the queue; retry skips confirmed successes. Status feedback uses a polite live region and progress labels; selected names render as text. No private selection data is persisted to browser storage. See [upload reliability](../docs/m7-upload-reliability.md). Native camera/Safari/accessibility acceptance remains pending.

## Notification inbox management — 2026-10-02

The portal inbox now supports status tabs, event/date filters, inbox-wide counts, individual and selected bulk read/unread actions, and no-JavaScript forms. Recipient scoping remains strict, and migration 000009 preserves arrival timestamps. See `docs/notification-inbox-management.md`.

## Brand asset integration — 2026-10-02

The generated NestCrafters logo is now the shared brand asset. Expanded navigation and guest/auth screens use the horizontal `nestcrafters-logo.png`; the collapsed 72 px rail uses the isolated `nestcrafters-logo-mark.png`; and `favicon.png` is declared in the shared head component. Logo assets use the existing deep blue-green/orange palette and transparent backgrounds. The subdirectory review helper serves PNG/SVG/font assets so the same branding works under `/contractors/`.

## Contractor documents refinement — 2026-10-02

The documents screen now follows a three-part scan path: readiness cards identify the W9 and insurance state first, the upload panel explains the one-file/private/expiry workflow, and stored files are presented as reviewable records. Readiness cards use a left state rail and explicit helper labels; expiring and expired files get a matching row accent; the upload panel stays visible beside the file list on wide screens; and file actions remain wrapped for narrow screens. This is a presentation-only refinement: private routes, ownership checks, MIME limits, expiry rules, archive behavior and upload handling remain unchanged.

Brand verification (2026-10-02): expanded wordmark and compact house/nest mark visually checked. Document layout has no horizontal overflow at measured 358/374/389/429/768/1280 CSS px. See [M7 evidence](docs/m7-branding-verification.md) for screenshots, keyboard/upload checks and remaining native-device limits.


## Account navigation and settings — 2026-10-02

Removed Account status from both role navigation lists. The sidebar account name/initials now open /account/settings, with a settings icon, subtitle and active indicator. Mobile menus expose Account settings and the contractor phone shortcut is Settings. Approved verified contractors and verified admins see a workspace settings screen with a navy identity card, tinted profile/email preference sections and sign-in guidance. Pending/unverified accounts retain the restricted status/verification flow. Existing /account/status links display settings for eligible accounts.

Browser verification: the local contractor account-name link opens settings; desktop and measured 389 CSS px layouts visually checked, no horizontal overflow, viewport reset. Screenshots: docs/account-settings-desktop.png and docs/account-settings-mobile.png. AccountSettingsTest passed 2 tests / 21 assertions; Vite build and Pint passed.
