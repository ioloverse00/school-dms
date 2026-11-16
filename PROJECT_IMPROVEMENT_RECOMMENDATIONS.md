# Project Improvement Recommendations

Prepared: 2026-11-10. Scope: School DMS, based on the completed [project audit](PROJECT_AUDIT_REPORT.md) and a targeted read-only recheck of current routes, controllers, settings, queue configuration and tests.

**Planning only. This document is the sole authorized file addition for this task. No implementation, dependency update, database change or removal of functionality has been performed.** Audit results below are historical evidence from the preceding audit, not tests rerun for this planning task. Proposed new classes/tables are designs, not claims that they already exist.

## 1. Executive recommendations

**Keep Laravel, Blade, Eloquent and the existing role framework. Improve this application incrementally as one deployable system.** The main problems are missing enforcement and inconsistent workflows, not an unsuitable language or framework. A frontend rewrite, microservices or a new database abstraction would consume effort without closing the audited risks.

The current implementation is suitable as a development baseline, but is **not yet suitable for production use with real student records**. The audit identified 32 issues, including registrar access to administrator accounts, cross-student request disclosure and unsafe document/release handling. PHP syntax, Composer validation and a temporary-output production build passed; 30 of 33 tests passed. None of those checks compensates for broken authorization.

The five most important improvements are:

1. **Enforce actor-and-target authorization everywhere.** Fix AUD-001/AUD-002 before improving access to registrar CRUD. Add policies, student-scoped queries and negative tests that prove forbidden targets remain inaccessible.
2. **Protect documents and make releases safe.** Use private proof storage, migrate existing files with verification, repair the packagers and exclude all runtime data. Establish secure account provisioning, backup and recovery before release.
3. **Complete the existing user journeys.** Repair alumni form serialization, registrar forms/redirects and mobile navigation; resolve registration policy and the registrar forecast mismatch. Test a complete journey for each role.
4. **Make state changes reliable and explainable.** Centralize validation and lifecycle rules, coordinate database/file writes, move mail behind reliable delivery, protect against duplicate/stale updates and record important actions.
5. **Make quality checks and operations enforceable.** Restore deterministic green tests, cover security/failure cases, verify the actual production database engine, validate release archives and rehearse restoration.

AI forecasting is secondary to correct request processing. Retain it as an advisory feature, with validated outputs and visible freshness; it must not block submission, status updates or document release. Do not remove it or other existing functionality without a separate approved decision.

## 2. Overall project evaluation and current strengths

### What is already implemented well

| Existing strength | Why retain it | Evidence |
| --- | --- | --- |
| Conventional Laravel MVC and Eloquent relationships | Familiar structure is appropriate for a small school operations team; there is no need for a framework replacement. | [controllers](app/Http/Controllers/), [models](app/Models/), [routes](routes/) |
| Role middleware and framework authentication primitives | Session regeneration, password hashing, login throttling and reset mechanisms provide useful foundations. Correct target authorization should build on them. | [bootstrap](bootstrap/app.php:13), [login request](app/Http/Requests/Auth/LoginRequest.php), [session controller](app/Http/Controllers/Auth/AuthenticatedSessionController.php) |
| Server-validated submission and eligibility checks | Document existence, urgency, MIME/size and alumni-only eligibility are enforced in backend code. Extend these rather than inventing a second validation system. | [submission controller](app/Http/Controllers/Student/DocumentRequestController.php:33) |
| Pagination, eager loading and streamed/chunked CSV | These are sufficient starting patterns for school-scale lists/exports; do not replace them with an analytics platform. | [staff listing](app/Http/Controllers/Admin/DocumentRequestController.php:23), [CSV exporters](app/Support/) |
| Separate forecasting/integration services | External calls, aggregate series and summaries already have meaningful service boundaries. Strengthen their contracts. | [services](app/Services/) |
| Database keys, migrations, lockfiles and executable tests | Reproducibility and integrity foundations exist. The audit's SQLite integrity checks passed; migration safety and coverage remain incomplete. | [migrations](database/migrations/), [Composer lockfile](composer.lock), [npm lockfile](package-lock.json), [tests](tests/) |
| Feedback and notification infrastructure | Success/error/empty states and persisted notification links support real user workflows. Delivery failures and history loading need correction. | [notifications](app/Notifications/), [views](resources/views/) |

### What needs improvement, what is missing, and growth risks

Authorization is incomplete at record boundaries; correct role middleware alone cannot distinguish a student record from an administrator account. Public attachments and unsafe ZIP contents weaken otherwise sensible download authorization. Fix those before performance or cosmetic work.

Several implemented screens do not complete their workflow: the alumni year field is outside its form, registrar student mutations point at admin routes, and the mobile menu has no panel. Registration creates roleless users. These are integration defects rather than requests for more features.

Important missing workflow capabilities include controlled correction/resubmission, a durable status/action history, explicit payment-proof verification, accountable release confirmation and delivery recovery. Their exact rules require registrar agreement. This document proposes narrow additions rather than a full student information or accounting system.

As records and staff grow, the most likely problems are unbounded notification reads, poorly indexed queries, synchronous SMTP/AI work, inconsistent duplicate business logic, concurrent status updates and ambiguous retention. These should be addressed by bounded queries, appropriate indexes, a working queue, shared actions and policy decisions before considering horizontal scaling.

Architecture should be reconsidered at three specific seams: duplicated admin/registrar operations, route-embedded file/notification handling, and business settings represented in several incompatible forms. Shared actions/policies/query builders are warranted; generic repositories around every Eloquent model are not.

### Technology-stack judgment

- **Keep Laravel/Blade and local Alpine UI state.** Forms, lists, dialogs and staff dashboards do not justify a SPA rewrite or global state store.
- **Keep Eloquent and existing database-backed notifications.** Native relations, validation, transactions and queues are adequate.
- **Keep database-backed jobs initially.** Redis is optional later if measured queue/cache contention warrants it and hosting supports operation.
- **Make one explicit production database decision.** SQLite is reasonable for development or a genuinely small, single-instance installation with safe backups. The existing MySQL hosting direction is reasonable for concurrent registrar use, but a switch needs migration/concurrency testing and operational support; the audit does not establish a capacity limit or mandate migration.
- **Consolidate frontend asset delivery in the existing Vite pipeline.** This is dependency/style consistency work, not a design-system or framework replacement. Preserve behavior while migrating the active dashboard's Alpine 2 interactions to the chosen version.
- **Keep Gemini optional and advisory.** No evidence justifies allowing model output to decide eligibility, priority or release authorization.

If responsible for maintenance, I would first establish an authorization matrix and passing regression suite, then repair complete user journeys, file/data lifecycle and release procedures. I would refactor only code touched by those changes until behavior is well protected.

## 3. Improvement opportunities by area

### A. System architecture

**Necessary now:** retain role-specific entrypoints while moving common mutations into a small set of application actions: request submission, status change, document access and student-account administration. Policy checks must remain explicit at each entrypoint. Use FormRequest classes for consistent rules. Keep controllers responsible for HTTP input/output and route-prefix selection, actions for workflow orchestration, and models for relations/scopes/casts.

Extract common request filters/ordering used by admin, registrar and CSV into a query scope or focused query object. This prevents a list showing one dataset while export returns another. Shared code must not accidentally broaden registrar or student access.

Replace route closures for protected document access and notification reads with dedicated controllers when those areas are changed, so validation/policies/error contracts are testable. Remove the misplaced duplicate [DocumentRequest declaration](app/Http/Controllers/DocumentRequest.php:3) only after approval and an autoload/reference check; retain the canonical [model](app/Models/DocumentRequest.php).

**Useful later:** a few focused namespaces for request operations/account administration if the application grows. Keep one application/database/release unit. **Unnecessary now:** microservices, CQRS/event sourcing, a generic repository layer, a separate frontend deployment and a new public API merely to render current screens.

### B. Functionality and business logic

1. Repair AUD-006/AUD-007/AUD-008 and registration/forecast permissions first. For registrar CRUD, close AUD-001 before connecting the repaired UI to mutation routes.
2. Agree on an allowed transition map using existing statuses. Define what pending_requirements means, who can request correction, whether rejected/completed records can reopen and whether archival is independent of workflow status. Do not silently remove valid transitions before that agreement.
3. Require a reason for rejection, correction requests and privileged overrides. Make archive/release actions intentional with confirmation and meaningful messages. Exclude archived/terminal records from the actionable queue while retaining explicit history access.
4. Store an idempotency/submission token with database uniqueness. Disabling the submit button helps UX but cannot protect retries/concurrent requests alone. Retry request-ID collisions without hiding unrelated database errors.
5. Protect staff edits from stale pages using a version field or conditional update. A stale status screen should show the newer state and ask the user to review, rather than silently overwriting another registrar.
6. Define practical copy-count, year and purpose-length limits; validate every optional password change. Preserve entered values and relevant modal context after validation failures.
7. Define priority fairness: deterministic order by score and a documented age tie-breaker. Keep already assigned scores as a historical snapshot initially unless the registrar wants reprioritization; any recalculation should preview effects, exclude closed work and be audited.
8. Resolve configurable student types coherently. The current add/remove settings cannot work with fixed enums everywhere (AUD-013). If customization is a genuine requirement, plan referenced category records and controlled migration; otherwise present a reviewed limited-settings option. No existing settings feature should be removed automatically.

Automation should first eliminate lost work: queued notifications, safe reminders, backup verification and failed-delivery alerts. Automatic eligibility decisions, payment approval or document release are not justified.

### C. Database

**Integrity first:** use database transactions for related database writes; validate uploads before persistence and implement compensation for filesystem operations. A database rollback cannot roll back a stored file. A practical design is to stage a validated private upload, transact request/attachment metadata, remove staged data on failure, and monitor/reconcile stale staged or orphan objects. Make partial finalization visible and recoverable if promotion fails.

Add database uniqueness for document-type names after a duplicate review and case/collation policy. Keep public request-ID uniqueness. Add constraints/bounds consistent with the selected engine and FormRequests; test SQLite/MySQL differences rather than assuming equivalent varchar/enum enforcement.

For the actual query workload, inspect plans and consider request indexes beginning with user_id/created_at for student history; archived_at/status/priority_score/created_at for staff queues as dictated by selectivity/order; created_at for historical ranges; and required_files.document_request_id where absent. These are candidates, not a mandate to add all permutations. Indexes increase storage/write costs and should be measured with representative data.

Preserve custom courses/programs during normalization. If the [normalization migration](database/migrations/2026_05_09_000000_normalize_programs_and_user_courses.php) is already deployed, do not edit its history to pretend lost data is recovered: use a reviewed forward repair and backup comparison. If not deployed, revise the upgrade plan before execution. Never assign unmatched students arbitrarily.

Use stable program/category identifiers if catalog management genuinely expands. Deactivate referenced values rather than erasing history. Resolve role-account deletion and retention together; a soft-delete flag alone does not implement erasure, file cleanup, restoration or last-admin protection.

**Backups:** document a consistent database-plus-private-file restore point, encrypt backups, restrict access, retain a separate copy and test restoration in an isolated environment. Include stable application-key recovery because some stored/encrypted data and sessions depend on it. Do not copy a live SQLite file casually; use an engine-supported consistent backup approach. Set recovery objectives with the school; an initial proposal is recovery within one business day and no more than 24 hours of lost changes, tightened if request volume/value requires it. These are targets for agreement, not existing guarantees.

### D. Security

Create an actor/action/target matrix covering student, registrar and admin; own/foreign requests, staff/student account targets, archives, files, exports and forecast reads/generation. Role middleware remains the broad gate; model policies and scoped lookup enforce the target. Avoid treating a difficult-to-guess identifier as authorization.

Move all proofs to private storage through a migration plan that verifies file counts/readability and metadata before removing public copies or mappings. Do not delete existing evidence until retention and migration checks are approved. Authorize preview/download separately where necessary; return safe file content types and private cache headers. Keep size/MIME checks; assess malware scanning later if upload volume or institutional security policy requires it. It would add operational requirements, not replace access control.

Provision staff accounts without fixed passwords or password output. Define institutional/student registration policy and whether email verification is mandatory; prevent roleless onboarding. Use password confirmation/re-authentication for sensitive staff account changes and last-admin protection. Consider administrator MFA before broad external deployment when supported by the account/hosting plan; it supplements rather than replaces policy fixes.

Validate bulk settings as arrays with allowed keys, required completeness and bounded values. Normalize names before uniqueness checks consistently with the database. Neutralize spreadsheet formulas in exports. Use context-safe JSON for Alpine attributes and safe text output for provider narratives.

Change explicit AI generation to a CSRF-protected POST with role checks, validation, throttling and a bounded job. GET should only retrieve a cached result/status. Notification read mutations should use an appropriate protected method while preserving an accessible navigation experience.

Log actor, action, target, timestamp and relevant state changes for account/role edits, status/release actions, file access where justified and sensitive exports. Do not log passwords, tokens, full upload contents or raw provider request URLs. Establish log access/retention. API failures should return safe messages and correlation IDs; keep sanitized technical context server-side. Verify effective HTTPS, Secure/HttpOnly/SameSite cookies, proxy trust, frame protection and CSP on actual hosting; plan CSP around current inline code rather than deploying a policy that breaks the UI.

### E. UI and user experience

Restore mobile navigation before cosmetic dashboard redesign. Fix the missing panel, backdrop dismissal, Escape behavior and focus return. Test all roles at narrow widths with long names/program titles and enlarged text.

Make dashboards operational: staff should see actionable requests, aging/due work, corrections awaiting the student and ready-for-release items. Students should see clear status, next action and whether staff review or their correction is required. Counts/filters must share status definitions with backend queries. Keep historical and AI analytics separate from the work queue.

Implement server-side search across the complete allowed dataset, preserving filters and pagination. Export should either apply the same visible filters/search or clearly state its broader scope. Keep GET URLs for bookmarkable non-sensitive filters; do not put private remarks or credential values in query strings.

Use shared Blade components for fields/errors, status badges, filter controls, dialogs and empty states, extending existing components where suitable. Associate labels/errors with inputs, expose dialog names/semantics, manage focus and avoid color-only status meaning. Replace fixed 380px panels and three-column inline grids with responsive sizing. Validate these changes in the browser; source inspection cannot establish contrast or usable keyboard behavior.

During slow work, preserve form state and show whether the operation is saved, pending delivery or failed. Do not say a request failed merely because SMTP failed after persistence. Provide a safe retry route and reference number for errors without exposing stack traces.

### F. Performance

**Now:** SQL-level notification count/limit, supporting indexes after plan review, deterministic pagination, eager loading and bounded external calls. Existing chunked CSV streaming is sufficient for ordinary volumes; keep it unless measured exports exceed request timeouts. An asynchronous export feature is useful later for genuinely large datasets.

**Next:** queue mail and generation through the existing database queue, and operate a real worker or scheduled drain appropriate to hosting. Simply setting a queue driver is not deployment. Define job timeouts, retries/backoff, visibility/expiration settings and failed-job handling; prevent duplicates when a job is delivered more than once. Do not queue a mutable model expecting it to preserve the old status: pass a persisted transition/event ID and stable payload.

Cache slowly changing settings within a request first; if repeated cross-request lookups warrant it, add bounded caching with explicit invalidation after settings changes. Use parameter-keyed forecast cache entries including prompt/config/schema versions and a source-data marker or freshness policy. Retain last-known-valid output and use a lock against duplicate generation. Avoid generating on each unrelated dashboard request.

Consolidate active scripts/styles in Vite with pinned versions. Preserve the dashboard's interaction behavior during Alpine consolidation; a build pass alone will not detect modal regressions. Redis, a CDN, distributed workers and horizontal application replicas should follow measured demand, not precede the missing basic controls.

### G. Testing and reliability

Repair the three audited test failures without weakening assertions: freeze forecast time or use time-relative fixtures, test the intended role redirect, and assert root's expected redirect. Include the destination response, since a successful intermediate redirect does not prove the user can access the page.

Recommended regression matrix:

| Test group | Scenarios and acceptance |
| --- | --- |
| Authorization | Registrar cannot read/change/delete admin accounts; student cannot read foreign requests/files; roleless users cannot drift into business routes; archived/export access follows policy. |
| Real form journeys | Alumni request submits year and proof; registrar create/edit/delete uses correct paths; validations reopen the right modal; mobile navigation reaches each role's major modules. |
| Persistence failures | Storage failure produces no falsely successful request; transaction rollback/compensation is verified; SMTP outage preserves saved state and schedules delivery; stale updates are rejected clearly. |
| Retries/concurrency | Double-submit yields one logical request; repeated queue delivery does not duplicate logical notifications; request-ID collision is recoverable; concurrent status edits do not silently overwrite. |
| Domain/settings | Alumni-only eligibility; all pending statuses; bounds/type validation; score snapshot/recalculation policy; custom catalog migration and referenced-value deactivation. |
| Integration | Gemini disabled/timeouts/429/5xx/connection errors; malformed dates/types/IDs/totals; cache freshness/locks; registrar forecast permissions; safe error redaction. |
| Database/exports | Production-engine migrations/constraints/query behavior; month ends/leap years/MySQL weekdays; filtered exports and CSV formula prefixes; historical migrations preserve custom courses. |
| Operations | Release ZIP denylist/allowlist; no runtime data/default secrets; production config smoke test; restore rehearsal; worker startup and failed-job recovery. |

Use focused unit tests for priority/transition/calendar/parser rules, feature tests for authorization/database/HTTP boundaries and a small browser suite for serialization/navigation/modal behavior. New browser tooling is an implementation decision requiring the user's later approval; none is installed by this task. Do not chase an arbitrary coverage percentage or duplicate implementation in tests.

Add centralized exception classification: expected validation/domain/conflict errors get actionable user messages; unexpected failures get a generic reference and sanitized structured logs. Do not catch every exception and report success. Monitor failed jobs, queue delay, delivery failures, request error rate, storage/backup failures and disk space; start with simple alerts owned by a named maintainer.

### H. Deployment and maintenance

Create clean release artifacts from an explicit allowlist, ideally from CI/clean checkout, never from a live working directory containing uploads. Validate ZIP entry paths, bootstrap caches, development markers, local databases, logs and public storage. Build the actual dashboard assets and verify protected download routes after deployment.

Expand [existing CI](.github/workflows/tests.yml) with the frontend build, deterministic tests, a production-engine migration job and archive validation. Dependency advisory/secret checks should be configured and reviewed before release; this planning task does not assert any new CVE or run online scans. Keep dependency updates small and scheduled with regression review; no blanket major-version upgrade is justified.

Document environment keys without values, first-time secure role/catalog provisioning, stable APP_KEY handling, queue/scheduler operation, migration sequencing, rollback limitations and role-specific smoke tests. Separate production/demo provisioning. Protect destructive demo commands by environment and operator checks; no functionality is removed as part of this plan.

Run a rehearsed deployment on a separate staging database/filesystem. Preserve writable runtime directories between releases. Back up before risky data migrations; use forward-compatible/expand-and-contract changes where practical. A code rollback cannot undo a destructive data migration, so define restore/forward-repair steps separately. Use a maintenance window if the hosting platform cannot safely apply changes while serving traffic.

Replace the boilerplate [README](README.md) with application onboarding and link the [Hostinger](DEPLOY_HOSTINGER.md) and [Indevfinite](DEPLOY_INDEVFINITE.md) guides to one authoritative operations runbook. Assign ownership of backups, alert responses, account provisioning, updates and recovery rehearsals.

## 4. Suggested features with meaningful value

These extend the school document workflow; they are proposals, not findings that every capability is required. Complexity includes validation, policies, tests and operational handling, not only screen construction.

### F-01. Controlled correction and resubmission

- **Purpose/problem:** let students resolve missing/incorrect evidence without creating a duplicate request or contacting staff outside the system.
- **How it works:** registrar requests correction with a reason; the owner sees exactly what is needed and uploads a replacement while in an allowed state. Resubmission returns to verification. Preserve prior evidence according to retention policy; prevent editing after completion unless explicitly reopened.
- **Affected modules:** student request create/show, staff status management, attachments and notifications; AUD-006/AUD-010 and the incomplete pending-requirements path.
- **Technical requirements:** agreed transition map, ownership policies, private/versioned attachment metadata, MIME/size checks, transaction/compensation, history and concurrency protection. Existing [student controller](app/Http/Controllers/Student/DocumentRequestController.php) needs new approved behavior, not a second request flow.
- **Complexity:** Medium. **Priority:** High. Build after the release-blocking privacy/form fixes.

### F-02. Request history and accountable release confirmation

- **Purpose/problem:** answer who changed a request, why it was rejected/reopened and who confirmed collection; current status/remarks alone cannot reconstruct events.
- **How it works:** append status events with actor/time/reason; present a student-safe timeline. Completion records authorized staff and release time, with a recipient/reference only if school policy requires it. Require an explicit release action rather than silently inferring collection from an email.
- **Affected modules:** admin/registrar status actions, request models/details, student timeline, notifications and reporting.
- **Technical requirements:** request-event table, transactional event/state write, policy checks, safe student/staff visibility, retry-safe delivery keyed to event ID and retention/redaction rules. Do not claim past actor history can be reconstructed from current rows.
- **Complexity:** Medium. **Priority:** High. Minimal audit history belongs before production; richer presentation/release metadata follows policy approval.

### F-03. Explicit payment-proof verification

- **Purpose/problem:** uploading a proof does not establish that a payment is genuine or accepted; make staff review visible and repeatable.
- **How it works:** authorized staff mark a proof accepted or needing correction with reviewer/time/reason. Gate processing only if the school confirms that rule. Students see review status and the next action.
- **Affected modules:** attachments, staff request details, lifecycle validation, correction flow and notifications.
- **Technical requirements:** separate review metadata or a payment-verification record, private access, history, concurrent-edit protection and a policy for replacement proofs. Keep this as evidence review; do not imply bank reconciliation or duplicate a full accounting ledger.
- **Complexity:** Medium. **Priority:** High if proof review is mandatory; otherwise Medium. Confirm ownership between registrar and finance first.

### F-04. Actionable queue, aging indicators and due-date expectations

- **Purpose/problem:** identify requests needing attention and reduce manual follow-up; current score-only ordering can hide old or closed work.
- **How it works:** saved filters for pending verification, correction, processing and release; show request age and a clearly labeled target date. Add optional overdue reminders after service targets are approved. Start with filtering/age before automatic escalation.
- **Affected modules:** staff dashboard/lists, priority scoring, settings and reports; AUD-029.
- **Technical requirements:** shared query scopes, stable ordering/indexes, agreed service-time/calendar rules and scheduler only for reminders. Processing-time measurement requires transition timestamps rather than assuming updated_at means completion time.
- **Complexity:** Medium. **Priority:** Medium; correcting the existing actionable queue is High.

### F-05. Delivery status and safe notification retry

- **Purpose/problem:** staff need to know when a saved update has not reached the student without repeating the business change.
- **How it works:** show delivery pending/sent/failed separately from request status; retry authorized failed events with bounded attempts. Keep in-app history available when email is unavailable.
- **Affected modules:** [mail](app/Mail/DocumentStatusUpdated.php), [notifications](app/Notifications/), status actions, jobs and a small staff delivery view.
- **Technical requirements:** event/delivery identifiers, database queue/outbox records, idempotent jobs, sanitized failure details, alert ownership and supported worker/scheduler setup. Email providers can accept a send before an acknowledgment is lost; promise retry-safe application behavior, not guaranteed exactly-once SMTP delivery.
- **Complexity:** Medium. **Priority:** High for reliable processing; a full delivery-management page can follow the core queue fix.

### F-06. Controlled student CSV import with preview

- **Purpose/problem:** reduce repetitive account entry for enrollment batches without introducing fixed shared passwords or silent data overwrites.
- **How it works:** staff upload a bounded CSV, review normalized/invalid/duplicate rows, explicitly confirm accepted changes, then receive a per-row result report. Use invitation/reset onboarding rather than exporting plaintext passwords.
- **Affected modules:** student administration, program/category catalog, onboarding and job/report infrastructure.
- **Technical requirements:** strict schema/encoding/size limits, role policy, school-identity matching rules, dry-run staging, batch identifiers, uniqueness/concurrency handling and logged approved changes. Decide whether existing records can update before supporting upserts. Queue only if batch size warrants it.
- **Complexity:** High. **Priority:** Medium, only if recurring batch entry is a demonstrated workload.

### F-07. Forecast confidence, freshness and baseline comparison

- **Purpose/problem:** prevent model-generated estimates being mistaken for validated commitments and make forecasting usefulness measurable.
- **How it works:** display generation date, data window, assumptions and insufficient-history warnings; compare past predictions to realized counts and a simple historical baseline. Allow operational pages to work when Gemini is disabled/unavailable.
- **Affected modules:** [forecast services](app/Services/), forecast cache/API, dashboard partial and tests; AUD-018 through AUD-023.
- **Technical requirements:** validated response schema, versioned parameter/source snapshots, retained forecast runs for evaluation, month/date fixes and an agreed evaluation metric. Do not invent numeric confidence intervals from model prose; label uncertainty qualitatively until calibrated.
- **Complexity:** Medium. **Priority:** Low for backtesting UI, High for truthful freshness/error display on an existing enabled feature.

**Not recommended currently:** public document verification, automated PDF signing/issuance, payment gateways, SMS campaigns, biometrics or SIS integration without defined institutional ownership/API requirements. These expand trust, privacy, support and compliance obligations. They should not compete with the current security and service workflow work.

## 5. Technical recommendations: now, later or unnecessary

| Opportunity | Timing | Smallest useful approach and rationale |
| --- | --- | --- |
| Target authorization/policies | Necessary now | Keep role middleware; add actor-target policies/scoped lookup and boundary tests. Closes AUD-001/AUD-002 directly. |
| Application actions/shared queries | Necessary now, incremental | Extract the duplicated submission/status/list-filter logic as touched; avoid a wholesale rewrite. Protects admin/registrar consistency. |
| FormRequests and status definitions | Necessary now | Central rules/allowed keys and one status vocabulary; prevents form/schema/filter drift. Transition policy must be agreed. |
| Reusable Blade components | Necessary now for repaired forms/dialogs; broader reuse later | Extend existing components; reduce repeated errors and accessibility gaps without redesigning every screen. |
| Central error handling | Necessary now | Distinguish validation, authorization, stale state, delivery and unexpected failures; safe response with sanitized diagnostic ID. |
| Internal API structure | Necessary now for forecasting | Cached GET plus generation POST/job/status contract. A broad REST API/versioning layer is unnecessary without consumers. |
| State management | Current local state sufficient | Use Alpine for dialogs/UI state and server truth for requests. Add stale-edit/version handling. Redux/Pinia-style global state or SPA conversion is unnecessary. |
| Background processing | Necessary before production for mail/AI | Existing database queue plus monitored worker/scheduled drain. Add outbox/reconciliation where missing delivery is unacceptable; no new broker initially. |
| Caching | Bounded caching now; expanded later if measured | SQL-limit notifications, parameter-key forecast entries and explicit invalidation. Never share protected response caches across users. |
| Database optimization | Necessary targeted indexes; larger normalization conditional | Query plans and real constraints first. Catalog tables are justified by genuine dynamic catalog requirements. |
| Logging/monitoring | Necessary operational minimum now | Structured sanitized events, failed-job/backup/storage alerts and an assigned responder. A large observability stack is unnecessary initially. |
| Automated tests | Necessary now | Regression matrix tied to findings, a few real browser journeys, selected production-engine tests. Coverage percentage alone is not a goal. |
| CI/CD | CI release gates now; full automatic deployment later | Build/test/advisory/archive checks and staged human-reviewed release. Automating a broken packager would increase risk. |
| Dependency maintenance | Advisory check before release, incremental updates thereafter | Keep compatible lockfiles, assess supported versions and runtime CDN dependencies. Do not infer known CVEs solely from version age. |
| Documentation | Necessary now | App onboarding, role/transition policy, secure provisioning, deployment/queue and restore runbooks. |
| Redis, horizontal replicas, managed object storage | Useful later if hosting/measurements justify | Shared sessions/files/locks become requirements if replicas are introduced. No evidence demands distributed infrastructure today. |
| Microservices, event sourcing, universal repositories | Unnecessary for current scope | Operational/implementation cost without resolving the observed defects. A small append-only request history is not a need for full event sourcing. |

## 6. Major recommendation comparisons

| Decision/option | Advantages | Disadvantages | Recommendation |
| --- | --- | --- | --- |
| Retain Blade/Laravel vs replace with SPA/services | Existing app preserves skills, deployment and server-side validation; small changes are reviewable. | Retention still requires shared-code cleanup; rewrite introduces duplicate APIs/auth/state and migration risk. | Retain the stack; no strong technical justification for replacement. |
| Policies/shared actions vs only separate controllers | Shared enforcement/business behavior reduces drift; role entrypoints remain readable. | Requires explicit boundaries/tests so shared code does not grant extra permissions. | Policies plus focused actions; do not rely on controller separation alone for authorization. |
| Private local files vs private object storage | Local private disk is simple on one server; object storage supports multi-instance/durable shared storage. | Local disk needs backup/retention discipline; object storage adds credentials, provider cost and migration. | Private local storage now unless hosting requires otherwise; never keep sensitive proofs public. |
| Database queue vs Redis/broker vs synchronous delivery | Database queue reuses current infrastructure and decouples SMTP/AI. Redis supports higher throughput; sync is simplest to run. | Database jobs need worker/monitoring; Redis adds operations; sync preserves current latency/failure coupling. | Database queue with supported worker/drain first. Do not claim reliability until it is operated and tested. |
| After-commit dispatch vs transactional outbox | After-commit jobs are simple and prevent precommit reads; an outbox records intended delivery durably with state. | Dispatch after commit can fail before enqueue; an outbox needs a processor/reconciliation and duplicate handling. | After-commit with explicit recovery is an initial step; use a same-transaction outbox for status/release events if delivery must never be silently lost. |
| Fixed types vs referenced configurable catalog | Fixed types are simple if school categories never vary; tables make real add/deactivate workflows consistent. | Fixed types limit an existing promised setting; tables require migration/backfill/history handling. | School decision first. Preserve functionality; prefer references if customization is intended, otherwise obtain approval for limited settings. |
| Keep SQLite vs adopt MySQL for production | SQLite is easy for a small single-instance service; MySQL aligns with hosting and concurrent staff use. | SQLite write concurrency/backup need testing; MySQL adds hosting/admin and migration differences. | Decide from hosting, support and load evidence. Test the selected engine; do not migrate just for modernization. |
| Broad application caching vs targeted caching | Targeted settings/forecast cache is easy to invalidate; broad caches may reduce reads. | Broad caches risk stale statuses, permissions and user-data leaks. | Targeted bounded cache; current paginated/streamed list/export design is sufficient until measurements say otherwise. |
| Live forecast on dashboard vs cached job-generated forecast | Background cached output keeps the dashboard responsive and available. | Requires freshness indicators/jobs; live requests are easy but couple UX to provider availability/cost. | Cached last-valid forecast, explicit authorized generation, baseline comparison later. |
| Existing CSV streaming vs async report/export service | Streaming already bounds memory and keeps operation simple. | Very large exports can exceed HTTP deadlines; async adds job/file retention/access concerns. | Keep streaming now; add async exports only after volume/time evidence. |

## 7. Prioritized implementation roadmap

Complexity guide: Low = localized change with regression checks; Medium = several modules or schema/operational coordination; High = data migration/cross-module workflow with substantial policy and recovery testing. Estimates are relative engineering scope, not delivery commitments. Team availability, school decisions, production state and hosting capabilities are unverified.

The phases organize work, not excuses to defer a release blocker. Minimal mobile navigation belongs in Phase 1; broader accessibility polish belongs in Phase 4. Private-file migration/data safety and essential backup verification also start in Phase 1. Do not deploy after a phase merely because its numbered tasks are complete; use the release gate below.

### Phase 1 - Essential improvements

- **Tasks:** close target/ownership authorization (AUD-001/AUD-002); private-file handling and migration plan (AUD-003); repair/validate release packaging (AUD-004); secure privileged bootstrap (AUD-005); repair alumni form, registrar CRUD paths and mobile menu (AUD-006 through AUD-008); resolve roleless onboarding and forecast role mismatch (AUD-009/AUD-020). Add direct denial and critical-journey regression tests with each fix. Establish a recoverable backup before migration.
- **Reason:** prevents privilege abuse/data disclosure and restores basic use before exposing real records.
- **Expected outcome:** only intended actors reach each record; existing role workflows complete; clean releases contain no runtime data; proofs remain accessible only through authorization.
- **Dependencies:** approved actor-target matrix, production/exposure status, account-provisioning choice, inventory of existing attachments/public mappings and hosting layout.
- **Complexity:** Medium overall; private-file/data migration and exposure review may be High.
- **Risks:** accidental lockout, lost proof links, public copies left behind, registration-policy disruption and regressions in uncommitted settings work. Preserve backups and test migrations before cleanup.
- **Exit gate:** foreign-target denial tests pass; admin/registrar/student/alumni journeys pass; archive inspection passes; private access verified with direct public URLs denied. Existing source changes are reviewed/preserved.

### Phase 2 - System reliability

- **Tasks:** restore deterministic green tests (AUD-025); common FormRequests/status rules (AUD-012/AUD-014); transactional request creation/file compensation and idempotency (AUD-010); reliable status/mail delivery (AUD-011); stale-update protection; minimal request history; settings/schema consistency and safe migration/retention decisions (AUD-013/AUD-015/AUD-016); CSV defenses; safe AI error/schema/calendar/parser handling (AUD-017/AUD-018/AUD-021/AUD-022/AUD-023). Configure queue operation, failures/alerts and recovery rehearsal. Verify production engine behavior.
- **Reason:** prevents partial success, silent history loss, contradictory states and recurring regressions.
- **Expected outcome:** persistence/delivery failures are recoverable; validation is consistent; changes have an actor/time/reason; tests are deterministic across the supported engine paths.
- **Dependencies:** Phase 1 enforcement; agreed lifecycle/retention; worker or scheduler availability; reviewed migration mapping and backup access.
- **Complexity:** High overall, mainly lifecycle/retention/migration and delivery coordination.
- **Risks:** changed validation rejecting legacy data, duplicate job effects, worker misconfiguration and schema changes that are difficult to roll back. Use staged forward migrations, idempotent jobs and explicit recovery procedures.
- **Exit gate:** no current-suite failures; storage/SMTP/retry/stale-update tests pass; production-engine migration checks pass; isolated backup restore and worker-failure recovery demonstrated.

### Phase 3 - Functionality improvements

- **Tasks:** implement agreed correction/resubmission (F-01), release history/confirmation (F-02), payment-proof review if required (F-03), actionable queues and server search (F-04); align export/list filters and preserve score fairness. Add delivery visibility/retry (F-05) around the Phase 2 mechanism.
- **Reason:** closes registrar/student service gaps after trustworthy persistence and authorization exist.
- **Expected outcome:** students can resolve corrections; staff can explain request history/release; search and work queues match actual actionable records.
- **Dependencies:** approved transition map, correction/payment/release ownership and service targets; Phase 2 history/delivery primitives.
- **Complexity:** Medium per feature; High combined if payment/release policy is unsettled.
- **Risks:** overrestricting legitimate exceptions, exposing staff-only notes, retaining too much sensitive data and scope expanding into accounting. Keep audited overrides and explicit visibility rules.
- **Exit gate:** school representatives accept complete request-to-release and correction journeys; no duplicate requests are needed for recoverable errors.

### Phase 4 - UI/UX improvements

- **Tasks:** consolidate repaired form/status/filter/dialog components; correct labels/focus/error associations; responsive panels/grids; improve next-action messages and dashboard hierarchy; make exports/search scope clear; consolidate active asset behavior with browser checks (AUD-027/AUD-029/AUD-030).
- **Reason:** makes established workflows easier to learn and accessible without masking underlying defects through redesign.
- **Expected outcome:** consistent interaction and useful feedback across roles, keyboard operation and narrow screens.
- **Dependencies:** stable workflow contracts and Phase 1 mobile fix; chosen supported browser/device matrix.
- **Complexity:** Medium.
- **Risks:** Alpine version migration breaks dialogs; style cleanup changes hidden states or focus behavior; visual work expands without measurable usability benefit.
- **Exit gate:** keyboard/manual accessibility checks and representative browser journeys pass, including long text, errors, empty lists and slow operations.

### Phase 5 - Performance and scalability

- **Tasks:** baseline query counts/latency, add measured indexes and bounded notification reads if not already fixed; parameter-keyed forecast cache/locks/jobs (AUD-019/AUD-024); settings-cache invalidation; remove repeated aggregates/duplicate query logic as measured; optimize assets and inspect representative concurrent staff usage.
- **Reason:** remove observed growth bottlenecks with evidence while keeping infrastructure supportable. Cheap unbounded-query fixes should be pulled earlier; this phase is for wider profiling/tuning.
- **Expected outcome:** bounded query/memory behavior, responsive dashboards during provider outages and known capacity/headroom on the selected hosting plan.
- **Dependencies:** realistic anonymized dataset/load targets, stable queue operation and production-engine choice.
- **Complexity:** Medium; High only if measurements justify infrastructure/database migration.
- **Risks:** stale/permission-unsafe caches, too many indexes, unrealistic load tests and unnecessary infrastructure. Record before/after measurements and invalidation behavior.
- **Exit gate:** agreed representative workloads meet measured response/queue targets; no permission leakage or extra logical effects under concurrency.

### Phase 6 - Future enhancements

- **Tasks:** consider previewed student import (F-06), forecast evaluation/baselines (F-07), service reminders, richer operational metrics and institutional integration only when demand/ownership is established. Revisit shared storage/Redis/replicas if capacity/support evidence warrants them.
- **Reason:** improve efficiency after essential quality and operational ownership are established.
- **Expected outcome:** less recurring manual work and measurable operational value, without turning DMS into an unsupported SIS/accounting platform.
- **Dependencies:** demonstrated user demand, reliable identifiers, retention/consent/communication policy where applicable, support budget and an accountable module owner.
- **Complexity:** Medium to High depending on the approved feature/integration.
- **Risks:** scope creep, additional private data/provider commitments, unreliable forecasts and ongoing integration support costs.
- **Exit gate:** each feature has a small accepted scope, measurable success criterion, tested failure/recovery behavior and an owner.

### Production release gate

Before real-world deployment, require: closed AUD-001/AUD-002 and verified private files; clean artifacts/no seeded privileged defaults; working role/onboarding/alumni/mobile journeys; no unexplained failing tests; reliable persistence/delivery and defined retention; validated configuration and selected database migrations; recovery/worker smoke checks; and current dependency/security review. Existing AI can remain advisory and unavailable without blocking core workflows, but enabled output must have safe errors, validation and truthful status. Do not label an unresolved security issue an accepted cosmetic backlog item.

## 8. Risks, decisions and implementation controls

| Decision/risk | Recommended control before implementation |
| --- | --- |
| Existing production exposure is unknown | Determine whether real records/accounts or archives have been distributed. An audited vulnerability is not proof of exploitation; any account/file access review must preserve evidence and confidentiality. |
| Existing uncommitted settings work | Review its intent and preserve it; keep remediation changes focused. Do not reset the working tree to simplify the work. |
| Who may register/manage students? | Agree on role/target policy and institutional identity checks; do not merely assign student role to every public registrant. |
| How long must records/proofs remain? | School approves retention, deactivation/deletion and archive policy. No arbitrary statutory period is asserted here. |
| Payment/release authority is unclear | Registrar/finance agree review, exceptions and release confirmation before adding gates. |
| Catalog customization is advertised but inconsistent | Choose fixed-domain limitations or referenced extensible catalogs explicitly; migration must preserve existing values and requested functionality. |
| Historical data cannot supply missing audit facts | Mark pre-history records as legacy; do not fabricate actor/payment/release events during backfill. |
| Queues create operational obligations | Confirm worker/scheduled-drain support, alert responder, retries/timeouts, stable payloads and duplicate handling before switching production delivery. |
| Migrations/file relocation can be irreversible | Test on a restored copy, verify counts/readability, keep recovery checkpoints and use a reviewed forward repair for already-applied migrations. |
| Sensitive data in troubleshooting | Use sanitized logs/anonymized test fixtures; never copy production proofs/secrets into repository, ZIPs or CI artifacts. |
| Refactoring obscures behavior changes | Pair each fix with acceptance tests; separate policy decisions, data migration and cosmetic refactoring into reviewable changes. |

A reasonable release decision should depend on demonstrated gates rather than the audit's aggregate score. No dates, throughput limits or legal retention obligations are invented by this plan.

## 9. Final professional assessment

### 1. What would I improve first as lead developer?

Close registrar-to-administrator targeting and cross-student request access with policy tests, then protect proofs and prevent unsafe packages from being distributed. These issues can compromise records regardless of how polished the interface looks. Repair registrar UI only after its target authorization is safe.

### 2. What are the five most important improvements?

1. Record-level authorization and ownership tests.
2. Private documents, clean releases and secure provisioning/recovery.
3. Complete alumni, registrar, onboarding and mobile journeys.
4. Transactional/idempotent workflow changes with reliable delivery/history.
5. Deterministic regression tests and operational release gates.

### 3. Which missing features add significant value?

Correction/resubmission, accountable status/release history and explicit proof review provide the most immediate service value. Queue aging and delivery recovery follow closely. Batch import is worthwhile only if staff regularly provision students in bulk; forecast backtesting is useful after historical data is reliable.

### 4. Which technical decisions should remain unchanged?

Laravel/Blade, Eloquent relationships, framework authentication, Spatie roles, server-side validation, database notifications, streamed CSV and a single application deployment. Keep existing pagination/eager loading patterns. Retain the existing database engine where verified operational needs support it; do not replace it solely for fashion.

### 5. What must happen before deployment?

Meet the production release gate in Section 7: authorization/privacy, clean artifacts/provisioning, complete core journeys, passing relevant tests, safe persistence/delivery, production configuration/migration verification and a demonstrated recovery path. Review actual advisory/hosting state rather than assuming local checks certify production.

### 6. What can safely be postponed?

A SPA rewrite, microservices, Redis/distributed infrastructure, extensive visual redesign, large-report services without size evidence, SIS/payment integrations, advanced forecast presentation and bulk import without recurring demand. Minimal accessibility/navigation, data integrity and privacy fixes cannot be postponed as optional polish.

### 7. What is the long-term direction?

Develop a focused, supportable registrar document service with explicit permissions, a consistent request lifecycle, private evidence, accountable history and reliable delivery. Grow modules incrementally inside the existing Laravel application; add infrastructure and integrations when measured demand and maintenance ownership justify them. Measure success through safe completed requests, fewer manual corrections and recoverable operations rather than feature count.

**Approval boundary:** this document authorizes no implementation. The next step is user approval of a bounded remediation phase and any required school-policy decisions, followed by reviewable code/tests/migrations. No implementation has begun.
