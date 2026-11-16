# Current project fix matrix

Updated: 2026-11-12. This tracks implementation against the original [audit](PROJECT_AUDIT_REPORT.md) and [recommendation roadmap](PROJECT_IMPROVEMENT_RECOMMENDATIONS.md). Those reports describe the original findings; they are not completion checklists.

**Agreed order:** remaining reliability fixes â†’ settings/data integrity â†’ forecasting fixes â†’ local UAT and corrections â†’ hosting preparation and deployment. Local UAT comes **before deployment**. Automated developer regression checks are not UAT. No production deployment or real email sending is authorized by a matrix status.

"Local fix" means implemented with relevant local regression coverage, not verified on a hosting server. "Partial" means material work or verification remains. Existing passwords/accounts are not silently rotated, and historical actions/data are not invented.

## Audit findings

| ID | Area | Current position | Remaining work |
| --- | --- | --- | --- |
| AUD-001 | Staff account-target authorization | Local fix | Maintain denial tests for privileged/mixed-role targets. |
| AUD-002 | Student request ownership | Local fix | Maintain foreign-request/file denial tests. |
| AUD-003 | Public payment proofs | Local fix; hosting verification pending | Private migration completed locally; verify actual deployed public mapping and authenticated access. |
| AUD-004 | Unsafe release archives | Local fix | Use new verified archives; old ZIPs are not certified safe. |
| AUD-005 | Default privileged credentials | Partial | Seeder/bootstrap fixes implemented. Review and rotate any legacy default credentials before public access. |
| AUD-006 | Alumni form serialization | Local fix | Include alumni journey in later local UAT. |
| AUD-007 | Registrar CRUD routes | Local fix | Include registrar create/edit/delete in later local UAT. |
| AUD-008 | Mobile navigation | Local fix | New shared layout retains tested focus/dismissal behavior. |
| AUD-009 | Roleless registration | Local fix | School-provisioned invitation/activation flow implemented; real mail verification remains. |
| AUD-010 | Partial request persistence | Local fix | Transaction, file compensation and submission retry protections implemented. |
| AUD-011 | SMTP breaks saved status | Local fix; hosting verification pending | Outbox/retry implemented; verify actual scheduler/provider after local UAT. |
| AUD-012 | Managed password validation | Local fix | Student/registrar create and update reject bcrypt inputs over 72 bytes and null bytes before writes; retain minimum length and confirmation checks. |
| AUD-013 | Configurable types vs enums | Local fix | Three supported codes shared across settings/forms/validation/display/CSV; labels editable, core codes protected, legacy entries preserved for review. |
| AUD-014 | Bulk weight validation | Local fix | Bounds, complete keys and atomic writes implemented; stored-score policy stays explicit. |
| AUD-015 | Course normalization migration | Local fix; historical recovery limited | Fresh/pending migration and additive forward migration preserve custom courses. Local forward migration applied after verified backup; all user rows unchanged. Previously cleared values require a trusted backup. |
| AUD-016 | Deletion and file/history lifecycle | Local safety fix; retention policy pending | Web deletion blocks request owners, recorded staff actors and the last admin. Registrar targets are scoped; unused-account cleanup is transactional. Retention/deactivation and historic orphan reconciliation remain separate work. |
| AUD-017 | CSV formula injection | Local fix | Escaping implemented in both exporters. |
| AUD-018 | AI output schema | Local fix | Validate exact future months, integer bounds, IDs, vector lengths, narrative shape and matching totals before caching; compute summary locally. |
| AUD-019 | Synchronous/unthrottled generation | Local safeguards; bounded synchronous design retained | GET reads only; explicit POST generation has throttle, shared lock, cooldown and one provider request capped at 25 seconds. Background worker deferred; verify host execution limits/shared cache. |
| AUD-020 | Registrar forecast role mismatch | Local fix | Registrar-specific read/generate routes and widget URL; students denied both staff endpoints. |
| AUD-021 | Calendar/weekday analytics | Local fix; hosting engine verification pending | Month-start arithmetic, reset-day parsing and corrected MySQL weekday conversion; boundary and mapping tests pass. |
| AUD-022 | Mixed-text JSON parser | Local fix | Quote/backslash detection fixed; mixed-text strings with embedded delimiters tested. Invalid JSON rejected. |
| AUD-023 | Provider exception disclosure | Local fix | Safe HTTP/CLI errors with diagnostic reference/type; no provider body or API key returned. Key moved from query string to header. |
| AUD-024 | Indexes/notification query growth | Partial | Notification reads are bounded; measure queries and add justified indexes later. |
| AUD-025 | Failing/incomplete regression suite | Partial | Current suite passes and targeted coverage expanded; remaining findings still need their own tests. |
| AUD-026 | Duplicate model/domain logic | Pending; defer broad cleanup | Shared actions/queries introduced where needed. Avoid unrelated refactoring before functional fixes. |
| AUD-027 | Frontend asset delivery | Partial, deliberate compatibility | Pinned local Tabler/Alpine/charts implemented; legacy Tailwind utilities and profile Vite entry retained. |
| AUD-028 | Destructive demo cleanup | Partial; production guard implemented | Old cleanup body remains destructive/engine-specific; do not use it for real data. |
| AUD-029 | Search/status/queue mismatch | Local fix | Shared server queries, filters/CSV, pending counts and active queue corrected. |
| AUD-030 | Accessibility/responsive UI | Partial | Approved Tabler design integrated; targeted browser checks passed. Full accessibility review/local UAT remains. |
| AUD-031 | Operational runbooks | Partial | Preflight and local recovery drill completed; off-host recovery and hosting execution remain unverified. |
| AUD-032 | Production/advisory verification | Pending release gate | Actual environment/security/advisory review is not certified by local tests. |

## Additional roadmap work

| Item | Status | Scope / next action |
| --- | --- | --- |
| Stale staff request edits â€” Phase 2 | Local fix; full regression checks passed | Signed snapshot checked under the transaction lock for status/remarks and archive/unarchive. No schema migration needed. |
| Minimal request history | Local fix | Submission, status/remarks, archive/unarchive recorded transactionally; not a tamper-proof retention ledger. |
| Correction/resubmission (F-01) | Pending business decision | Agree the allowed corrections and attachment-version behavior before implementation. |
| Release confirmation/payment review (F-02/F-03) | Partial / decision needed | History exists; explicit release/payment metadata and ownership are not implemented. |
| Delivery-management UI, imports, reminders | Optional later | Core outbox exists; extra screens/imports are not required merely for modernization. |
| Local UAT | Not started; isolated base setup verified | [Checklist](LOCAL_UAT_CHECKLIST.md) prepared; password fix and isolated base setup verified; see [access](LOCAL_UAT_ACCESS.md). AI/SMTP simulation cases remain blocked. |
| Deployment | Not started | After local UAT corrections; verify Namecheap engine/PHP/private paths and five-minute cron limits. |

## Immediate execution order

1. **Completed:** stale-edit protection and documentation. Current regression suite: 215 tests / 1270 assertions passed; four navigation tests passed in the preceding batch (navigation unchanged). This is developer verification, not UAT.
2. **Completed:** AUD-013/AUD-015 and remaining managed password bounds; additive local catalog migration backed up and verified without changing user rows.
3. **Safety guards completed:** AUD-016 web deletion restricted to unused accounts. Agree retention/deactivation policy before any future destructive cleanup.
4. **Local fixes completed:** forecasting AUD-018 through AUD-023. Background generation remains optional; host execution limits, shared locks and controlled provider checks remain release checks.
5. Reconcile pending release blockers, then conduct **local UAT**, fix findings and rerun relevant checks.
6. Prepare/verify hosting and deploy only after local acceptance. No step above means production has already been tested.


## Latest batch: before and after

| Area | Before | After |
| --- | --- | --- |
| Student categories | Settings accepted codes that fixed database enums and other forms could not support. | Only `regular`, `graduating`, and `alumni` are selectable; their display labels are customizable and shared across screens and exports. Core codes cannot be deleted. Existing unsupported settings remain visible for review and can be removed only when unused. |
| Program migration | The normalization migration rewrote course aliases and cleared unmatched values. | Both the pending historical migration and a new forward migration use an additive catalog merge; custom course strings and all user rows remain untouched. Repeated execution is idempotent. |
| Program removal | A program could be removed while accounts still used it. | Removal reports a validation error while any account references it; unused program removal remains available. |
| Managed passwords | Character limits did not reliably prevent bcrypt errors for long or multibyte input. | Student/registrar create and update reject over-72-byte bcrypt inputs and null bytes before mutation, with a validation message. |

Implementation: [category definitions](app/Support/StudentCategory.php), [settings guards](app/Http/Controllers/Admin/SettingsController.php), [catalog preservation](app/Services/PreserveProgramCatalog.php), [forward migration](database/migrations/2026_11_12_000001_preserve_existing_program_catalog.php), [password rule](app/Rules/PasswordHashInput.php).

Verification: 179 PHP tests / 960 assertions and four navigation tests passed; Blade compilation passed. Migration tests cover custom/unmatched courses, case variants, malformed catalogs, unchanged user rows and repeated execution. A verified private SQLite backup was created before the local forward migration; the backup contained 578 requests and the full user-row checksum was unchanged after migration. No source accounts were created or deleted by this batch. These are developer checks, not local UAT or hosting verification.

Limitations: already-cleared historical courses cannot be reconstructed from the current database. Category storage remains the existing fixed enum; arbitrary new business categories require a separate schema/workflow decision. Account deletion and last-admin protection remain the next matrix item.


## Account deletion safety batch

| Area | Before | After |
| --- | --- | --- |
| Student/account deletion | Owner deletion could cascade-delete requests, history and attachment rows while leaving private files behind. | All three web deletion paths share [DeleteUnusedAccount](app/Services/DeleteUnusedAccount.php). Any owned request, including completed or archived requests, blocks deletion and retains files/history. |
| Staff history | Actor deletion could null historical actor references. | Accounts referenced as request-event actors are retained. |
| Last admin | A sole admin could self-delete. | Transactional role-row locking serializes deletion checks; the last admin is rejected. An unused admin can leave if another admin remains. |
| Registrar management | Edit/update/delete accepted arbitrary account IDs. | List/edit/update/delete manage registrar-only accounts; admin, student, roleless and mixed-role targets are excluded. |
| Unused-account removal | Session/reset-token/notification cleanup was incomplete; self-service logged out before deletion. | Owned database sessions, reset tokens and notifications are cleaned transactionally. Logout follows successful deletion; blocked users stay signed in and see the reason. |

Verification: 190 PHP tests / 1045 assertions passed, including 11 new deletion/authorization cases; Blade compilation passed. Tests used isolated SQLite, fake storage and synthetic accounts. No real accounts/files were deleted and no migration is needed. Navigation was unchanged; its four tests passed in the preceding batch.

Limitations: these safeguards apply to web flows, not manual SQL or destructive demo commands. Existing orphan files are not swept and lost records are not reconstructed. SQLite tests do not certify MySQL concurrency; verify locking/foreign keys on the eventual host before release. Deactivation and retention periods require a separate business decision.

Following batch: forecasting fixes (completed below), then release-blocker reconciliation and local UAT before deployment.


## Forecasting safety batch

| Area | Before | After |
| --- | --- | --- |
| Page load | Cache misses could trigger provider work on GET, including repeated loads. | GET never generates or writes cache. Explicit staff Generate button sends CSRF-protected POST; no saved forecast returns 202 (nothing is queued). |
| Provider resource use | Retries and fallback models could multiply long synchronous calls. | One provider request, at most 25 seconds, five-second connection cap; three POST attempts/minute per user, shared generation lock (180 seconds), shared 60-second generation cooldown including failures. |
| Output | JSON arrays could be accepted without domain validation. | Exact next-month sequence/horizon, bounded integer counts, document identity/uniqueness, vector lengths, narrative lists and matching totals are checked. Names use the source catalog; application summary replaces provider summary. Invalid output never replaces saved cache. |
| Staff roles | Registrar widget called an admin-only URL. | Each staff dashboard uses its matching read/generation endpoint; student access is denied. |
| Calendar analytics | Month-end subtraction/addition could duplicate or skip months; partial month parsing and MySQL weekday conversion were wrong. | Calculations start at day one, parsed months reset unspecified fields, MySQL Sunday maps to Sunday. |
| Parser and errors | Escaped quote detection was incorrect; raw provider exceptions could reach clients. | Embedded quoted braces/backslashes parse correctly. Safe errors expose a reference; logs retain reference and exception class, without raw provider text. API keys use a header. |
| Saved forecasts | Legacy malformed/stale results could be reused without a clear contract. | Version/parameters/current-month scoped, revalidated before use. Expired matching results remain readable with an outdated label; new month or incompatible parameters require explicit generation. |

Verification: 203 PHP tests / 1116 assertions and four navigation tests passed. Blade compilation passed; registrar dashboard rendering with AI enabled is covered. Thirteen new forecast tests cover schema rejection, exact months, month-end history, February workload, weekday mapping, role access, cache behavior, lock/cooldown, failure preservation and mixed-text parsing. All provider calls were faked; no live AI/email or deployment occurred. No migration or dependency change is required.

Operational limits: this deliberately retains bounded synchronous generation for a small capstone. The 202 read response does not promise a background job. Shared database/file cache with working atomic locks is required across web requests; array/null cache is unsuitable outside isolated tests. Hosting database concurrency, real provider output compatibility, PHP/proxy timeouts and actual network behavior remain unverified. Existing legacy cache is retained but ignored until a valid explicit generation replaces it. Forecasts remain estimates, not guaranteed demand.

Next: reconcile remaining essential/partial matrix items and prepare the local role-based UAT scenarios. UAT and deployment have not started.


## Forecast review follow-up

Both review findings are addressed. [DocumentTrendForecaster](app/Services/DocumentTrendForecaster.php) now returns the validated forecast together with the exact overall/per-document history used in its prompt. The controller persists this snapshot instead of querying the catalog again after the provider call. Existing forecast-only CLI/service callers retain their original return shape.

[ForecastFailure](app/Services/ForecastFailure.php) supplies allowlisted failure reasons/stages and bounded provider HTTP status diagnostics. HTTP and CLI logs share this contract and retain the client-visible reference without raw exception messages, response bodies or API keys. Unexpected exceptions remain labeled internal/unexpected.

Verification: 206 tests / 1165 assertions passed. Added regressions change the catalog during a fake provider call and verify subsequent cached reads, distinguish configuration/HTTP/parsing/validation failures in safe logs, and verify CLI diagnostics. Navigation was unchanged; its four tests passed in the preceding batch. No live provider calls, schema changes, source-account changes, UAT or deployment occurred.


## Local UAT entry reconciliation

Read-only review on 2026-11-12: local `deploy:check` passed seven checks (key, connection, migrations, required tables, private storage, attachment existence, no public legacy proofs). All 16 migrations were applied. No jobs, emails, source-account changes or production-mode checks were run. Latest completed automated suite remains 206 tests / 1165 assertions; it was not rerun for this documentation-only review.

**Entry-review finding (resolved in the follow-up below):** managed student/registrar password validation (AUD-012) is complete, but adjacent profile-change and reset controllers lack hash-input bounds/null-byte validation. Activation lacks the null-byte check. Apply the shared rule and add rejection/success regressions before local UAT. Source inspection confirmed this gap; this review did not reproduce it in a browser or change those controllers.

| Remaining item | Local UAT treatment | Release/longer-term treatment |
| --- | --- | --- |
| Password self-service/reset/activation | Fixed and regression-tested in the follow-up below; keep managed-account completion distinct. | Recheck all credential journeys in local acceptance. |
| AUD-005 legacy credentials | Use synthetic isolated accounts; no credential rotation against existing accounts during preparation. | Review/rotate legacy defaults before public access. |
| AUD-003/011/019/021/031 hosting behavior | Local scenarios use private fixture storage, local mail capture and a controlled AI provider. | Actual web mapping, SMTP/cron, MySQL concurrency and off-host restore remain release gates. |
| AUD-024/026 query tuning/refactoring | Do not block small local UAT unless measurable latency affects scenarios. | Profile representative data; avoid speculative infrastructure or broad cleanup. |
| AUD-027/030 frontend compatibility/accessibility | Exercise assets, forms, mobile and keyboard behavior during UAT; fix blocking usability defects. | Full accessibility certification is not claimed. |
| AUD-028 demo cleanup | Exclude destructive commands; isolated synthetic fixtures only. | Do not use cleanup for real records. |
| F-01/F-02/F-03 scope | Test current documented workflows; record correction/release/payment metadata gaps as scope decisions. | Additional business workflows require an agreed scope, not an assumed implementation. |
| AUD-032 advisories/environment | No new live advisory scan or dependency change performed in this review. | Fresh advisory review and production preflight before release. |

[Local UAT checklist](LOCAL_UAT_CHECKLIST.md) contains 20 role-based scenarios, setup requirements, evidence/defect recording and exit criteria. Every scenario is Not run. The password-path fix is completed below. Next action is isolated setup and local UAT; deployment remains afterward.


## Password-path entry fix completed

Profile password changes, password resets and activation now apply the shared hash-input validation rule before hashing. Existing minimum length, confirmation, current-password and token/identity checks remain. Activation retains its existing 72-character maximum; bcrypt additionally enforces its byte limit and rejects null bytes. No database migration or dependency change is needed.

Nine new regression cases prove oversized ASCII, oversized multibyte and null-byte rejection on all three paths, unchanged account attributes and still-usable reset/activation tokens, followed by successful valid 72-byte retries. Full suite: **215 tests / 1270 assertions passed**. Tests use isolated SQLite; no existing account passwords were changed and no live mail or AI was used. Navigation was unchanged; its four tests passed in the preceding UI batch.

The identified password blocker is resolved. [Local UAT checklist](LOCAL_UAT_CHECKLIST.md) is updated; all 20 browser scenarios remain Not run. Next: isolated local UAT setup, verify effective paths/capture transports, then execute scenarios and record findings. Deployment remains after local acceptance.


## Isolated local UAT setup

[Access/run instructions](LOCAL_UAT_ACCESS.md) recorded. Temporary runtime checks enforce separate SQLite/storage/file sessions/cache/compiled views, local log-mail capture and disabled live Gemini. Nine synthetic accounts, six fixture requests and six private PDF proofs created only in the isolated environment. A visible local banner and response header distinguish it from other app instances.

Verification: isolated preflight seven checks passed; login page rendered with the banner; source database checksum remained unchanged. No source code, working accounts, working environment file or application key changed during setup. No automated suite rerun was needed for temporary fixtures/documentation. UAT scenario results are still Not run; setup smoke checks are not acceptance. AI provider success/failure and SMTP outage/recovery browser cases are blocked until controlled service simulations are configured. Local mail is captured to a file, not delivered through SMTP.

Next: execute available role-based local UAT scenarios, record defects/evidence, and address blocked service simulations explicitly. Deployment remains after local acceptance and release checks.


## Tabler chart presentation update

Replaced the shared dashboard Chart.js presentation with Tabler cards/grid and locally pinned MIT ApexCharts. Monthly trend, category donut and document bars retain server data and forecast behavior. Default expanded analytics, responsive columns, light/dark support and data tables added. Developer checks: 215 PHP tests / 1270 assertions, seven frontend tests, Blade compilation and isolated desktop/mobile SVG rendering passed. See [frontend notes](FRONTEND_DESIGN.md). Full local UAT remains pending.

## Spark Admin v1.0.0 frontend migration

All active role dashboards, request pages, management/settings screens, profile and authentication now share the Spark design foundation. Backend contracts and database schema were unchanged by this migration. Final developer verification: 215 PHP tests / 1,270 assertions; seven frontend tests; Blade compilation; isolated browser registrar CRUD, student PDF submission/download and status/history update. Source database checksum unchanged. See [migration report](FRONTEND_MIGRATION_REPORT.md) for files, evidence and limitations. Formal local UAT remains pending; deployment stays after acceptance.

## Academic calendar and synthetic forecasting dataset

Implemented admin semester/event editing, registrar read-only access, archive/history retention, inclusive-date validation and optimistic revision checks using existing settings storage. Calendar edits mark cached forecasts stale. Added guarded reproducible synthetic weekly dataset generation; six assumed semesters/390 rows generated only in private isolated UAT storage. No working request records/schema changes. Calendar context/model training and staffing calculations remain the next stage, not completed forecasting capabilities. Verification: 222 PHP tests / 3,262 assertions, seven frontend tests, Blade compilation and isolated browser saves/layout passed. See [calendar/data notes](ACADEMIC_CALENDAR_AND_FORECAST_DATA.md). Formal UAT remains pending.

Academic-year refinement: added editable June-May academic years, scoped calendar filter/month bounds and semester-year dropdown. Existing year labels receive stable compatibility links without changing saved dates on GET. Full validation: 228 PHP tests / 3,307 assertions and seven frontend tests passed; isolated browser year save/bounds verified. See calendar/data notes for details. Training is still pending.

## Current implementation reconciliation — 2026-11-13
- Priority settings consolidates category management/weights, urgency and document weights. Custom categories work in SQLite UAT; the existing database enum remains a MySQL portability/release risk requiring review before hosting.
- Reports & analytics implemented for admin/registrar with shared filtered aggregates, CSV and a dependency-free text PDF summary. Submission-date membership and current status are explicitly distinguished. Last full regression verification: 239 PHP tests / 3,403 assertions and seven frontend tests; browser totals/export checks matched.
- Isolated UAT now contains 1,500 generated students and 10,251 generated requests, with current-semester-only unarchived fixtures. That archive operation is fixture preparation, not an automatic semester rollover feature.
- Academic calendars cover AY 2021–2022 through 2026–2027. Historical calendars copy the reference year; those copied dates remain synthetic assumptions. First-semester enrollment June 8–20, classes June 22, preliminary July 23–25, midterm September 11–14 and finals October 19–21 are incorporated, shifted per historical year.
- Offline numeric forecasting experiment completed: 283 full weeks / 3,113 document-week rows / 10,195 included synthetic requests. Train, validation, test and current holdout are chronological. Ridge penalty selected using validation only; test predictions recursively use predicted lags rather than held-out actuals. Model trained through validation ends 2025-06-01; test covers 52 weeks from 2025-06-02. Current holdout remains unused.
- Test result: calendar-aware ridge document/week MAE 1.404, WAPE 43.54%; four-week average MAE 1.620; seasonal-52 MAE 1.892. Aggregated weekly total WAPE 12.43% versus 20.35% and 17.24% for those baselines. This is synthetic feasibility evidence, not real-world forecasting accuracy.
- New model is offline only. Existing dashboard/provider forecast is unchanged. Staffing capacity, forecast UI integration, prediction intervals and real-data validation are pending. Synthetic processing timelines cannot validate staffing estimates.
- Next: review model results and horizon behavior, agree explicit handling-time/staff-capacity inputs, then integrate an honestly labeled local forecast. Formal role-based UAT and production release gates remain pending.

## Local UAT execution - November 16, 2026

See [dated execution results](LOCAL_UAT_RESULTS_2026-11-16.md) for scenario-level evidence. Final regression: 251 backend tests / 3,499 assertions and 13 frontend tests passed. Fixed and retested mobile pagination overflow and obsolete fixed-calendar input to Gemini. Browser execution covered authentication/role denial, account create/edit/delete, captured invitation/reset, private proof submission and idempotent retry, request lifecycle/stale edits/archive, notification filters, report downloads and responsive layouts. Temporary accounts were removed; completed UAT request 40214 retained. Working database hash unchanged.

Release remains pending: latest live Gemini refresh timed out (saved cache retained), MySQL custom-category enum compatibility, staging/cron/SMTP/private-storage/HTTPS checks, release/restore checks and client acceptance. Automated passes do not establish live forecast reliability or production readiness.
