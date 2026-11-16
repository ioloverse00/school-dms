# Request persistence and status-email delivery

## Release gates before UAT

Run these read-only commands against the deployed application after installing the release and applying its migrations:

```bash
php artisan deploy:check
php artisan schedule:list
# On the production target, additionally require production security settings:
php artisan deploy:check --production
```

The preflight returns a nonzero exit code on failed checks. It reads configuration, migration metadata, required tables and attachment paths; it never sends messages, consumes the outbox, applies migrations or creates missing storage directories. Exceptions are summarized without printing credentials or database connection strings. It checks that referenced proof files exist privately and no legacy files remain in either known public location. It does not verify every file's contents, the hosting document root, proxy/TLS configuration, database grants, SMTP credentials or cron execution. Custom deployment layouts still require checking their actual public directory.

Production additionally requires `APP_ENV=production`, `APP_DEBUG=false`, an HTTPS `APP_URL`, `SESSION_SECURE_COOKIE=true`, HttpOnly cookies, persistent session/cache configuration, a supported delivery mailer, a valid sender address, a bounded SMTP timeout, built assets and writable runtime directories. These are configuration checks, not connectivity or write probes. Configure the real delivery provider on the host, then rebuild configuration cache there. Never copy a locally cached configuration into a release. Log/array mailers and failover chains containing them fail the production check: the outbox could otherwise mark a message sent after it was only captured or logged. Do not process a real pending outbox using a fake/log/array mailer as a test.

Preserve the existing `APP_KEY` during every upgrade. Generate a key only on first installation when no key exists; keep it in the organization's secret store and recovery plan. Key rotation is a separate planned operation. Keep the database and private uploads outside the web root. Set the domain document root to the application's public directory or use the documented split layout. Test that configuration files and a dummy confidential attachment are denied through the actual web server; do not publish real proof URLs for testing.

### Scheduler and mail verification on staging

1. Use a separate staging database, private storage, session/cache namespace, credentials and an email capture destination. Do not point staging at production data or its pending outbox. If staging data is copied, sanitize it and preserve referential integrity before enabling any jobs.
2. `schedule:list` should show `notifications:deliver-email` every minute. This proves application registration only. Configure one hosting cron entry to invoke `schedule:run` every minute using the host's absolute PHP binary and application path. The production entry will send real queued mail once enabled; coordinate enabling it with the release owner.
3. On isolated staging, create a dummy status change and allow the scheduler to process it into the staging mail destination. Confirm the snapshot, recipient, `sent_at`, notification count and no resend on another run. Simulate transport failure only there, then confirm deferred retry and eventual recovery. Existing automated tests cover this without live SMTP.
4. Inspect actual hosting cron logs across several minutes. Check for wrong working directory/PHP binary, overlapping invocations and execution limits. A visible cron entry alone is not evidence it ran.
5. Keep a bounded private cron log and alert on repeated failures or aging unsent outbox events. Recheck after each deployment. Do not expose cron or delivery commands through public HTTP routes.

### Backup and restore procedure

Back up the database, the complete private `request_files` directory, and the application key/configuration secrets through separate protected mechanisms. Store encrypted recovery copies off-host with restricted access and an agreed retention policy. Copies retained on the same machine are useful for rollback but do not protect against host loss.

For a consistent release backup, stop incoming mutations during a short maintenance window and pause outbox/other background writers. Wait for existing workers to finish. Use a database-native snapshot: SQLite `VACUUM INTO` to a new private file locally, or the hosting database's supported consistent export/snapshot for MySQL. Never copy a live SQLite file without accounting for its journal/WAL. Capture private uploads in the same window and save a SHA-256 manifest. Record the release version, schema migration state and backup time. Resume writers only after successful verification.

Restore into a new isolated database and private directory first. Verify database integrity/foreign keys and table counts, compare upload hashes, and resolve every attachment reference. Boot the matching release against the restored copy with jobs disabled and mail captured; check authenticated access, role restrictions and a dummy private attachment. Retain the original installation. A real recovery cutover requires a separate maintenance window, tested connection/configuration changes and a rollback point; avoid two active environments processing the same restored outbox. Previously pending emails can be retried after recovery, so review the restore point and delivery history before resuming workers.

Agree recovery objectives with the school before launch: acceptable data loss determines backup frequency, and acceptable downtime determines how often to rehearse recovery. Assign an owner and record recovery time. Do not delete the last known-good recovery copy until the replacement is verified.

### Local verification evidence — 2026-11-11

- Read-only `deploy:check`: all seven local checks passed; all packaged migrations applied, referenced proofs available privately, no legacy public proofs found.
- `schedule:list`: status-email delivery is registered every minute with overlap protection. Host cron installation/execution remains unverified.
- Configuration, route and Blade-view cache builds passed using isolated private scratch paths; the normal runtime caches were not replaced. On Windows, use project-relative `APP_CONFIG_CACHE` / `APP_ROUTES_CACHE` overrides for an isolated check because this Laravel version prefixes drive-letter paths with the project directory. Build actual release caches on the target host.
- Regression suite: 147 PHP tests and 655 assertions passed; all four navigation tests and both deployment-packager fixture suites passed. The eight new preflight tests cover failure reporting, privacy, no mail delivery, and valid production configuration without contacting SMTP.
- Isolated recovery drill: a fresh SQLite snapshot was copied to a separate recovery database; all 21 application tables retained matching row counts, including 578 requests. Database integrity passed with zero foreign-key violations. All 13 upload files matched SHA-256 checksums in backup and restored copies, and all four attachment references resolved. Recovery artifacts and their manifest remain under private storage, excluded from release packages.
- The drill did not replace the application's database, boot a second live application, run jobs, send mail, prove off-host recovery or certify a MySQL restore. The current local database is SQLite; the actual hosting engine must be rehearsed separately.
- No staging/hosting access or live SMTP delivery was verified in this batch. UAT and production release remain separate gates.

## Request operations and history upgrade

### Concurrent staff edits

Staff status/remarks and archive/unarchive forms include a signed snapshot token. Each action locks and reloads the request inside its transaction and compares the token before any mutation, history, notification or outbox insert. A stale, missing, forged or other-request token is rejected. The latest history-event ID is part of the snapshot, so a change and revert within the same timestamp second still invalidates an older form. Locks/retries depend on the database engine; production-engine concurrency checks remain a release requirement.

After a conflict, the editor shows the latest saved values. The rejected status/remarks draft appears separately as read-only reference rather than being silently attached to a fresh token. Ordinary field-validation failures preserve their original token so later changes still conflict. An unchanged save with a current token remains a no-op; retrying an already-used old form may require reviewing/reloading the latest page. Open forms from before this upgrade have no token and must be reloaded. No new migration is needed beyond the existing history table. Tokens supplement existing role/CSRF authorization; they do not replace it. Application-key changes invalidate open tokens.

Regression coverage: [stale request edit tests](tests/Feature/StaleRequestEditTest.php). UAT is planned locally before deployment, after remaining matrix fixes.

Back up the database before applying [the additive history migration](database/migrations/2026_11_11_000002_create_document_request_events.php). Deploy the migration and application together during maintenance: the new submission, status and archive actions require the history table. It creates a separate table and does not rewrite existing requests or invent historical actions.

Submission, status/remarks changes, archive and unarchive actions record their actor, time and before/after values in the same transaction as the change. Identical retries do not add history. Staff can see actor names and previous remarks; students can see history only for their own requests, without staff identities. Detail pages paginate history at 20 entries. History is read-only through the application, but is not a tamper-proof compliance ledger: database administrators can change it, request deletion cascades to its history, and staff-name snapshots persist after actor deletion. Include it in backup, privacy and retention procedures. Direct SQL edits, seeders and demo commands do not create history events.

Staff cards count unarchived requests. Pending includes both requirements and verification. The dashboard queue includes only unarchived pending, processing and ready-for-release requests; completed and rejected requests remain accessible in the full list. Equal priority scores sort oldest first, then by database ID, consistently between the staff list and CSV. Demand charts retain archived historical records. Student cards retain their personal historical totals, including archived records, with an explanation on the dashboard.

The header search now submits a server query: student-management pages search students; other pages search accessible document requests. Search covers all pages, accepts up to 200 characters, preserves list filters and resets pagination. CSV exports use the same shared query and search as their staff list, across all matches. Request search covers ID, purpose, stored status and document name; staff can additionally search student name, ID and email. Student search covers name, ID, email and course. `%`, `_` and `!` are treated literally. Search is a bounded substring query without a new indexing service; revisit query plans if measured data volume warrants it. An export made while staff concurrently edit records is not a point-in-time database snapshot.

Automated regression coverage: [request operations tests](tests/Feature/RequestOperationsTest.php), [submission reliability tests](tests/Feature/RequestReliabilityTest.php), and the existing status-email tests. These checks do not replace UAT or verify hosting cron/SMTP configuration.

## Upgrade

Back up the database and private uploads before upgrading. Apply [the additive reliability migration](database/migrations/2026_11_11_000001_add_request_reliability.php) through the normal maintenance/staging procedure. It adds nullable submission identifiers to existing requests and a separate email-outbox table; it does not rewrite existing request IDs, statuses or attachment paths. Historical status emails are not invented/backfilled. Existing requests continue to work.

Old open submission pages do not have a submission token: users should reload the form after deployment. Tokens are unique in the database. Repeating an identical submission returns the existing request without another upload or staff notification. Reusing a token with different fields/file contents or another account produces a validation error; start a new form for a new logical request. A failed request preserves its token and fields but the browser must reattach the proof. Purpose is limited to 255 characters; copy count defaults to a configurable maximum of 100 through `DOCUMENT_REQUEST_MAX_COPIES`.

Uploads are stored privately before the database transaction. Profile changes, request/attachment records and staff database notifications commit together. Failures trigger removal of the preselected upload path. The filesystem is not a transactional database: a process crash or failed cleanup can leave a private orphan. Cleanup failures are logged without filenames; investigate securely rather than deleting private evidence indiscriminately. The new UUID/ULID identifiers do not replace ownership/role authorization.

## Operate email delivery

Status changes, in-app notifications and the intended email snapshot now commit in one database transaction. The HTTP request does not call SMTP and does not claim that an email was delivered. Repeating an unchanged status/remarks update creates no additional event. Both admin and registrar controllers use the same status action.

Configure the hosting scheduler to run every minute from the application directory:

```bash
php artisan schedule:run
```

Laravel schedules `notifications:deliver-email` every minute with an overlap lock. Use an absolute application/artisan path in your hosting control panel; only one scheduler configuration is needed. Alternatively, invoke the delivery command directly from cron if this host does not run the Laravel scheduler:

```bash
php artisan notifications:deliver-email --limit=50
```

The normal Laravel queue worker does **not** consume this outbox. A scheduler or explicit delivery invocation is required. Default SMTP timeout is 30 seconds, configurable with `MAIL_TIMEOUT`. A batch is bounded to 50 events by default; keep runtime below the ten-minute claim/overlap lease by reducing `--limit`, timeout or both where hosting execution limits require it. Multiple workers atomically claim events so ordinary concurrent runs do not send the same event. Claims older than ten minutes are recoverable after an interrupted process. Do not run a separate delivery process for longer than its lease on one message; unusually long custom mail transports require reviewing lease/timeouts.

Failures are persisted, logged with a delivery ID, and retried with exponential delay capped at 256 minutes. Later emails for the same request wait for earlier unsent events, so status messages do not overtake failed predecessors. Delivery uses the committed recipient/name/document/status/remarks snapshot, not the latest mutable request. In-app notifications do not depend on SMTP and are not recreated by email retries.

Monitor scheduler execution, pending/oldest outbox entries, attempts and failure logs; assign someone to investigate prolonged failures. The command reports sent/deferred counts and exits unsuccessfully when deliveries fail. A permanently bad address blocks later email for that request until an authorized maintainer resolves it. Do not mark an event sent merely to hide an alert; any correction/reissue should preserve its historical meaning and be reviewed.

SMTP delivery is at-least-once: if the provider accepts a message and the process loses the acknowledgment or fails before recording success, a retry can send it again. This implementation prevents repeat business changes/in-app notifications and routine duplicate outbox processing; it cannot guarantee exactly-once provider delivery. No live email should be sent from tests: use the array/fake mailer and an isolated database.

## Verify

1. Test a new submission and an identical retry: exactly one request, attachment and staff notification should exist.
2. Simulate upload/database failures in tests: no partial profile/request changes should remain; the attempted file should be removed.
3. Save a status while SMTP is unavailable: status and in-app notification remain saved; an unsent outbox event remains for retry.
4. Restore mail service, process the outbox and verify recipient/recorded status; another run must skip already sent events.
5. Confirm staff password/year and settings-array/range validation rejects invalid input before mutation.

The feature tests cover these paths with in-memory SQLite and fake storage/mail. Deployment still requires checking the actual database engine, SMTP credentials/timeouts, scheduler and hosting limits. This guide does not certify production operation or install a persistent scheduler.


## Program catalog preservation upgrade

Back up the database before migration. The historical `2026_05_09_000000_normalize_programs_and_user_courses` migration now preserves course values for fresh/pending installations. Editing that file does not rerun it on an existing database; the new `2026_11_12_000001_preserve_existing_program_catalog` forward migration handles those installations.

The forward migration merges existing catalog entries, configured defaults and nonempty account course values using exact deduplication. It never rewrites user courses or guesses aliases. Invalid catalog structures stop migration for manual review rather than replacing the data. Its rollback intentionally keeps preserved catalog data. Values cleared by an earlier migration require a trusted historical backup and reviewed recovery; this migration cannot reconstruct them.

Locally, the forward migration completed after a verified private SQLite backup, and all user rows remained unchanged. Repeat backup and engine-specific verification on the eventual host. This is not authorization to deploy before local UAT.


## Forecasting operations

Dashboard load only reads saved forecasts. Admins and registrars explicitly select **Generate forecast** to send POST to their role-specific endpoint. GET never invokes Gemini or updates the cache; 202 means no compatible saved forecast, not a queued job. Matching expired forecasts display as outdated. Cache parameters include a contract version and current month; older/incompatible results are ignored without deleting them.

Use a persistent shared cache store supporting atomic locks (the existing database store or a correctly configured shared file store). Array/null cache cannot enforce cross-request locks/cooldowns. No queue worker is added: generation is a single synchronous provider call capped at 25 seconds, with a five-second connection timeout, per-user three-per-minute POST throttle, shared 180-second lock and shared 60-second cooldown. The configured timeout can shorten the call; larger values are capped. Legacy `GEMINI_RETRIES` and `GEMINI_FALLBACK_MODELS` are no longer used. Manual retry after cooldown avoids multiplied provider calls. The CLI command also uses one bounded provider call and validated output, but web throttles/locks do not govern explicit CLI invocations; do not schedule overlapping CLI runs.

Keep PHP/proxy execution time above the provider timeout plus database/rendering overhead; verify actual hosting limits and shared locking before release. If those limits are unsuitable or usage grows, move generation into a queued job with job-state polling, keeping the same validation and cache contract. This additional infrastructure is deferred for current capstone use.

Invalid output/provider failures preserve the saved cache. HTTP and CLI failures expose a reference, while logs record that reference, exception type, allowlisted reason/stage and provider HTTP status when applicable, without response bodies or credentials. API keys are sent in the request header. Check configuration, provider availability and the referenced log entry during investigation; do not expose raw provider errors in the dashboard. Validate one controlled real response only when explicitly authorized: automated tests use fake HTTP, and no live AI request was made during this batch.

Before acceptance, verify both staff dashboard Generate flows, student denial, expired/no-cache messaging and provider failure recovery locally. Hosting-engine weekday queries and concurrent cache locks still need engine-specific verification; SQLite regression tests verify month boundaries and the MySQL weekday conversion mapping, not a running MySQL server.

Forecast cache history now contains the exact overall/per-document snapshot supplied to the provider; it is not requeried after generation. A catalog change during the provider call therefore does not make the saved result fail its own history validation.
