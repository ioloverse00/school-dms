param(
  [string]$DestinationPath = (Join-Path $PSScriptRoot '../../dist/hostinger-deploy.zip'),
  [switch]$NoVendor,
  [switch]$NoBuiltAssets,
  [switch]$KeepStaging,
  [string]$ProjectRoot = (Join-Path $PSScriptRoot '../..')
)

& (Join-Path $PSScriptRoot '../deploy/New-DeploymentArchive.ps1') -ProjectRoot $ProjectRoot -DestinationPath $DestinationPath -NoVendor:$NoVendor -NoBuiltAssets:$NoBuiltAssets -KeepStaging:$KeepStaging