param(
  [string]$DestinationPath = "$(Resolve-Path .)\\dist\\hostinger-deploy.zip",
  [switch]$NoVendor,
  [switch]$NoBuiltAssets,
  [switch]$KeepStaging
)

$ErrorActionPreference = "Stop"

function To-RelativePath([string]$FullPath, [string]$BasePath) {
  $normalizedBase = (Resolve-Path $BasePath).Path.TrimEnd('\') + '\'
  $normalizedFull = (Resolve-Path $FullPath).Path

  if (-not $normalizedFull.StartsWith($normalizedBase, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Path '$normalizedFull' is not under base '$normalizedBase'"
  }

  return $normalizedFull.Substring($normalizedBase.Length)
}

function Copy-FileSharedReadWrite([string]$SourcePath, [string]$DestinationPath) {
  $sourceStream = [System.IO.File]::Open($SourcePath, [System.IO.FileMode]::Open, [System.IO.FileAccess]::Read, [System.IO.FileShare]::ReadWrite)
  try {
    $destinationDir = Split-Path -Parent $DestinationPath
    if (-not (Test-Path $destinationDir)) {
      New-Item -ItemType Directory -Path $destinationDir | Out-Null
    }

    $destinationStream = [System.IO.File]::Open($DestinationPath, [System.IO.FileMode]::Create, [System.IO.FileAccess]::Write, [System.IO.FileShare]::None)
    try {
      $sourceStream.CopyTo($destinationStream)
    } finally {
      $destinationStream.Dispose()
    }
  } finally {
    $sourceStream.Dispose()
  }
}

$repoRoot = (Resolve-Path ".").Path

$includeVendor = (-not $NoVendor) -and (Test-Path (Join-Path $repoRoot "vendor"))
$includeBuiltAssets = (-not $NoBuiltAssets) -and (Test-Path (Join-Path $repoRoot "public\\build"))

$includeRoots = @(
  "app",
  "bootstrap",
  "config",
  "database",
  "public",
  "resources",
  "routes",
  "storage",
  "artisan",
  "composer.json",
  "composer.lock",
  "vite.config.js",
  "package.json",
  "package-lock.json",
  "postcss.config.js",
  "tailwind.config.js",
  "DEPLOY_HOSTINGER.md",
  "README.md"
)

if ($includeVendor) {
  $includeRoots += "vendor"
} elseif (-not $NoVendor) {
  Write-Warning "vendor not found; Hostinger shared hosting usually needs vendor uploaded unless you run Composer on the server."
}

if ($NoBuiltAssets) {
  Write-Warning "Skipping public/build because -NoBuiltAssets was used."
} elseif (-not $includeBuiltAssets) {
  Write-Warning "public/build not found; assets may not load in production unless you build and upload them."
}

$excludedPathFragments = @(
  "\.git\",
  "\.github\",
  "\node_modules\",
  "\tests\"
)

$excludedExactRelativePaths = @(
  ".env",
  "public\\hot"
)

Push-Location $repoRoot
try {
  $files = New-Object System.Collections.Generic.List[string]

  foreach ($root in $includeRoots) {
    if (-not (Test-Path $root)) { continue }

    $items = Get-ChildItem -LiteralPath $root -Recurse -Force -File -ErrorAction SilentlyContinue
    foreach ($item in $items) {
      $relative = To-RelativePath $item.FullName $repoRoot

      if ($excludedExactRelativePaths -contains $relative) { continue }
      if ($relative -like ".env.*") { continue }
      if ($NoBuiltAssets -and $relative -like "public\\build\\*") { continue }

      $isExcludedFragment = $false
      foreach ($fragment in $excludedPathFragments) {
        if ($relative -like "*$fragment*") { $isExcludedFragment = $true; break }
      }
      if ($isExcludedFragment) { continue }

      # Never bundle user uploads / runtime cache; keep only placeholder files (like .gitignore).
      if ($relative -like "storage\\app\\*" -and ($relative -notlike "storage\\app\\**\\.gitignore")) { continue }
      if ($relative -like "storage\\framework\\*" -and ($relative -notlike "storage\\framework\\**\\.gitignore")) { continue }
      if ($relative -like "storage\\logs\\*" -and ($relative -notlike "storage\\logs\\**\\.gitignore")) { continue }

      $files.Add($relative) | Out-Null
    }
  }

  $destinationDir = Split-Path -Parent $DestinationPath
  if (-not (Test-Path $destinationDir)) {
    New-Item -ItemType Directory -Path $destinationDir | Out-Null
  }

  if (Test-Path $DestinationPath) {
    Remove-Item -LiteralPath $DestinationPath -Force
  }

  $stagingRoot = Join-Path $destinationDir (".hostinger-staging-" + [System.Guid]::NewGuid().ToString("N"))
  New-Item -ItemType Directory -Path $stagingRoot | Out-Null

  try {
    foreach ($relative in $files) {
      $sourcePath = Join-Path $repoRoot $relative
      $destinationFilePath = Join-Path $stagingRoot $relative
      Copy-FileSharedReadWrite -SourcePath $sourcePath -DestinationPath $destinationFilePath
    }

    Compress-Archive -Path (Join-Path $stagingRoot "*") -DestinationPath $DestinationPath -CompressionLevel Optimal
    Write-Host "Created deploy zip: $DestinationPath"
  } finally {
    if (-not $KeepStaging -and (Test-Path $stagingRoot)) {
      Remove-Item -LiteralPath $stagingRoot -Recurse -Force
    } elseif ($KeepStaging) {
      Write-Host "Kept staging folder: $stagingRoot"
    }
  }
} finally {
  Pop-Location
}
