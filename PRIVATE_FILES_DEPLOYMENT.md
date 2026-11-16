# Private request files and safe deployment archives

Payment proofs now use the `documents` disk rooted at `storage/app/private`. They have no public URL or temporary-serving route. Existing database `file_path` values stay unchanged. The authenticated `/files/{requiredFile}` route permits only the owner or admin/registrar and returns private/no-store responses. There is deliberately no public-disk fallback when a private copy is missing.

## Upgrade an existing installation

1. Back up the database and upload files together outside the web root. Preserve the application key and existing environment configuration. Record a tested recovery location. Do not put backups in a deployment ZIP.
2. Enter a maintenance window and stop old application instances/workers from writing uploads. Upload the new code including configuration, routes and the migration command. Rebuild any existing Laravel configuration/route caches using the normal deployment procedure; stale caches can omit the new disk or retain the old download handler.
3. Block direct web access to `/storage/request_files/` while migrating. The application's Apache rules include this denial. For Nginx, configure an equivalent rule before any generic static-file/alias rule, for example:

   ```nginx
   location ^~ /storage/request_files/ { return 403; }
   ```

   If the deployment copies `public/` to another web root, preserve this restriction there too. Apache rules only apply where mod_rewrite and the hosting configuration honor them; verify the actual web server. New private files must never be copied into the web root.
4. From the application directory, preview:

   ```bash
   php artisan files:privatize-requests
   ```

   This checks both the configured public disk and physical `public/storage` copies, compares duplicate/destination SHA-256 hashes and prints counts without filenames. It includes unreferenced files under `request_files`, so old public proofs do not remain accessible just because their database metadata was deleted. No files or database records are changed by the preview itself; filesystem adapters may ensure their root directories exist.
5. If the preview succeeds and backups are verified, apply:

   ```bash
   php artisan files:privatize-requests --apply
   ```

   The command stages and verifies copies, preserves existing matching private files, verifies the complete batch before cleanup and removes public copies only after matching their private SHA-256. It never overwrites conflicting content. On conflict/storage failure it exits unsuccessfully; inspect securely, correct the cause and rerun. Some verified private copies or completed source cleanup may remain after a later failure. Reruns preserve completed copies and finish cleanup. Do not delete a conflict to make the command pass without reviewing both versions and backups.
6. Verify an owner and staff member can open an existing proof through `/files/...`; verify a different student and guest cannot. Test a previously public direct URL and require 403/404 from the actual server. Repeat with a new submission. Ensure no old release/worker is still writing to public storage, then end maintenance.

The migration does not recover proofs already missing from disk and does not alter database paths. Keep backups under the institution's retention/access policy. Do not roll back to the old public-disk download code after removing public copies; restore code/data together under a reviewed recovery plan. A copied deployment web root outside `public/storage` needs a separately inventoried migration; the command cannot guess hosting-specific paths.

## Build a clean release

Both hosting wrappers use [the shared archive builder](scripts/deploy/New-DeploymentArchive.ps1). They allow application sources, migrations/seeders/factories, approved static/build assets, dependency files, deployment scripts and empty writable runtime directories. They exclude environment files, local databases/journals, upload files, public storage copies, logs, sessions, compiled views, bootstrap caches and public/hot. Dotfiles such as the Apache rules are preserved. Directory junctions/symlinks are skipped to avoid pulling external data into a package.

Use a new destination filename. Existing archives are refused rather than overwritten:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/hostinger/make-deploy-zip.ps1 -DestinationPath dist/hostinger-private-files-release.zip
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/indevfinite/make-deploy-zip.ps1 -DestinationPath dist/indevfinite-private-files-release.zip
```

`-NoVendor` and `-NoBuiltAssets` remain available when the target installs/builds those independently. `-KeepStaging` retains an extracted copy of the clean package for inspection. Relative destination paths resolve from the caller's working directory; source paths default to the repository containing the scripts. Use a clean checkout and production dependencies for real releases; a path allowlist is not a content/secret scanner, and the builder does not remove hardcoded seed credentials or other unrelated audited risks.

Do not deploy the old archives already present in `dist/`: they predate the fix and may contain logs/proofs. They are not automatically deleted or rewritten. Store/review them as sensitive artifacts under a separate approved cleanup procedure.

Run the package regression test without real uploads or databases:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/test-deployment-packages.ps1
```

It builds disposable fixtures for both hosts, verifies runtime-data exclusions/dotfiles/flags and proves an existing destination is unchanged. Fixtures contain dummy bytes only and stay in the printed temporary folder. The PHP private-file tests use fake disks and cover upload location, owner/staff/guest/foreign access, preview, duplicate sources, conflicts and repeat migration.
