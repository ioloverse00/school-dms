# Academic Calendar and Synthetic Forecast Data

Implemented 2026-11-12. This is the calendar and dataset preparation stage, not a trained forecasting model.

## Academic calendar

- Administrators: Administration > Academic calendar, or System Settings > Academic calendar.
- Registrars: read-only Academic calendar navigation link. Students have no access.
- Add/edit academic year and semester name/date range, then link events to a semester.
- Event types: enrollment, exams, clearance, graduation, semester-end, holiday, other. Dates are inclusive; events may overlap. Office closure is explicit rather than inferred from an event type.
- Event dates must fit their semester. Editing a semester cannot strand existing events outside it. Duplicate semester names within an academic year are rejected.
- Archive/unarchive through the edit form. No destructive delete action: historical dates remain available, including archived events. Archiving is an organizational state, not a signal to discard historical training context.
- Before/after changes, actor ID, timestamp and revision are retained. Old forms cannot overwrite newer revisions.
- Existing forecast caches become stale after calendar changes. This does not automatically regenerate a forecast or make the existing Gemini forecast calendar-aware.

The first version uses the existing `system_settings` key `academic_calendar`, with a versioned JSON document and transactional compare-and-swap saves. No schema migration is needed. Full history remains in that document; the UI shows the latest ten changes. For a much larger institution or many years of high-frequency edits, separate indexed calendar/audit tables would be a later scaling option.

Implementation: [calendar service](app/Services/AcademicCalendar.php), [controller](app/Http/Controllers/Admin/AcademicCalendarController.php), [page](resources/views/admin/settings/academic-calendar.blade.php), [routes](routes/web.php), [forecast freshness](app/Services/AiForecastCache.php).

## Synthetic dataset

The [generator](app/Services/SyntheticForecastDataset.php) produces **weekly request counts**, not copies, unique requestors, or actual transaction rows. Six assumed semesters across 2023-2025 include three document types, calendar event flags, weekday office closures, normal variation, assumed enrollment/clearance/graduation demand and an unexpected final-test surge. Exam flags deliberately do not imply an automatic demand increase.

These dates and effects are assumptions, not facts supplied by the school. No student identity or real proof file is used. Leading partial weeks before a semester's first Monday are omitted; the final week is clipped to semester-end. A boundary week cannot appear in both training and evaluation splits.

CSV columns:

`source, week_start, academic_year, semester, week_of_semester, document_type, working_days, enrollment, exams, clearance, graduation, requests, split`

Chronological split: four semesters train, fifth validation, sixth final test. Lag/rolling features must be built later using only data available at the forecast date. For multi-week predictions, never feed actual held-out future counts into lag features. Treat unknown future request counts as predictions, or use direct horizon-specific models. Do not tune using the final test period.

The [command](app/Console/Commands/GenerateForecastDemo.php), `forecast:demo-dataset --seed=2026`, requires isolated local-UAT storage or the testing environment. It writes private files under `storage_path('app/private/forecast-demo/seed-2026')` and refuses to overwrite an existing dataset. The same seed reproduces the same rows. It does not insert requests or modify the calendar/database. In this session the command was invoked through the existing private UAT bootstrap, rather than the working application's default Artisan environment.

Generated fixture in the existing local UAT environment: **390 rows**, `weekly-requests.csv`, `calendar.json`, `manifest.json`. Its actual local directory is [seed-2026](storage/app/private/local-uat/app/private/forecast-demo/seed-2026/). Keep this private and exclude it from deployment packages. No model file or accuracy score has been generated.

The assumed calendar was separately imported into the isolated UAT calendar through the calendar service, recording change history. Six historical demo semesters and thirty events were added; browser checks added one current demo semester and one enrollment event. The working database was not populated. Editing the UAT calendar does not rewrite the frozen synthetic dataset; any later model must record which calendar/dataset snapshot it used.

## Verification

- Full PHP suite: **222 tests / 3,262 assertions passed**.
- Frontend suite: **7 tests passed**.
- All Blade templates compiled using isolated storage.
- New tests cover role restrictions, read-only registrar rendering, date bounds, overlapping events, duplicates, archive history, stale saves, cache invalidation, reproducible/chronological labeled synthetic rows, command isolation and overwrite rejection.
- Browser: administrator semester/event saves succeeded; desktop/mobile layout checked without page-wide overflow. Wide tables retain internal scrolling. Small-screen forms stack into one column.
- Working SQLite checksum matched its pre-UAT checksum. No live mail, live AI calls, schema changes, production deployment or working request mutations were performed.

## Next stage

1. Confirm actual school dates if available; otherwise retain explicit assumed-demo labels.
2. Build and evaluate a numerical forecasting baseline and candidate model. This generator does not train Gemini or a numerical model. The current forecasting implementation has not been replaced.
3. Evaluate chronological unseen periods and alternate synthetic scenarios, report error in request counts, and avoid claims of real-school predictive accuracy.
4. Add processing-time and staff-availability inputs, then calculate backlog plus forecast workload versus capacity. These inputs/calculations are not implemented by the calendar stage.
5. Add Reports & Analytics with consistent filtered CSV/PDF exports and transparent forecast assumptions, then conduct local UAT before deployment.

## Calendar panel presentation update

Academic calendar now opens with a real monthly date grid and a full monthly agenda. Previous/next/today controls and a native month picker browse saved dates without changing records. Multi-day events appear throughout their inclusive date range; office closures use a distinct tint. Admin event links open existing edit forms; registrar remains read-only. On phones the grid uses compact event markers and full descriptions stay in the agenda. Semester management, archive/history, save routes and revision protections remain intact.

Verification: nine calendar/data tests passed (2,006 assertions), including leap February, malformed month inputs, cross-month events and read-only rendering. Browser checked month navigation, event-to-edit linking and desktop/tablet/mobile overflow. This presentation-only follow-up did not rerun the entire backend suite; the preceding full run passed 222 tests. No database mutation was needed for the panel change.

## Calendar usability refinement

The default page now shows only calendar/month agenda and clear Add semester/Add event actions. Semester management and history are separate navigation views. Each add/edit action opens one focused form with Cancel/Back controls; validation errors reopen the relevant form. Removed technical revision/training text from the main user flow. Existing fields, date rules, role restrictions and audit storage remain intact. Verification: ten calendar tests / 2,018 assertions passed; browser add-event navigation/cancel and desktop/mobile overflow checked. No records changed during this presentation refinement.

## Academic year hierarchy and filtering

Added saved academic-year records above semesters/events within the same versioned settings document. Define the year once (default June 1-May 31), select it when adding semesters, then link events to those semesters. Academic years have edit/archive and audit history using the existing revision protection. Year edits cannot exclude saved semesters; semester edits cannot exclude events. Renaming a year updates its linked semester labels.

The calendar's main filter is now Academic year. Selecting 2026-2027 opens the current month when in range, otherwise June 2026. Previous/next are limited to June 2026-May 2027; an out-of-range month query is clamped to the year's start month. Historical years remain selectable. Selected year is retained across view/add/edit/cancel navigation, save redirects and month links. Semester/event lists are scoped to that year.

Existing free-text year labels are grouped on read into stable year IDs without rewriting stored semesters/events. Standard consecutive year labels receive June-May bounds, extended only if necessary to preserve an existing outlying semester date. Normalized links persist on the next authorized calendar save; no schema migration or background data rewrite runs on GET. Old synthetic CSV/calendar snapshots stay unchanged.

Verification: full suite 228 PHP tests / 3,307 assertions; seven frontend tests; all Blade templates compiled. Browser verified 2026-2027 bounds, year save, disabled June/May navigation and preselected semester year dropdown in isolated UAT. Working SQLite checksum remained unchanged. Numerical model training, staffing calculations and Reports & Analytics remain separate future stages.

### Historical calendar alignment — 2026-11-12
Local UAT academic years 2021–2022 through 2025–2026 now use AY 2026–2027 as their calendar reference. Both semester boundaries and the seven existing reference events were shifted by whole years, preserving month/day and office-closure flags. This produces 35 historical events across ten semesters; seven current events remain unchanged. Earlier generic historical events were replaced, with a SQLite backup and complete before/after snapshots retained in calendar history. Copied dates, including holidays, are synthetic assumptions rather than verified historical school schedules; weekdays may differ between years. Missing reference event types were not invented. Existing standalone forecast CSV datasets were not regenerated by this calendar change.

### Historical request batch — 2026-11-13
Generated 10,251 isolated local UAT requests from June 1, 2021 through November 12, 2026, using the 1,500 demo student accounts and calendar revision 108. Seed: 20261113. Enrollment and exams increase simulated demand; weekends and recorded office closures reduce submissions. Processing transitions skip weekends and recorded closures. Terminal older requests are archived. Request IDs use DEMO26-YYYYMMDD-NNN; repeat execution skips existing IDs. Current category snapshots and priority weights are applied historically and do not represent actual enrollment histories. Timeline actors are explicitly labeled Historical simulation. Processing durations are assumptions, not evidence for real staffing capacity. No proof files, mail, or notifications are generated. A SQLite backup precedes generation; the working database is unchanged. Model training has not been performed by this step.

### Offline local forecasting experiment — 2026-11-13
Read only the DEMO26 request batch, including archived records, and exclude incomplete edge weeks. Output: 283 complete weeks x 11 document types = 3,113 rows; counts reconcile to 10,195 requests. First four training weeks are lag warm-up. Train: 2021-06-07–2024-06-02; validation: 2024-06-03–2025-06-01; untouched test: 2025-06-02–2026-05-31; current holdout: 2026-06-01–2026-11-08, unused. Features are calendar working/closure/event days, annual seasonality, document identity, lag 1/4 and rolling four-week demand. Numeric ridge regression standardizes on fitting data only; penalty selection uses validation only. Full-year recursive evaluation does not feed held-out actual demand back into lags. Compare with recursive four-week-average and seasonal-52 baselines. Test per-document/week MAE 1.404 and WAPE 43.54%; aggregate weekly WAPE 12.43%. Retrospective calendar snapshot and stationary synthetic generation limit validity; current calendars were not recorded as known-at-time historical snapshots. The offline model is not integrated into dashboard/API forecasting. It does not train staff capacity or use OpenAI/Gemini. Private artifacts include dataset, predictions, evaluation, frozen model and calendar snapshot.

### Graduation and post-graduation simulation — 2026-11-13
User approved provisional May 4–7 ceremonies in the ending year of every academic year (2022–2027). Added six Graduation events. New Alumni cohorts for graduation years 2022–2026 contributed 223 additional TOR requests in the following 1–8 weeks, with deterministic early/tapered timing. Thirty generated 2026 Graduating accounts were transitioned to Alumni with graduation year 2026. Existing DEMO26 request-category snapshots for those matched cohorts now distinguish Regular/Graduating/Alumni by simulated graduation timing; alumni-only documents before graduation are replaced with eligible TOR records and scores recalculated. This remains an assumed cohort history, not actual enrollment data. No future 2027 requests were created. New batch IDs use DEMOGRAD and are idempotent; backups retained. Offline dataset now includes both prefixes and 10,418 complete-week requests, with post-graduation days/intensity features. New experiment run-20261113-112109-89f7 retains the original splits and keeps the current holdout unused. Test document/week MAE 1.476, WAPE 44.67%; four-week baseline MAE 2.008 and seasonal-52 MAE 1.932. Results from different target datasets should not be interpreted as a direct model improvement comparison. No dashboard integration or real-world accuracy claim.
