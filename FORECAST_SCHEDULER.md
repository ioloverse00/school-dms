# Automatic forecast refresh

The Laravel schedule runs `ai:refresh-forecast` at **00:00 and 12:00 in the application timezone** (default Asia/Manila). Dashboard visits only read stored forecasts. Admins retain Refresh now; registrars have read access only. Scheduled refreshes use the same history (24 months), horizon (12 months), configured prompt, cache format, shared generation lock and one-minute cooldown as the dashboard.

Successful generation replaces the shared result only after validation. Failure retains the previous result and writes safe diagnostics with a reference. An older month's result remains readable and explicitly outdated. There are no automatic provider retry loops. The next scheduled timestamp is a planned time, not proof that the scheduler is running. A missed run while the machine is off waits until the next scheduled slot; an admin may refresh manually.

## Hosting setup (not yet installed)

Configure GEMINI_ENABLED=true and the existing secret through the hosting environment. Set APP_TIMEZONE=Asia/Manila. Use persistent shared cache with atomic lock support. Keep private storage and environment files outside public access.

Configure the host's cron to run Laravel's `schedule:run` every minute, or every five minutes if that is the host minimum. Both align with minute 00 at midnight and noon. Use the host's actual PHP CLI executable and project path, for example:

```cron
* * * * * cd /actual/project/path && /actual/php/path artisan schedule:run >> /actual/private/log/path/scheduler.log 2>&1
```

Replace all example paths with verified host values. Install cron once; duplicate schedulers are unnecessary. Existing email delivery scheduling remains intact. Verify CLI PHP configuration, API connectivity, logs, time zone and last-success timestamps on the host before acceptance. `php artisan schedule:list` lists the schedule without calling Gemini. `php artisan ai:refresh-forecast` performs one immediate refresh, potentially consuming API quota.

## Isolated local UAT

Keep the PHP server running in one terminal. In a second normal PowerShell terminal:

```powershell
cd D:\school-dms
'HTTP_PROXY','HTTPS_PROXY','ALL_PROXY' | ForEach-Object { Remove-Item "Env:$_" -ErrorAction SilentlyContinue }
C:\xampp\php\php.exe storage/app/private/local-uat/scheduler.php
```

The dedicated launcher preserves the isolated UAT database/storage/mail capture in scheduler subprocesses and opts into live Gemini only when the private live-preview marker is present. Other fixture/training scripts remain offline. Ctrl+C stops the worker. Do not use a plain `php artisan schedule:work` for UAT: that uses the normal project environment rather than the isolated launcher.

No hosting cron, Windows scheduled task or persistent local worker was installed by this code change. Automated tests use fake providers and an isolated database; they incur no provider calls.
