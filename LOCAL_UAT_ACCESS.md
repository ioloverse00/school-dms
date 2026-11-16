# Isolated local UAT access

Prepared: 2026-11-12. Setup verified; browser acceptance scenarios **not run**.

- Login: <http://127.0.0.1:8876/login>
- [Synthetic credentials](D:/school-dms/storage/app/private/local-uat/ACCESS.txt)
- [Local captured mail](D:/school-dms/storage/app/private/local-uat/logs/mail-capture.log)
- [Synthetic upload PDF](D:/school-dms/storage/app/private/local-uat/synthetic-upload.pdf)
- [Scenario checklist](LOCAL_UAT_CHECKLIST.md)

Accounts: `admin@uat.test`, `registrar@uat.test`, `regular@uat.test`, `graduating@uat.test`, `alumni@uat.test`, `other-student@uat.test`, `unused-student@uat.test`, `history-registrar@uat.test`, `unused-registrar@uat.test`. Their random shared fixture password is in the local credentials file above. These are synthetic local accounts, not real school credentials. The sole admin fixture has no recorded actions initially; run last-admin protection before introducing additional admins.

The application source is served directly, with a local-only bootstrap/router enforcing a separate SQLite database, storage, file sessions, file cache, session cookie, compiled views and logs. Every dynamic request checks database/storage isolation. Login HTML displays a local-only banner and dynamic responses carry `X-School-DMS-Environment: isolated-local-uat`. Source code edits will affect this running preview; the data stays isolated.

The working database checksum was verified unchanged after fixture creation and isolated preflight. No working accounts, attachments, application key or environment file were changed. The fixture contains six requests across all statuses, one archived completed request, six synthetic private proofs, an unused/custom program, a custom category display label, and staff-history references. The synthetic upload file is available for new-submission scenarios.

## Runtime and limitations

The server binds only to `127.0.0.1:8876`. It is a development server, not a deployment. The server is launched as a hidden background PHP process; it can still stop when the computer restarts or the process exits. Fixtures now persist under private local storage, outside the public document root. Temporary-folder cleanup no longer removes them. Do not deploy or publicly serve this local fixture directory. Use the restart command below if the listener stops.

To restart the same fixture from PowerShell:

```powershell
php -S 127.0.0.1:8876 -t D:/school-dms/public D:/school-dms/storage/app/private/local-uat/router.php
```

Do not use a plain `artisan serve` command as a substitute: it will not apply this temporary isolation bootstrap. If the port is already occupied, inspect the existing process instead of launching a duplicate.

Mail uses the log transport and was verified with a synthetic capture message. There is no SMTP server or inbox UI; invitations/reset links must be read from the local capture file, and that file can contain test reset tokens. This does not verify SMTP outage/recovery, so U19 remains blocked until a controlled local SMTP capture/failure setup is prepared. Do not invoke the ordinary project delivery command against the working database.

Live Gemini is disabled and its key is removed from effective runtime configuration. U17/U18 AI-success/failure browser steps remain blocked until an isolated controlled provider is configured; existing automated fake-HTTP tests are separate evidence. No external emails or AI requests were made by setup.

Setup verification: login page rendered with isolation banner; isolated `deploy:check` passed seven checks; source database checksum unchanged. No role login, form submission, acceptance scenario or project-owner sign-off has been recorded yet. Next: execute the available checklist scenarios and record actual evidence/defects; configure the blocked external-service simulations separately.

## Connection recovery

The previous temporary runtime disappeared and its listener stopped, causing connection refusal. Rebuilt synthetic fixtures in private persistent local storage and restarted the loopback listener. The rebuilt accounts use a new random fixture password: read the current ACCESS.txt above rather than reusing the old one. Verified HTTP 200 and repeated isolated preflight (7/7); working database checksum unchanged. No previous UAT acceptance results existed to recover.

## Live Gemini preview opt-in (2026-11-13)
User enabled live Gemini for the isolated web preview. The private `enable-live-gemini` marker enables Gemini only under the PHP built-in web server; the UAT bootstrap reads the existing configured key without printing it. CLI fixture/training scripts remain offline. The banner now says live Gemini enabled. Local mail capture and isolated SQLite/storage remain enforced. GET only reads cached forecasts; use the staff Generate forecast button for a provider request. One minimal provider connectivity check succeeded outside the restricted tool network sandbox; a full application forecast response has not yet been verified. The existing model is gemini-2.5-flash. To disable web preview, remove the private opt-in marker; do not edit or expose the API key. This opt-in supersedes earlier blanket statements that the UAT web runtime always disables live AI.

### Live forecast connection follow-up
The PHP web server inherited sandbox proxy variables, producing cURL error 7. Restarting with only HTTP_PROXY/HTTPS_PROXY/ALL_PROXY removed from its process environment restored provider connectivity. Do not start a second server on the same port: verify port ownership rather than trusting a stale PID file. A clean Windows environment launch failed to bind and was not retained. The active server keeps the regular environment except those proxy variables. Full generation subsequently reached the provider but exceeded the bounded timeout; direct JSON mode and zero thinking budget for the exact gemini-2.5-flash model reduced that overhead. The provider then returned inconsistent aggregate arithmetic. The application now derives overall monthly and period totals from bounded per-document integer forecasts before final strict validation; malformed counts, unknown IDs, dates and incomplete schema still fail. No provider retries, timeout extensions or validation bypasses were introduced. Full browser POST returned 200, cached GET loaded suggestion cards, and 17 forecast safety tests passed. Displayed workload/staffing remains based on existing assumptions, not a validated staffing model.
