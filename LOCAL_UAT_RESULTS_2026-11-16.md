# Local UAT execution results - November 16, 2026

## Decision

**Local developer verification passed for the tested workflows after fixes. Production release is not yet approved.** Live forecast generation timed out during this run; saved-cache recovery worked. Hosting verification and client acceptance remain open. Automated results are not a substitute for client sign-off.

## Environment and safeguards

- Isolated local UAT at `http://127.0.0.1:8876`, PHP 8.2.12, SQLite, local captured email.
- Started with 10,000 students and 40,213 requests. A SQLite backup was created before browser mutations, under the private local UAT storage directory.
- PHPUnit used a separate in-memory SQLite database, temporary storage, fake mail, and controlled HTTP responses. No working database was used for automated tests.
- Working database SHA-256 before/after: `855f4120cf3033bad21705291b5ee98307f4ca784cada764d175c291f35e91f4` (unchanged).
- Temporary student and registrar accounts were removed after testing. The completed browser test request, ID **40214**, its attachment and history remain as traceable UAT evidence. Final totals: 10,000 students, 3 registrar accounts, 40,214 requests. SQLite foreign-key check returned no errors.
- No production deployment or external email delivery was performed. One live Gemini generation request was made; controlled provider failures were also covered by automated tests.

## Automated verification

Final complete run after fixes:

- **251 PHPUnit tests passed, 3,499 assertions.**
- **13 frontend tests passed** (chart alignment, forecast cards, navigation and focus behavior).
- Initial failures were stale test fixtures: the old schedule wording and a browser API missing from the Node test sandbox. Updated those fixtures; did not weaken authorization or validation assertions.
- Read-only `deploy:check` passed encryption-key configuration, database connectivity, applied migrations, required tables, private storage placement, referenced attachment existence and absence of legacy public proof files. Production security mode was not evaluated locally.
- `schedule:list` confirms email delivery every minute and forecast refresh at midnight/noon. This verifies definitions, not an installed or running hosting cron.

## Browser and local acceptance evidence

The following distinguishes browser/HTTP execution from automated-only coverage. Expected 403/404/422 responses during negative tests are not application defects.

| Checklist | Execution and result |
| --- | --- |
| U01 - Authentication and roles | Browser login/logout exercised admin, registrar and student roles. Student requests to staff dashboard/report/request routes returned 403. Registrar access to admin registrar management returned 403. Passed; additional role combinations covered by tests. |
| U02 - Account activation | Created a disposable student through the staff form, issued an invitation to local mail capture, then opened the captured link in the browser. Wrong student identity returned 422, valid activation reached the student dashboard, and replay returned 422. Expiry behavior covered automatically. Tokens excluded from this report. |
| U03 - Password recovery | Browser requested reset mail for the disposable account. Captured reset link opened successfully, mismatched confirmation returned 422, valid reset returned to login. Invalid/expired tokens and authenticated password changes also have automated coverage. |
| U04 - Student management | Staff created and edited a disposable student through application forms; updated name persisted. Account removed after invitation/reset testing. Privileged/mixed-role protections and custom labels covered automatically. |
| U05 - Registrar management | Admin standalone create/edit forms worked; disposable registrar was deleted and original count restored. Automated tests cover protected and mixed-role targets. Visible animation review of the modal variant remains a human check; the shared browser reports itself hidden and pauses Alpine transitions. |
| U06 - Request submission and retry | Student uploaded a PNG proof and submitted the same submission token twice. Exactly one request and one submission event were created. Request ID 40214. Passed. |
| U07 - Rejected input and uploads | Missing proof returned 422 with a proof field error. Invalid counts, file types/sizes/names and storage failure paths covered automatically. |
| U08 - Alumni restrictions | Automated coverage passed; historical fixture eligibility was checked earlier. Fresh alumni-only submission was not separately repeated in this browser run. |
| U09 - Private access | Owner fetched the new proof with HTTP 200/image/png; registrar could access it. Another student's request returned 404. Cross-owner attachment and guest restrictions covered automatically. |
| U10 - Status updates and history | Registrar moved the test request from pending verification to processing, ready for release and completed. Correct events and three email-outbox rows were recorded. Student-side notification and history rendering covered by automated tests. |
| U11 - Stale edits | Reused an old edit token after a successful status change. Second request returned 422 with `edit_token` feedback; newer state/history retained. Passed. |
| U12 - Archive and unarchive | Both operations completed and produced archived/unarchived history events. Confirmation opened and cancel dismissed it without mutation when the actual submit button was used. Count/list consistency covered automatically. |
| U13 - Search, filters and CSV | Staff listing/search/filter/export tests passed. Browser report CSV returned HTTP 200/text-csv; PDF returned HTTP 200/application-pdf with a valid PDF signature. Exact inclusive filters/export totals covered automatically. |
| U14 - Category/program protection | Automated management coverage passed. **MySQL custom-category storage compatibility remains a deployment blocker**, described below. |
| U15 - Priority settings | Automated valid/invalid weights and snapshot behavior passed. Staff settings pages returned HTTP 200. No existing UAT weights were changed during this browser run. |
| U16 - Account deletion safety | Disposable accounts were removed; referenced-account/last-admin protections passed automated tests. A raw browser `DELETE` fetch followed the controller's 302 using DELETE and ended at HTTP 405; deletion itself succeeded. Normal UI uses POST with method override, so this was a test-harness redirect artifact. Database state was checked before any further cleanup. |
| U17 - Forecast access and scheduling | Admin/registrar cached-read and admin-only generation tests passed; scheduled definitions confirmed. Saved calendar integration fixed and regression tested. Latest live refresh timed out at the provider boundary; see open items. |
| U18 - Forecast failure/recovery | Live timeout returned safe HTTP 503. Last successful forecast remained stored unchanged (generated November 15, 23:53:44 +08:00). Controlled malformed output, lock/cooldown and failed-cache preservation tests passed. |
| U19 - Email outage and recovery | Pointed only the isolated test process at an unavailable local SMTP port. Delivery failed/deferred while completed status/history stayed saved. Restored local capture; all four pending messages were delivered, including all three for request 40214, with no extra request history. No external mail was sent. |
| U20 - Responsive/navigation | Registrar dashboard, requests, reports and request detail checked at 1440, 768 and 390 pixels. Fixed pagination overflow; request list retest at 390px had document width 384px, no page overflow. No page JavaScript exceptions or broken image elements in the page sweep. Mobile drawer open/dismiss and notification filter switching exercised. Keyboard focus behavior also covered by frontend tests; real-device and visible-animation acceptance remain human checks. |

Additional browser checks:

- Fifteen admin pages/endpoints, including both notification filters and report exports, returned successful responses.
- Notification All/Unread filters were verified with an unchanged document marker: both filters switched while staying on the same document.
- Desktop/tablet/mobile report layouts rendered without page-level horizontal overflow. Screenshots below are supporting visual records, not proof of every interaction.

## Defects fixed and retested

### UAT-01: Request pagination overflows a phone viewport

- Before: at 390px the request page expanded to 649px because numbered pagination controls could not wrap.
- Fix: shared pagination wrapper allows wrapping and constrains navigation to available width.
- Retest: same request page at 390px had no horizontal page overflow. Shared component also benefits other paginated lists.
- Files: [pagination template](resources/views/vendor/pagination/windmill.blade.php), [portal CSS](public/assets/css/spark-portal.css).

### UAT-02: Forecast prompt uses obsolete calendar assumptions

- Before: Gemini received a hardcoded June 15-to-April calendar, without the saved event dates.
- Fix: include relevant saved years, semesters and events spanning the history/forecast window; retain archived entries as historical context. Remove obsolete fixed dates and instruct the provider not to invent missing dates.
- Retest: new controlled-provider regression asserts saved graduation and historical enrollment dates are in the request. Forecast safety/scheduler/request regressions and the final complete suite passed.
- Files: [forecaster](app/Services/DocumentTrendForecaster.php), [forecast tests](tests/Feature/ForecastSafetyTest.php).
- The previously cached forecast predates this fix; only a subsequent successful generation incorporates the corrected calendar context.

## Remaining work before deployment approval

1. **Live Gemini refresh reliability:** this run hit cURL timeout 28 after the bounded request deadline. Verify a successful generation with the corrected calendar context and confirm the host supports the provider latency. Preserve old-cache fallback; do not label timeout recovery as a successful new forecast. No repeated live calls were made in this run.
2. **MySQL category compatibility:** the users migration still defines `student_type` as an enum containing only regular/graduating/alumni, while application settings allow custom categories. SQLite tests cannot establish MySQL compatibility. Resolve and test this before enabling custom category assignment on MySQL; no schema change was made during this UAT.
3. **Hosting staging:** validate MySQL migrations/concurrency, document root, HTTPS/cookies/debug settings, writable private storage, real SMTP, actual cron execution, cache locking and API/PHP timeout behavior.
4. **Release/recovery:** fresh dependency advisory checks, production packaging without local UAT assets/credentials, and off-host backup restoration remain required.
5. **Human acceptance:** client/owner should review the visible modal/sidebar/filter animations, keyboard and actual mobile-device behavior, and approve the workflows. Browser/automated execution here is developer verification, not a client signature.

The staffing assessment remains an assumption-based monthly calculation using registrar account count, six productive hours within an eight-hour shift, Monday-Saturday, handling-time assumptions and current backlog. It is not attendance tracking or a guarantee against daily surges. All demand records used here are synthetic; no real-school forecasting accuracy is claimed.

## Visual evidence

- [Desktop dashboard](docs/uat/2026-11-16/dashboard-desktop.png)
- [Mobile reports](docs/uat/2026-11-16/reports-mobile.png)

No passwords, keys, activation/reset tokens or uploaded payment proofs are included in this report.
