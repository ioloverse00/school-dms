# Project Audit Report

Audit date: 2026-11-10. Project: School DMS. Scope: current working tree, including existing uncommitted changes. Audit only; no repairs applied.

## 1. Executive Summary

**Deployment decision: not ready for production.** The project has a conventional Laravel MVC structure and real database-backed document workflows. However, registrar-accessible student-management endpoints can target administrator accounts, including changing their passwords. Student request details also lack ownership checks. These are confirmed, reachable source-level authorization defects; no attack was performed against existing data.

Other major problems include public storage for payment proofs, deployment archives containing logs/uploads, alumni submissions omitting a required field, registrar forms targeting admin-only endpoints, and missing mobile navigation. Registration creates accounts without roles. Multi-step persistence and external-service failure handling need improvement.

Positive evidence: all 97 first-party PHP files passed syntax checks; Composer metadata validated; the production frontend build succeeded; 30 of 33 tests passed. The local SQLite database passed read-only quick-check and foreign-key consistency checks. These results do not establish production security or complete workflow correctness.

Prioritize server-side authorization, student privacy, clean release artifacts and the broken user journeys before deployment. No source changes should begin until the user approves remediation.

## 2. Project Overview

### Technologies and architecture

- PHP ^8.2; audit runtime PHP 8.2.12. Laravel framework locked at 12.56.0, Breeze authentication, Spatie Laravel Permission 6.25.0.
- Server-rendered Blade MVC, Eloquent ORM, session authentication, role middleware, database notifications and SMTP mail.
- Local SQLite database; deployment documentation targets MySQL. Analytics explicitly handles SQLite/MySQL SQL expressions.
- Vite 7.3.1, Tailwind 3.4.19, Alpine 3.15.11 and Axios 1.14.0 in the npm lockfile. Main dashboard/login instead load Alpine 2 from a floating CDN URL and prebuilt Windmill CSS. Charts load Chart.js 2.9.3 from a CDN.
- Gemini REST integration provides demand forecasts. Active forecast requests use browser fetch.
- Composer/npm lockfiles, PHPUnit 11.5.55, and GitHub Actions PHP tests across 8.2/8.3/8.4. Audit Node runtime: 24.13.0.

### Inventory

| Location | Purpose and observations |
| --- | --- |
| [app/](app/) | Controllers, models, forecast services, notifications, mail, CSV exporters and CLI commands. |
| [routes/](routes/) | Web/auth endpoints; role groups; inline file/notification handlers. No separate public API layer. |
| [database/](database/) | 13 migrations, seeders/factory, local SQLite database. |
| [resources/](resources/) | Blade views and Vite assets; active screens plus starter/demo views. |
| [public/](public/) | Entrypoint, Apache rules, static assets, build output and storage directory. |
| [config/](config/) | Laravel settings, program defaults, Gemini and forecast assumptions. |
| [tests/](tests/) | 33 tests covering auth/profile, exports, notifications and forecast summaries. |
| [.github/workflows/](.github/workflows/) | PHP tests and repository maintenance workflows. |
| [scripts/](scripts/) | Two similar PowerShell release packagers. |
| [dist/](dist/) | Five existing ZIP archives inspected without extraction. |
| [windmill-dashboard/](windmill-dashboard/) | Ignored template copy with duplicate assets and separate package manifests. |
| [README.md](README.md) | Laravel boilerplate; hosting guides exist separately. |

Inventory covered 150 files across application code, views, routes, migrations, tests and configuration, plus build files, lockfiles, deployment documentation/scripts, static assets and archive metadata. Third-party internals were inspected selectively, not audited line by line. No applicable AGENTS.md was found in the workspace or immediate parent; the local .agents directory contained no instruction files.

Existing modifications preserved: [SettingsController.php](app/Http/Controllers/Admin/SettingsController.php), [document-weights.blade.php](resources/views/admin/settings/document-management/document-weights.blade.php), [web.php](routes/web.php), and the new [document-type partial directory](resources/views/admin/settings/document-management/partials/).

### Evidence and limitations

**Confirmed** means established by source, metadata, existing tests or an isolated demonstration. **Conditional** means exposure depends on deployment/configuration. Recommendations and unverified policies are identified explicitly. No production HTTP, SMTP or Gemini calls were made. No dependency installation, destructive database command, cache clearing or packager execution occurred. Browser rendering, console output, keyboard behavior and cross-browser compatibility were not dynamically verified. No credentials are reproduced below.

## 3. Findings and Issues

Severity: CRITICAL = immediate security/integrity/stability risk; HIGH = major security/functionality/reliability issue; MEDIUM = significant issue; LOW = minor issue; INFO = observation. **32 findings: 1 CRITICAL, 7 HIGH, 20 MEDIUM, 3 LOW, 1 INFO.**

### AUD-001 — Registrar can modify or delete privileged accounts

**Authorization · CRITICAL · Confirmed by source.**

Evidence: [registrar routes](routes/web.php:49), [student update](app/Http/Controllers/Admin/StudentController.php:88), [password write](app/Http/Controllers/Admin/StudentController.php:111), [delete](app/Http/Controllers/Admin/StudentController.php:119).

Registrar routes reuse the student controller. Edit/update/delete call unrestricted User::findOrFail instead of checking that the target is a student. A registrar supplying an administrator's numeric ID can change that account's password or delete it. Role middleware validates the actor, not the target.

**Impact:** administrator takeover/deletion and loss of administrative control. UI restrictions do not protect direct requests. **Recommendation:** enforce target policies and student-scoped lookups on every action, protect privileged/last-admin accounts, and add registrar-versus-admin denial tests.

### AUD-002 — Students can read another student's request

**Authorization/privacy · HIGH · Confirmed by source.**

Evidence: [show action](app/Http/Controllers/Student/DocumentRequestController.php:105), [resource route](routes/web.php:45), [owner information](resources/views/student/requests/show.blade.php:154).

Show loads any request by sequential ID without comparing user_id. Its view discloses student identity, course/year, purpose, status, remarks and attachment names. The separate file route does enforce ownership; this finding does not imply that route also permits foreign downloads.

**Impact:** cross-student disclosure. **Recommendation:** query through the authenticated user's requests or enforce an ownership policy; test foreign and archived IDs.

### AUD-003 — Payment proofs use public storage

**File privacy · HIGH · Conditional exposure; confirmed storage design.**

Evidence: [upload](app/Http/Controllers/Student/DocumentRequestController.php:86), [public disk](config/filesystems.php:41), [storage link](config/filesystems.php:76), [authorized route](routes/web.php:114), [deployment link instruction](DEPLOY_HOSTINGER.md:102).

Proofs are stored publicly despite an authorized application download route. A normal public storage link serves those files outside Laravel checks. Local public/storage was not established as a functioning symlink.

**Impact:** anyone holding a direct URL can read a confidential proof when that mapping exists. Random filenames are not access control. **Recommendation:** private disk, authorization on every response, review existing mappings/distributed URLs, and private/no-store headers where appropriate.

### AUD-004 — Release exclusions fail; archives contain runtime data

**Deployment/privacy · HIGH · Confirmed by comparisons and ZIP metadata.**

Evidence: [Hostinger exclusions](scripts/hostinger/make-deploy-zip.ps1:85), [runtime patterns](scripts/hostinger/make-deploy-zip.ps1:111), corresponding [Indevfinite exclusions](scripts/indevfinite/make-deploy-zip.ps1:85).

PowerShell literals contain doubled backslashes while relative paths contain single separators. Comparisons reproduced failed public/hot and runtime-storage exclusions without executing packaging. Both deployment ZIPs contain one runtime log and nine image/PDF upload entries; each test ZIP contains one log, six upload entries and public/hot. Entries may include copies; contents were not classified as secrets or personal records. The database root also lacks an explicit local SQLite exclusion.

**Impact:** disclosure or accidental release of development/user data and broken asset configuration. **Recommendation:** normalize paths, explicitly allowlist release contents, exclude databases/uploads/logs/caches/public storage/hot markers and verify archive entries in CI. Review/quarantine existing packages before distribution.

### AUD-005 — Default seeding creates accounts with fixed passwords

**Credentials/bootstrap · HIGH · Conditional on seed usage; confirmed code.**

Evidence: [privileged seeder](database/seeders/RoleSeeder.php:20), [default seed chain](database/seeders/DatabaseSeeder.php:11), [generator default](app/Console/Commands/GenerateStudents.php:13), [password output](app/Console/Commands/GenerateStudents.php:65).

Default seeding creates admin/registrar/student accounts using source-defined passwords. The student generator uses a shared default and prints the selected password. Values are omitted here; existing account records were not inspected.

**Impact:** predictable access if demo bootstrap is used in production; password disclosure through command output. **Recommendation:** separate demo seeding from secure provisioning, require unique bootstrap secrets, suppress password output and rotate any deployed defaults.

### AUD-006 — Alumni graduation-year input is outside its form

**Functionality · HIGH · Confirmed HTML/controller mismatch.**

Evidence: [input](resources/views/student/requests/create.blade.php:49), [later form opening](resources/views/student/requests/create.blade.php:62), [required validation](app/Http/Controllers/Student/DocumentRequestController.php:40).

The required year input has no form association and is outside the submitted form. Backend validation requires it for every alumni request, even when the profile already contains a year.

**Impact:** ordinary alumni submissions fail despite a populated visible input. **Recommendation:** move/associate the field with the form and add a real alumni submission/browser regression test.

### AUD-007 — Registrar CRUD forms point at admin endpoints

**Integration/functionality · HIGH · Confirmed route/template mismatch.**

Evidence: [create form](resources/views/admin/students/index.blade.php:159), [edit](resources/views/admin/students/index.blade.php:265), [delete](resources/views/admin/students/index.blade.php:375), [success redirect](app/Http/Controllers/Admin/StudentController.php:77).

The shared view uses routePrefix for filtering/export, but mutation forms still target admin URLs. Standalone create/edit pages and controller success redirects likewise target admin routes.

**Impact:** registrar UI submissions receive role denial; correctly addressed direct registrar mutations redirect to an inaccessible admin page afterward. **Recommendation:** consistently apply the authorized prefix to forms/links/redirects, together with AUD-001 authorization, and test complete registrar CRUD journeys.

### AUD-008 — Mobile navigation panel is missing

**UI/navigation · HIGH · Confirmed markup; not browser-tested.**

Evidence: [desktop-only sidebar](resources/views/layouts/dashboard.blade.php:18), [overlay](resources/views/layouts/dashboard.blade.php:269), [hamburger](resources/views/layouts/dashboard.blade.php:280).

The only sidebar is hidden below md. The hamburger toggles an overlay and overflow state but there is no mobile sidebar to reveal.

**Impact:** mobile users lose primary navigation and can be left behind a backdrop. **Recommendation:** responsive navigation with dismissal, Escape and focus handling; verify narrow viewports for all roles.

### AUD-009 — Registration creates roleless accounts

**Authentication/workflow · MEDIUM · Confirmed source.**

Evidence: [registration](app/Http/Controllers/Auth/RegisteredUserController.php:39), [fallback redirect](routes/web.php:39), [student middleware](routes/web.php:43), [test](tests/Feature/Auth/RegistrationTest.php:19).

Public registration logs users in without assigning a role or validating school identity. Dashboard redirects them into a student area requiring the missing role. The existing test stops at the first redirect.

**Impact:** successful registration leads to denied access. **Recommendation:** decide on institution-managed versus public registration; disable unused registration or validate identity and assign the intended role atomically. Test the entire redirect chain.

### AUD-010 — Submission can leave partial records or files

**Data integrity · MEDIUM · Confirmed failure-handling gap.**

Evidence: [profile mutation](app/Http/Controllers/Student/DocumentRequestController.php:46), [insert](app/Http/Controllers/Student/DocumentRequestController.php:69), [upload](app/Http/Controllers/Student/DocumentRequestController.php:81), [notifications](app/Http/Controllers/Student/DocumentRequestController.php:96).

Profile/request/file/attachment/notification operations run sequentially without transaction or file compensation. Disk failures are configured not to throw.

**Impact:** missing usable proof, orphan files, partial notifications and duplicate requests after retries. **Recommendation:** validate first, transact database writes, check/compensate storage failures, deliver notifications after commit and prevent duplicate submissions.

### AUD-011 — SMTP failure interrupts a persisted status update

**Reliability/integration · MEDIUM · Confirmed source.**

Evidence: [admin update](app/Http/Controllers/Admin/DocumentRequestController.php:64), [mail](app/Http/Controllers/Admin/DocumentRequestController.php:69), corresponding [registrar update](app/Http/Controllers/Registrar/DocumentRequestController.php:64).

Status is persisted before synchronous mail. Database notification and success response occur afterward, with no local exception handling.

**Impact:** an error response after a successful state change, missing in-app notification and repeated updates/mail on retry. **Recommendation:** after-commit queue/outbox with retries, delivery-state visibility and separate workflow/delivery success reporting.

### AUD-012 — Managed password updates lack validation

**Authentication/validation · MEDIUM · Confirmed source.**

Evidence: [student validation](app/Http/Controllers/Admin/StudentController.php:93), [password update](app/Http/Controllers/Admin/StudentController.php:111), [registrar validation](app/Http/Controllers/Admin/RegistrarController.php:53).

Create enforces a minimum password length, but update hashes any filled value without equivalent rules/confirmation. Year, student-ID and email bounds also do not consistently match database limits.

**Impact:** weak passwords and database errors from malformed values. AUD-001 separately covers critical unrestricted targets. **Recommendation:** shared request validation, optional validated/confirmed password fields and database-aligned length/year bounds.

### AUD-013 — Configurable student types conflict with fixed enums

**Domain configuration · MEDIUM · Confirmed source/schema.**

Evidence: [settings](app/Http/Controllers/Admin/SettingsController.php:150), [validation](app/Http/Controllers/Admin/StudentController.php:62), [user enum](database/migrations/2026_04_05_093755_add_fields_to_users_table.php:15), [request enum](database/migrations/2026_04_05_075508_create_document_requests_table.php:19).

Settings allow adding/removing arbitrary types while validation, forms, filters, weights and schema retain three fixed values.

**Impact:** apparently supported configuration cannot be applied consistently; removed types can remain assigned. **Recommendation:** restrict customization to the fixed domain or introduce referenced types and derive UI/validation/weights from them, with safe deletion rules.

### AUD-014 — Bulk weight endpoints lack server-side shape/range checks

**Validation/business logic · MEDIUM · Confirmed source.**

Evidence: [document weights](app/Http/Controllers/Admin/SettingsController.php:181), [student weights](app/Http/Controllers/Admin/SettingsController.php:244), [urgency](app/Http/Controllers/Admin/SettingsController.php:252).

Input is iterated/array-mapped without array/key/type/completeness validation. HTML bounds are not enforced. Single-document edits cap weights at 20; bulk edits do not.

**Impact:** type errors, missing/negative/extreme priority inputs and inconsistent ordering. Endpoints are admin-only. **Recommendation:** shared bounded rules, allowed/required keys, atomic settings persistence and a defined policy for recalculating existing stored scores.

### AUD-015 — Course migration overwrites settings and clears unmatched values

**Migration/data preservation · MEDIUM · Confirmed code; affected rows unverified.**

Evidence: [settings replacement](database/migrations/2026_05_09_000000_normalize_programs_and_user_courses.php:11), [nulling courses](database/migrations/2026_05_09_000000_normalize_programs_and_user_courses.php:59), [irreversible down](database/migrations/2026_05_09_000000_normalize_programs_and_user_courses.php:67).

The migration replaces programs with defaults and clears all remaining unmatched courses after limited mappings, without retaining originals.

**Impact:** valid custom programs/course history can disappear on upgrade. **Recommendation:** backup, preview unmatched values, preserve custom programs, review explicit mappings and retain recoverable originals.

### AUD-016 — Deletion cascades history but leaves physical files

**Retention/lifecycle · MEDIUM · Confirmed design; policy impact conditional.**

Evidence: [request cascade](database/migrations/2026_04_05_075508_create_document_requests_table.php:14), [attachment cascade](database/migrations/2026_04_05_075510_create_required_files_table.php:13), [self-delete](app/Http/Controllers/ProfileController.php:43), [staff delete](app/Http/Controllers/Admin/StudentController.php:119).

User deletion removes requests/attachment metadata through cascades without physical file cleanup. Every authenticated role can self-delete with its password; no last-admin protection was found.

**Impact:** lost registrar history and orphan proofs on disk. **Recommendation:** establish school retention requirements, use controlled deactivation/soft deletion where appropriate, manage file lifecycle and protect last-admin access.

### AUD-017 — CSV exports permit formula-leading cells

**Export security · MEDIUM · Confirmed sink; spreadsheet effect conditional.**

Evidence: [student exporter](app/Support/StudentCsvExporter.php:37), [request exporter](app/Support/DocumentRequestCsvExporter.php:40).

Names, purpose and remarks reach fputcsv without spreadsheet formula neutralization. CSV quoting protects delimiters, not formula interpretation.

**Impact:** spreadsheet software may interpret attacker-controlled cells as formulas depending on import settings. **Recommendation:** documented text-cell neutralization, with tests for formula prefixes, tabs and line breaks in supported spreadsheet tools.

### AUD-018 — Gemini output lacks enforced schema validation

**Integration/data quality · MEDIUM · Confirmed source.**

Evidence: [JSON acceptance](app/Services/GeminiClient.php:67), [return](app/Services/DocumentTrendForecaster.php:40), [summary](app/Services/ForecastSummaryBuilder.php:14), [cache](app/Http/Controllers/Admin/AiForecastController.php:89).

Any decoded JSON array/object is accepted. Prompt instructions do not enforce date format, horizon length, IDs, totals or element types. Partial downstream sanitization is not a complete contract.

**Impact:** invalid operational advice, inconsistent charts or runtime errors. **Recommendation:** strict schema and semantic checks, last-known-valid fallback and mocked malformed-provider tests. Establish backtesting evidence before using staffing predictions operationally.

### AUD-019 — Unthrottled GET triggers synchronous AI generation

**API/resource management · MEDIUM · Confirmed design; load impact unmeasured.**

Evidence: [GET route](routes/web.php:69), [force](app/Http/Controllers/Admin/AiForecastController.php:21), [generation/cache](app/Http/Controllers/Admin/AiForecastController.php:72), [retries](config/gemini.php:9).

An admin GET can incur external cost and change cache state. No generation lock or dedicated throttle prevents duplicate concurrent calls. Three primary-model attempts may take about 90 seconds before fallbacks; the UI aborts generation after 70 seconds.

**Impact:** blocked workers, abandoned requests and duplicate cost. **Recommendation:** CSRF-protected generation POST/job, cached GET reads, parameter-keyed lock/idempotency, throttling and aligned end-to-end deadlines.

### AUD-020 — Registrar forecast widget calls an admin-only endpoint

**Authorization/integration · MEDIUM · Confirmed mismatch.**

Evidence: [registrar include](resources/views/registrar/dashboard.blade.php:9), [feature gating](resources/views/partials/forecasting.blade.php:12), [API URL](resources/views/partials/forecasting.blade.php:343), [admin group](routes/web.php:67).

Both dashboards execute the widget when Gemini is enabled, while its API requires admin. Frontend gating does not check the role.

**Impact:** permission failures, redundant requests and unusable registrar forecast summary. **Recommendation:** explicitly authorize cached forecast reads for the intended roles or hide the widget, retaining server enforcement.

### AUD-021 — Calendar overflow and MySQL weekday conversion corrupt analytics

**Analytics correctness · MEDIUM · Confirmed arithmetic demonstrations.**

Evidence: [history months](app/Services/DocumentTrendForecaster.php:49), [per-type months](app/Services/DocumentTrendForecaster.php:83), [dashboard labels](app/Http/Controllers/Admin/DashboardController.php:41), [weekday mapping](app/Services/ForecastSummaryBuilder.php:145).

Subtracting months before anchoring to month start permits overflow: March 31 minus one month then startOfMonth produces March instead of February. Dashboard future labels have the same risk. MySQL Sunday=1 maps to 6 via (wk+5)%7, while the labels expect Sunday=0.

**Impact:** skipped/duplicated month series and wrong weekday staffing guidance. **Recommendation:** anchor month arithmetic first/use no-overflow operations, correct weekday conversion and test month ends, leap years and MySQL.

### AUD-022 — Mixed-text JSON parser mishandles quoted delimiters

**Parser reliability · MEDIUM · Confirmed isolated execution.**

Evidence: [substring scanning](app/Services/GeminiClient.php:153), [character handling](app/Services/GeminiClient.php:161).

PHP single-quoted quote/backslash comparisons do not represent the intended individual characters. Mixed text containing valid JSON with a brace inside a string fails extraction; directly valid JSON succeeds through the initial decode path.

**Impact:** avoidable forecast failures/retries. **Recommendation:** correct character handling or use a tested parser/structured output contract; cover quotes, escapes and embedded delimiters.

### AUD-023 — Provider exceptions are returned verbatim

**Error handling/security · MEDIUM · Confirmed source; credential disclosure not demonstrated.**

Evidence: [key in URL](app/Services/GeminiClient.php:57), [provider error body](app/Services/GeminiClient.php:77), [browser error](app/Http/Controllers/Admin/AiForecastController.php:95).

Provider bodies and raw exception messages are returned to admins. Transport exceptions may include request URLs, which contain the API key. Connection exceptions also escape the inner status-based retry loop.

**Impact:** internal/provider detail disclosure and possible credential propagation through diagnostics; incomplete network resilience. No key-bearing error was generated. **Recommendation:** supported credential header, redacted diagnostics, safe client error codes/correlation IDs and explicit transient connection-error handling.

### AUD-024 — Missing indexes and unbounded notification loading

**Performance · MEDIUM · Confirmed structure; latency unmeasured.**

Evidence: [list query](app/Http/Controllers/Admin/DocumentRequestController.php:28), [archive schema](database/migrations/2026_04_09_074446_add_archived_at_to_document_requests_table.php:11), [unread collection](resources/views/layouts/dashboard.blade.php:323), [history collection](resources/views/layouts/dashboard.blade.php:364).

Read-only SQLite inspection found only request_id uniqueness indexing document_requests, with no indexes for its user/status/archive/priority/time query patterns. Required-files parent lookup also lacks an SQLite index. Layout loads all notifications before taking eight and loads unread rows just to count them.

**Impact:** growing query cost/page memory. This is a confirmed design concern, not a measured production slowdown. **Recommendation:** workload/query-plan-based indexes and SQL-level count/limit. MySQL may automatically index foreign keys; verify per engine.

### AUD-025 — Existing suite fails and misses high-risk boundaries

**QA · MEDIUM · Confirmed execution/inventory.**

Evidence: [fixed forecast dates](tests/Unit/ForecastSummaryBuilderTest.php:26), [history assertion](tests/Unit/ForecastSummaryBuilderTest.php:80), [login expectation](tests/Feature/Auth/AuthenticationTest.php:30), [root expectation](tests/Feature/ExampleTest.php:17).

Three tests fail: May fixtures fall outside the moving history window, login expects an intermediate redirect, and root expects 200 instead of its intentional 302. No tests cover foreign request ownership, registrar targeting admins, alumni form serialization, registrar CRUD UI, mobile navigation or AI contracts.

**Impact:** red CI and insufficient security/journey confidence. Failures do not prove intentional production redirects are wrong. **Recommendation:** deterministic dates, updated expectations, authorization/failure tests, browser journeys and MySQL/frontend-build CI.

### AUD-026 — Misplaced duplicate model and repeated domain logic

**Architecture/organization · MEDIUM · Confirmed inventory.**

Evidence: [misplaced model](app/Http/Controllers/DocumentRequest.php:3), [canonical model](app/Models/DocumentRequest.php:7), [admin request controller](app/Http/Controllers/Admin/DocumentRequestController.php), [registrar controller](app/Http/Controllers/Registrar/DocumentRequestController.php).

A file under Controllers declares the same model class as the canonical file. Current PSR-4 lookup resolves the canonical path; no active loading failure was demonstrated. Controllers/dashboard queries/status lists are substantially duplicated. Unrouted demo views remain.

**Impact:** divergent fixes, confusing maintenance and possible duplicate-class conflicts with broader loaders. **Recommendation:** after approval, remove verified unused copies and extract shared domain/query logic while preserving role authorization.

### AUD-027 — Active dashboard assets bypass the npm build

**Frontend/dependency maintenance · MEDIUM · Confirmed configuration.**

Evidence: [dashboard assets](resources/views/layouts/dashboard.blade.php:8), [chart CDN](resources/views/partials/forecasting.blade.php:222), [Vite inputs](vite.config.js:7), [packages](package.json:11).

The lockfile's Alpine 3 differs from the active floating Alpine 2 CDN. Dashboard CSS is a static Windmill file rather than Vite output. Unused Tailwind 4 Vite integration coexists with active Tailwind 3 PostCSS. CDN scripts lack integrity attributes.

**Impact:** passing builds do not validate actual dashboard runtime assets; CDN outages/changes can break UI independently. Version age alone is not proof of a CVE. **Recommendation:** consolidate active assets, pin/bundle libraries and remove verified unused packages only after approval.

### AUD-028 — Demo cleanup is destructive and not portable

**Operations/database · MEDIUM · Confirmed source; never executed.**

Evidence: [confirmation](app/Console/Commands/CleanDemoData.php:15), [constraints disabled](app/Console/Commands/CleanDemoData.php:20), [truncation](app/Console/Commands/CleanDemoData.php:23), [restoration](app/Console/Commands/CleanDemoData.php:53).

Cleanup asks for confirmation but permits production, deletes all request/file/notification metadata and student users, and uses MySQL statements despite SQLite defaults. No finally block guarantees constraint restoration. Demo generators likewise lack production guards.

**Impact:** accidental live-data loss and incomplete cleanup; physical files remain. **Recommendation:** nonproduction guard, separate demo data stores, engine-specific logic and guaranteed restoration; exclude unnecessary destructive commands from releases.

### AUD-029 — Search and status/queue semantics disagree

**Business logic/UX · LOW · Confirmed behavior; queue policy unverified.**

Evidence: [DOM search](resources/views/layouts/dashboard.blade.php:449), [pagination](app/Http/Controllers/Admin/DocumentRequestController.php:33), [pending count](app/Http/Controllers/Admin/DashboardController.php:17), [queue](app/Http/Controllers/Admin/DashboardController.php:23), [filter list](app/Http/Controllers/Admin/DocumentRequestController.php:15).

Global Search only filters the current page's rows. Dashboard queues include archived/terminal requests; pending_requirements is writable but omitted from staff pending totals and selectable list/export filters. Equal-priority lists lack a deliberate tie-breaker.

**Impact:** misleading search/totals and closed records displacing actionable work. **Recommendation:** server-side search, shared statuses, defined queue scopes and deterministic ordering.

### AUD-030 — Accessibility and fixed-width layouts need work

**Accessibility/UX · LOW · Confirmed markup; visual effect unverified.**

Evidence: [labels](resources/views/admin/students/create.blade.php:26), [custom modal](resources/views/admin/students/index.blade.php:121), [380px dropdown](resources/views/layouts/dashboard.blade.php:340), [fixed grid](resources/views/student/requests/show.blade.php:189).

Some standalone labels lack for/id association; custom management dialogs lack explicit dialog semantics/focus trapping/restoration. Fixed widths and inline three-column grids lack mobile variants.

**Impact:** keyboard/screen-reader difficulties and possible narrow-screen clipping. Contrast/browser compliance was not inferred. **Recommendation:** associated labels, named dialogs/focus handling, responsive widths and keyboard/screen-reader/320px checks.

### AUD-031 — Operational runbooks are incomplete

**Documentation/operations · LOW · Confirmed documentation gap.**

Evidence: [README](README.md:10), [deployment commands](DEPLOY_HOSTINGER.md:99), [CI](.github/workflows/tests.yml:37), [middleware setup](bootstrap/app.php:13).

Guides cover hosting, writable paths, HTTPS URL and debug=false but omit safe role/catalog provisioning, tested backups/restores, migration review/rollback and role-aware postdeploy smoke tests. Repeated key generation should be explicitly first-install-only. Application/Apache CSP/frame-header configuration was not found; proxy settings are unknown.

**Impact:** incomplete provisioning, session invalidation on key regeneration and unproven recovery/security configuration. **Recommendation:** application runbook, stable APP_KEY on upgrades, restore rehearsal and verification of effective HTTPS/cookies/proxy trust/headers.

### AUD-032 — Production configuration/advisory status is unverified

**Configuration/assurance · INFO · Confirmed limitation.**

Evidence: [example environment](.env.example), [session settings](config/session.php), [app settings](config/app.php), [dependencies](composer.json).

Local configuration uses local/debug mode, SQLite, SMTP and enabled Gemini. This does not prove public debug exposure. Secret locations were identified without copying values. The real environment file is Git-ignored; inspected archives contained no environment files. Online Composer/npm advisory scans were not run.

**Impact:** production settings/current dependency vulnerability status cannot be certified. **Recommendation:** inspect actual production settings and run current lockfile advisory scans before release. Successful builds are not vulnerability clearance.

## 4. Module-by-Module Assessment

| Module | Assessment |
| --- | --- |
| Login/logout/reset | Framework hashing, five-attempt identity/IP throttling, session regeneration, logout invalidation and broker-based reset are present. Registration is disconnected from roles; managed password updates are inconsistent. |
| Verification/profile | Signed/throttled verification routes exist, but User does not implement MustVerifyEmail and business routes do not require verified middleware. Whether verification is required is a policy decision. Profile self-deletion needs retention/last-admin rules. |
| Student listing/dashboard | Own-user filtering, eager loading and pagination exist. Detail ownership fails. Pending metrics differ from staff semantics. |
| Request creation | Real inserts, MIME/10MB file validation, urgency validation, minimum copies and server-side alumni-only checks exist. Alumni form and multi-step failure handling are defective. Purpose lacks a database-aligned maximum; copies lacks a practical ceiling. |
| Attachments | File route authorizes staff or student owner. No direct user-controlled traversal path was identified. Public disk undermines privacy when mapped. No replacement/resubmission action was found for pending-requirements requests. |
| Staff requests/archive | Real status/archive writes and CSRF-bearing forms exist. Mail failure handling is weak. Any allowed status can be selected, including reversing terminal states; intended transition policy is unverified. No actor/status-history table was found. |
| Students | Scoped lists, filters and CSV are connected. Mutation targets permit critical privilege abuse; registrar forms/redirects are wrong. |
| Registrars | Admin middleware protects the module; lists scope registrars, individual lookups do not. An admin can target another role through these endpoints; no additional lower-privilege escalation is claimed. Password and last-admin safeguards need work. |
| Settings | New document-type CRUD/partial/routes are connected. Referenced types are blocked from UI deletion. JSON persistence works but student-type customization conflicts with enums. Program settings lack relational integrity and read-modify-write updates can lose concurrent edits. |
| Notifications/mail | Role-appropriate detail links and database notification tests exist. Individual read uses state-changing GET ([route](routes/web.php:129)); bulk read is POST. Unbounded loading and delivery failures need improvement. |
| CSV | Chunked streaming, eager loading/withCount and backend category/status/archive filters exist. Formula neutralization is absent. DOM search state does not affect exports. |
| Analytics/Gemini | Active dashboards aggregate real records. No evidence that demo-view mock data feeds active dashboards. Prompt includes aggregate counts/document names rather than student records. Calendar errors, schema/deadline/role/concurrency handling need work; forecast accuracy is unverified. |
| UI | Empty/error/success states, pagination and modal dismissal hooks exist. Mobile navigation is incomplete; accessibility/asset/search problems remain. Browser behavior and rendered aesthetics were not certified. |
| CLI/demo | Forecast command handles failures. Demo generation is explicit CLI functionality, not automatic production seeding. Defaults/output and absent environment guards create operational risk. |
| Build/deploy | Production build succeeds. Archive privacy failures and incomplete operational procedures block release. |

## 5. Security Assessment

The principal boundary defects are AUD-001/AUD-002. Frontend URL fixes cannot substitute for server authorization. Add denial tests before accepting a fix.

Positive controls: role middleware, framework authentication/hashing/session handling, login throttling, mutation CSRF tokens, MIME/size checks, owner-aware file route, signed verification URLs and user-scoped notifications. Inspected Eloquent filters use bound values; raw analytics expressions are fixed by database driver. No confirmed SQL injection was identified.

Most HTML uses escaped Blade output; forecast narratives use textContent. Raw JSON script insertion uses default json_encode, including slash escaping; a script-breakout vulnerability was not established. Inline Alpine name interpolation using addslashes should be replaced with context-safe JSON during hardening, especially for unusual names/line breaks; no executable XSS payload was tested.

Public attachments, fixed seed credentials, CSV formulas and raw provider errors need remediation. Effective security headers/TLS/proxy/session-cookie settings and current dependency advisories remain unverified. No actual production exploit, credential leak or specific CVE is claimed.

## 6. Database Assessment

Core relationships: users -> document_requests -> required_files; document_types -> document_requests. Unique emails/student IDs/request IDs/setting keys exist. Spatie tables provide role/permission uniqueness and pivot constraints; notifications have UUID primary keys and a polymorphic recipient index. Migrations include jobs, failed jobs, cache and sessions.

The local SQLite file was opened through PDO using mode=ro plus query_only. Observed columns include archived_at and alumni_only and align with major migrations. **quick_check=ok; foreign_key_check returned zero violations.** This does not verify all business data, file existence or production migrations.

Specific concerns:

- Missing SQLite indexes on request access patterns and attachment parents (AUD-024); MySQL foreign-key auto-index behavior differs.
- Destructive cascading deletion and unmanaged physical files (AUD-016).
- document_types.name uniqueness is application-only; no database unique constraint. Concurrent inserts or repeated non-idempotent seeding can duplicate names.
- Bounds/checks for copies, priority/weights, year and purpose are inconsistent. SQLite accepts strings that MySQL varchar constraints may reject.
- Fixed category enums conflict with settings; programs/courses are string/JSON values without reference constraints.
- Irreversible normalization replaces/clears custom data (AUD-015).
- Request/file writes and JSON setting updates lack transactional/concurrency coordination.
- Short random request-ID suffix has uniqueness enforcement but no collision retry; a rare collision causes failure rather than duplication. The generator's post-insert null check does not catch uniqueness exceptions.
- Default Laravel PostgreSQL/SQL Server configuration does not imply analytics compatibility; reviewed analytics branches support SQLite/MySQL.

No destructive migration/seed/cleanup/update was executed. Define database and private-file backup together, retention/access/encryption policy, recovery objectives and a tested restore procedure. Existing data contents, production applied migrations and MySQL behavior were not certified.

## 7. Testing Results

| Check | Result | Evidence/limits |
| --- | --- | --- |
| php -l across app/routes/config/database/tests | PASS | 97 PHP files. Does not validate Blade runtime/imports/authorization. |
| composer validate --no-check-publish --no-interaction | PASS | Manifest/lock metadata accepted; no install/update/scripts. |
| php vendor/phpunit/phpunit/phpunit --do-not-cache-result --colors=never | FAIL | 33 tests, 98 assertions, 3 failures; 30 passed. 45.912 seconds, 56MB reported. |
| Installed Vite CLI production build, --configLoader runner --outDir <new temp directory> --emptyOutDir false | PASS | 54 modules. CSS 56.52KB/8.99KB gzip; JS 83.33KB/30.96KB gzip. Existing build not overwritten. Browserslist age warning. |
| Read-only ZIP metadata | CONFIRMED ISSUES | Five archives; no extraction or printing log/upload contents. |
| PowerShell path comparisons | CONFIRMED DEFECT | public/hot/runtime patterns fail single-separator relative paths. |
| Isolated Gemini parser | CONFIRMED DEFECT | Plain JSON parsed; mixed-text JSON with a quoted brace failed. No external call. |
| Carbon/MySQL arithmetic examples | CONFIRMED DEFECT | March 31 subtraction skipped February; Sunday mapped to 6 instead of 0. |
| Read-only PDO SQLite schema/index/integrity | PASS WITH DESIGN ISSUES | quick_check=ok; zero foreign-key violations; missing indexes documented. |
| Git status before/after executable checks | PRESERVED | Original modifications remained as found; report is the intentional new file. |

Tests used process-local overrides: SQLite :memory:, empty DB_URL, array cache/session/mail, sync queue, disabled Gemini, null logger, new temporary Laravel storage/compiled-view paths and no result caching. Existing database, logs, uploads, views and result cache were not intentionally written. The existing database was separately inspected in read-only mode.

### Failures

1. [ForecastSummaryBuilderTest](tests/Unit/ForecastSummaryBuilderTest.php:80): expected nonempty historical weekdays; fixed May 2026 timestamps are outside the current two-month query window. Freeze time/derive fixtures.
2. [AuthenticationTest](tests/Feature/Auth/AuthenticationTest.php:30): expected /dashboard, received /student/dashboard. Update expectation if direct role redirect is intended and test role destinations.
3. [Feature ExampleTest](tests/Feature/ExampleTest.php:17): expected root 200, received intentional login redirect 302.

### Archive inventory

| Archive | Entries | Environment files | Runtime logs | Image/PDF upload entries | public/hot |
| --- | ---: | ---: | ---: | ---: | ---: |
| hostinger-deploy.zip | 9,171 | 0 | 1 | 9 | 0 |
| indevfinite-deploy.zip | 9,171 | 0 | 1 | 9 | 0 |
| public-assets.zip | 16 | 0 | 0 | 0 | 0 |
| test.zip | 252 | 0 | 1 | 6 | 1 |
| test2.zip | 252 | 0 | 1 | 6 | 1 |

Counts are metadata under runtime/upload paths, not a classification of their contents or unique people. No cached bootstrap config file was found in these archives.

### Not executed / limitations

- Browser E2E/visual/accessibility/keyboard/browser matrices: not executed. UI findings are structural.
- SMTP/Gemini/credential verification/production HTTP/TLS: not executed to avoid external effects/cost.
- Online Composer/npm advisory scans: not executed; current vulnerability status unknown.
- Pint/type analysis/coverage: not executed; no additional tooling installed. Syntax/tests are not type analysis or coverage measurement.
- Production/MySQL migrations, load tests, backup restore exercises and destructive demo commands: not executed.
- Native SQLite3 extension was unavailable on the initial inspection attempt; subsequent read-only PDO SQLite inspection succeeded.

## 8. Deployment Readiness

**Release blockers:** target authorization and ownership, private attachment handling, clean release archives, safe privileged bootstrap, alumni submission, registrar CRUD routing, mobile navigation and a green/relevant test suite.

Also validate SMTP failure behavior, strict AI contracts/deadlines, safe normalization/provisioning and backups. Verify actual production APP_ENV, APP_DEBUG=false, HTTPS APP_URL, stable APP_KEY, database credentials, Secure session cookies, document root and proxy trust. Local debug=true does not establish production exposure. Do not regenerate APP_KEY on ordinary upgrades.

Guides correctly recommend serving only public and excluding development markers, but packaging must enforce those intentions. Build success does not validate CDN runtime, hosting configuration or recovery. No deployment/release was performed.

## 9. Recommended Action Plan

### Priority 1: Critical/immediate

1. Fix AUD-001 target-role authorization and add registrar-to-admin denial tests; inspect deployed account changes if already exposed.
2. Contain AUD-002/AUD-003 privacy and AUD-004 archive distribution risks; assess prior access/distribution without assuming a breach.
3. Check whether fixed seeded accounts were deployed and rotate defaults if present (AUD-005).

### Priority 2: High-impact functionality/security

1. Repair alumni serialization, registrar route/redirect consistency and mobile navigation (AUD-006–008), with full journeys.
2. Resolve registration policy and password validation (AUD-009/AUD-012).
3. Neutralize CSV formulas and sanitize provider errors (AUD-017/AUD-023).

### Priority 3: Reliability/maintainability

1. Restore deterministic green tests and add security/failure-path coverage (AUD-025).
2. Add transactional creation/file compensation and after-commit delivery (AUD-010/AUD-011).
3. Align settings/enums, protect migration data and define retention (AUD-013–016).
4. Validate AI contracts, correct date/parser logic, bound/lock generation and resolve registrar API access (AUD-018–023).
5. Consolidate duplicate code, guard demo tooling and document provisioning/recovery (AUD-026/AUD-028/AUD-031).

### Priority 4: Performance/UI

1. Measure plans/volumes, add appropriate indexes and bound notification reads (AUD-024).
2. Dataset-wide search, consistent statuses/queues and deterministic ordering (AUD-029).
3. Unified assets, responsive grids and accessible dialogs/forms (AUD-027/AUD-030).

### Priority 5: Optional improvements

1. Forecast backtests/uncertainty/source-date display; keep staffing output advisory until validated.
2. Structured actor/status history, delivery observability and supported database/browser/API documentation.
3. Remove verified unused templates/packages after approval; automate archive/advisory checks.

Wait for user approval before applying fixes.

## 10. Overall Project Score

Scores are audit judgments, not measured coverage, exploit probability or percentage completion. Each dimension uses five evidence categories worth 20 points each: 0 absent/broken, 5 major gaps, 10 partial, 15 mostly established, 20 verified strong. Values below make totals reproducible; unknown runtime/production properties do not receive assumed success credit. Equal-weight mean: **42/100**, rounded from 41.7. Release blockers take precedence over the mean.

| Dimension | Score | Categories (/20 each) and evidence |
| --- | ---: | --- |
| Code Quality | 55 | Syntax 20; organization 10; validation 10; error handling 5; consistency 10. PHP passes; duplicates and failure/validation gaps remain. |
| Architecture | 55 | MVC 15; services 15; domain consistency 5; authorization design 5; maintainability 15. Suitable structure, scattered rules/policies. |
| Security | 25 | Auth primitives 15; authorization 0; sensitive data 0; input/output 5; assurance/config 5. Critical privilege abuse/privacy defects override framework strengths. |
| Database Design | 50 | Keys/relations 15; observed integrity 20; domain constraints 5; indexing 5; lifecycle/migrations 5. Read-only checks pass; integrity/lifecycle design gaps remain. |
| Functionality | 40 | Requests 5; staff CRUD 5; onboarding 5; exports/notifications 15; dashboard integration 10. Real backend exists; major journeys break. |
| Performance | 45 | Pagination/streaming 15; relationship loading 15; indexing 5; bounded memory 5; external work 5. Sensible exports; notification/index/AI/mail risks, no load benchmark. |
| User Experience | 35 | Navigation 5; forms 5; feedback/empty states 15; accessibility 5; responsive evidence 5. Structural defects; aesthetics unverified. |
| Testing | 40 | Executable suite 10; results 10; domain coverage 10; security negatives 5; environment/CI breadth 5. 30/33 pass; missing boundaries/browser/MySQL coverage. |
| Deployment Readiness | 30 | Build 15; release hygiene 0; provisioning/config 5; recovery 0; monitoring/release checks 10. Build/guides exist; unsafe archives and unverified recovery block release. |

Audit completed without repairs. This report is the sole intentional project-file addition.
