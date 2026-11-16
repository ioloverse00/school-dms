param(
  [Parameter(Mandatory = $true)][string]$ProjectRoot,
  [Parameter(Mandatory = $true)][string]$DestinationPath,
  [switch]$NoVendor,
  [switch]$NoBuiltAssets,
  [switch]$KeepStaging
)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path -LiteralPath $ProjectRoot).Path.TrimEnd('\', '/')
$archivePath = [System.IO.Path]::GetFullPath($DestinationPath)
if (Test-Path -LiteralPath $archivePath) { throw 'Destination already exists. Choose a new release filename.' }
$archiveDirectory = Split-Path -Parent $archivePath
New-Item -ItemType Directory -Path $archiveDirectory -Force | Out-Null

function Test-ReleasePath([string]$RelativePath) {
  $relative = $RelativePath.Replace('\', '/')
  if ($relative -match '(^|/)(\.git|\.github|node_modules|tests)(/|$)') { return $false }
  if ($relative -match '(^|/)\.env($|\.)' -or $relative -match '\.(log|sqlite|sqlite3|db|key)(-|$)') { return $false }
  if ($relative -match '^public/(hot|storage)(/|$)') { return $false }
  if ($relative -match '^bootstrap/cache/') { return $relative -eq 'bootstrap/cache/.gitignore' }
  if ($relative -match '^storage/') { return $relative -match '^storage/(app(/private|/public)?|framework(/cache(/data)?|/sessions|/views|/testing)?|logs)/\.gitignore$' }
  if ($relative -match '^database/') { return $relative -match '^database/(migrations|seeders|factories)/' -or $relative -eq 'database/.gitignore' }
  if ($relative -match '^public/build/') { return -not $NoBuiltAssets }
  if ($relative -match '^public/') { return $relative -match '^public/(assets/|index\.php$|\.htaccess$|favicon\.ico$|robots\.txt$)' }
  if ($relative -match '^vendor/') { return -not $NoVendor }
  if ($relative -match '^(app|config|resources|routes|scripts)/') { return $true }
  if ($relative -match '^bootstrap/') { return $relative -match '^bootstrap/(app|providers)\.php$' }
  return $relative -in @('artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js', 'postcss.config.js', 'tailwind.config.js', 'README.md', 'DEPLOY_HOSTINGER.md', 'DEPLOY_INDEVFINITE.md', 'PRIVATE_FILES_DEPLOYMENT.md', 'REQUEST_RELIABILITY_DEPLOYMENT.md', 'ACCOUNT_PROVISIONING.md', 'FRONTEND_DESIGN.md')
}

# Walk explicitly so directory symlinks/junctions cannot pull outside data into a release.
$files = New-Object 'System.Collections.Generic.List[object]'
$pending = New-Object 'System.Collections.Generic.Stack[string]'
$pending.Push($repoRoot)
while ($pending.Count -gt 0) {
  $directory = $pending.Pop()
  foreach ($item in Get-ChildItem -LiteralPath $directory -Force) {
    $relative = $item.FullName.Substring($repoRoot.Length + 1).Replace('\', '/')
    if (($item.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0) { continue }
    if ($item.PSIsContainer) {
      if ($relative -match '(^|/)(\.git|\.github|node_modules|tests)(/|$)' -or $relative -match '^(dist|windmill-dashboard|\.agents)(/|$)' -or $relative -match '^public/storage(/|$)') { continue }
      if ($NoVendor -and $relative -match '^vendor(/|$)') { continue }
      if ($NoBuiltAssets -and $relative -match '^public/build(/|$)') { continue }
      # Only enter release roots, never arbitrary folders or destination staging trees.
      if (($relative -split '/')[0] -notin @('app','bootstrap','config','database','public','resources','routes','storage','vendor','scripts')) { continue }
      if ($relative -match '^storage/' -and $relative -notmatch '^storage/(app(/private|/public)?|framework(/cache(/data)?|/sessions|/views|/testing)?|logs)$') { continue }
      if ($relative -match '^database/' -and $relative -notmatch '^database/(migrations|seeders|factories)(/|$)') { continue }
      $pending.Push($item.FullName)
    } elseif (Test-ReleasePath $relative) {
      $files.Add([pscustomobject]@{ FullPath = $item.FullName; Relative = $relative })
    }
  }
}

if (-not $NoVendor -and -not (Test-Path -LiteralPath (Join-Path $repoRoot 'vendor/autoload.php'))) { Write-Warning 'Vendor dependencies absent; install production dependencies on the server.' }
if (-not $NoBuiltAssets -and -not (Test-Path -LiteralPath (Join-Path $repoRoot 'public/build/manifest.json'))) { Write-Warning 'Built assets absent; build and upload assets before deployment.' }

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archiveStream = [System.IO.File]::Open($archivePath, [System.IO.FileMode]::CreateNew, [System.IO.FileAccess]::ReadWrite, [System.IO.FileShare]::None)
$archive = New-Object System.IO.Compression.ZipArchive($archiveStream, [System.IO.Compression.ZipArchiveMode]::Create, $false)
try {
  foreach ($file in $files) {
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $file.FullPath, $file.Relative, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
  }
  # Required writable directories are explicit, even when the source has no placeholder files.
  foreach ($directory in @('bootstrap/cache/','storage/app/private/','storage/app/public/','storage/framework/cache/data/','storage/framework/sessions/','storage/framework/views/','storage/logs/')) {
    $archive.CreateEntry($directory) | Out-Null
  }
} finally {
  $archive.Dispose()
  $archiveStream.Dispose()
}

# Validate what was written, including dotfiles that Compress-Archive can omit on Windows.
$check = [System.IO.Compression.ZipFile]::OpenRead($archivePath)
try {
  foreach ($entry in $check.Entries) {
    if (-not $entry.FullName.EndsWith('/') -and -not (Test-ReleasePath $entry.FullName)) { throw 'Release contains a forbidden path.' }
  }
  if (Test-Path -LiteralPath (Join-Path $repoRoot 'public/.htaccess')) {
    if ($null -eq $check.GetEntry('public/.htaccess')) { throw 'Release is missing Apache rules.' }
  }
} finally { $check.Dispose() }

if ($KeepStaging) {
  $stagingPath = Join-Path $archiveDirectory ('.deploy-staging-' + [guid]::NewGuid().ToString('N'))
  [System.IO.Compression.ZipFile]::ExtractToDirectory($archivePath, $stagingPath)
  Write-Host "Kept staging folder: $stagingPath"
}
Write-Host "Created verified deploy zip: $archivePath"
