param([string]$ScratchRoot = (Join-Path ([System.IO.Path]::GetTempPath()) ('dms-package-tests-' + [guid]::NewGuid().ToString('N'))))

$ErrorActionPreference = 'Stop'
$fixture = Join-Path $ScratchRoot 'fixture'
$contents = @{
  'app/Example.php' = '<?php // application';
  'bootstrap/app.php' = '<?php // bootstrap';
  'bootstrap/cache/.gitignore' = '*';
  'bootstrap/cache/config.php' = 'SENSITIVE CONFIG';
  'public/.htaccess' = 'RewriteEngine On';
  'public/index.php' = '<?php';
  'public/assets/example.css' = 'body {}';
  'public/build/manifest.json' = '{}';
  'public/hot' = 'http://localhost:5173';
  'public/storage/request_files/proof.pdf' = 'PUBLIC PROOF';
  'database/migrations/example.php' = '<?php';
  'database/database.sqlite' = 'PRIVATE DATABASE';
  'database/database.sqlite-wal' = 'PRIVATE DATABASE JOURNAL';
  'storage/app/private/request_files/proof.pdf' = 'PRIVATE PROOF';
  'storage/app/public/request_files/proof.pdf' = 'LEGACY PROOF';
  'storage/framework/sessions/session' = 'PRIVATE SESSION';
  'storage/framework/views/compiled.php' = 'PRIVATE VIEW';
  'storage/logs/laravel.log' = 'PRIVATE LOG';
  'storage/app/private/.gitignore' = '*';
  '.env' = 'SECRET';
  '.env.production' = 'SECRET';
  'vendor/autoload.php' = '<?php';
  'vendor/example/tests/secret.txt' = 'VENDOR TEST';
  'resources/views/example.blade.php' = 'view';
  'scripts/deploy/example.ps1' = '# script';
  'artisan' = '<?php';
  'composer.json' = '{}'
}
foreach ($relative in $contents.Keys) {
  $target = Join-Path $fixture $relative
  if (Test-Path -LiteralPath $target) { throw 'Fixture path already exists; refusing to overwrite.' }
  New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force | Out-Null
  [System.IO.File]::WriteAllText($target, $contents[$relative])
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
foreach ($hostName in @('hostinger','indevfinite')) {
  foreach ($omitDependencies in @($false, $true)) {
    $zipPath = Join-Path $ScratchRoot ($hostName + '-' + $omitDependencies + '.zip')
    & (Join-Path $PSScriptRoot "$hostName/make-deploy-zip.ps1") -ProjectRoot $fixture -DestinationPath $zipPath -NoVendor:$omitDependencies -NoBuiltAssets:$omitDependencies
    $zip = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
    try {
      $names = @($zip.Entries.FullName)
      foreach ($required in @('app/Example.php','public/.htaccess','public/index.php','database/migrations/example.php','storage/app/private/','storage/framework/views/','storage/app/private/.gitignore','scripts/deploy/example.ps1','artisan')) {
        if ($names -notcontains $required) { throw "Missing expected entry: $required" }
      }
      foreach ($forbidden in @('bootstrap/cache/config.php','public/hot','public/storage/request_files/proof.pdf','database/database.sqlite','database/database.sqlite-wal','storage/app/private/request_files/proof.pdf','storage/app/public/request_files/proof.pdf','storage/framework/sessions/session','storage/framework/views/compiled.php','storage/logs/laravel.log','.env','.env.production','vendor/example/tests/secret.txt')) {
        if ($names -contains $forbidden) { throw "Forbidden release entry: $forbidden" }
      }
      if (($names -contains 'vendor/autoload.php') -eq $omitDependencies) { throw 'NoVendor flag was not respected.' }
      if (($names -contains 'public/build/manifest.json') -eq $omitDependencies) { throw 'NoBuiltAssets flag was not respected.' }
    } finally { $zip.Dispose() }

    $priorHash = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash
    $rejected = $false
    try {
      & (Join-Path $PSScriptRoot "$hostName/make-deploy-zip.ps1") -ProjectRoot $fixture -DestinationPath $zipPath
    } catch { $rejected = $true }
    if (-not $rejected -or (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash -ne $priorHash) { throw 'An existing release was overwritten.' }
  }
}
Write-Host 'PASS: both packagers exclude runtime data, retain dotfiles, respect flags and preserve existing archives.'
Write-Host "Test artifacts retained in: $ScratchRoot"
