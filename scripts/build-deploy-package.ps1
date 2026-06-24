<#
.SYNOPSIS
    Dong goi code DTH (dienmayhieu.com) thanh file zip de upload qua cPanel File Manager.

.DESCRIPTION
    Script nay loai bo .git, .github, file rac, file backup, file dev
    va nen thanh file zip de upload len hosting VinaHost qua File Manager.

.NOTES
    File: scripts/build-deploy-package.ps1
    Updated: 2026-06-24
#>

[CmdletBinding()]
param(
    [string]$OutputDir = "C:\Users\pcpv\Documents\tmp",
    [string]$ProjectPath = "C:\Users\pcpv\OneDrive\Desktop\DTH"
)

$ErrorActionPreference = "Stop"
$root = Resolve-Path $ProjectPath
$dateStr = Get-Date -Format 'yyyyMMdd-HHmm'
$zipName = "dienmayhieu-deploy-$dateStr.zip"
$zipPath = Join-Path $OutputDir $zipName
$staging = Join-Path $env:TEMP "dth-staging-$dateStr"

Write-Host "`n=== BUILD DEPLOY PACKAGE ===" -ForegroundColor Cyan
Write-Host "Source: $root"
Write-Host "Output: $zipPath"

# BUOC 1: Staging
New-Item -ItemType Directory -Path $staging -Force | Out-Null

# Copy filter
$excludePatterns = @(
    "\.git$", "\.git/", "\.git\\",
    "\.github", "\.vscode",
    "node_modules", "vendor", "dist", "build",
    "storage\\logs", "storage\\cache",
    "scripts\\deploy-", "scripts\\seed-",
    "docs\\", "\\docs/",
    "deployment_backups",
    "AutoSign",
    "test\.php$", "test/",
    "fix_.*\.php$", "alter_.*\.php$", "debug_.*\.php$",
    "admin_xxx\.php$", "old_index\.php$",
    "khach_hang.*\.json$",
    "gemini-code.*\.txt$",
    "error_log$",
    "AutoSign\\.exe", "AutoSign\\.pdb",
    "AutoSign\\.vshost\\.exe",
    "AutoSign\\.manifest"
)

Write-Host "`n[1/3] Staging files..." -ForegroundColor Yellow
$files = Get-ChildItem -Path $root -Recurse -File
$copied = 0
$skipped = 0
foreach ($f in $files) {
    $rel = $f.FullName.Substring($root.Path.Length + 1)
    $skip = $false
    foreach ($ex in $excludePatterns) {
        if ($rel -match $ex) { $skip = $true; break }
    }
    if (-not $skip) {
        $dest = Join-Path $staging $rel
        New-Item -ItemType Directory -Path (Split-Path $dest) -Force | Out-Null
        Copy-Item $f.FullName -Destination $dest
        $copied++
    } else {
        $skipped++
    }
}
Write-Host "   Copied: $copied files"
Write-Host "   Skipped: $skipped files (excluded)"

# BUOC 2: Copy .env.example (KHONG copy .env that)
Write-Host "`n[2/3] Writing .env.example from .env..." -ForegroundColor Yellow
$envFile = Join-Path $root ".env"
$envDest = Join-Path $staging ".env.example"
if (Test-Path $envFile) {
    Get-Content $envFile | ForEach-Object {
        if ($_ -match "^\s*#|^\s*$") { $_ }
        elseif ($_ -match "^(.*?)=(.*)$") {
            $key = $matches[1]
            # Khoa DB_PASS, BOT_TOKEN, etc. thanh placeholder
            if ($key -match "PASS|SECRET|KEY|TOKEN|API") {
                "$key=YOUR_SECRET_HERE"
            } else {
                "$key=$($matches[2])"
            }
        } else { $_ }
    } | Set-Content $envDest
    Write-Host "   .env.example created"
} else {
    Write-Host "   .env not found, skipping" -ForegroundColor DarkYellow
}

# BUOC 3: Zip
Write-Host "`n[3/3] Zipping..." -ForegroundColor Yellow
Compress-Archive -Path "$staging\*" -DestinationPath $zipPath -CompressionLevel Optimal
$zipSize = [math]::Round((Get-Item $zipPath).Length / 1MB, 2)

# Cleanup staging
Remove-Item -Recurse -Force $staging

Write-Host "`n=== HOAN TAT ===" -ForegroundColor Green
Write-Host "File ZIP: $zipPath"
Write-Host "Size: $zipSize MB"
Write-Host ""
Write-Host "BUOC TIEP THEO (anh Vinh lam):" -ForegroundColor Yellow
Write-Host "1. Vao cPanel VinaHost: https://123.30.136.221:2083"
Write-Host "2. Login bang cPanel user 'kwkrbcce' + password cPanel"
Write-Host "3. Click 'File Manager'"
Write-Host "4. Navigate to: public_html/ (hoac thu muc DTH cua anh)"
Write-Host "5. Click 'Upload' -> chon file: $zipName"
Write-Host "6. Sau khi upload xong, click phai file zip -> 'Extract'"
Write-Host "7. Xoa file zip sau khi extract thanh cong"
Write-Host ""
