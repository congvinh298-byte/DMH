param(
    [string]$Source = "C:\Users\pcpv\OneDrive\Desktop\DTH",
    [string]$Storage = "D:\DTH",
    [switch]$Apply
)

$ErrorActionPreference = "Stop"

$SourcePath = (Resolve-Path -LiteralPath $Source).Path
if (-not (Test-Path -LiteralPath $Storage)) {
    New-Item -ItemType Directory -Path $Storage | Out-Null
}
$StoragePath = (Resolve-Path -LiteralPath $Storage).Path

if ($SourcePath -eq $StoragePath) {
    throw "Source and storage paths must be different."
}

$ExcludedDirs = @(
    ".git",
    ".vscode",
    ".sixth",
    ".expo",
    "node_modules",
    "vendor",
    "dist",
    "build",
    "uploads",
    "public\uploads",
    "storage\private",
    "storage\logs",
    "mobile-app\node_modules",
    "mobile-app\.expo"
)

$ExcludedFiles = @(
    ".env",
    ".env.*",
    "error_log",
    "bot_log.txt",
    "*.log"
)

$RobocopyArgs = @(
    $SourcePath,
    $StoragePath,
    "/E",
    "/XO",
    "/FFT",
    "/R:2",
    "/W:2",
    "/XD"
)
$RobocopyArgs += $ExcludedDirs
$RobocopyArgs += $ExcludedDirs | ForEach-Object { Join-Path $SourcePath $_ }
$RobocopyArgs += "/XF"
$RobocopyArgs += $ExcludedFiles

if (-not $Apply) {
    $RobocopyArgs += "/L"
    Write-Host "Dry run only. Add -Apply to copy changed files."
}

Write-Host "Source : $SourcePath"
Write-Host "Storage: $StoragePath"
robocopy @RobocopyArgs

$Code = $LASTEXITCODE
if ($Code -le 7) {
    exit 0
}
exit $Code
