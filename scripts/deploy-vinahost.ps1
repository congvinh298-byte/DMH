<#
.SYNOPSIS
    Deploy code DTH (dienmayhieu.com) len hosting VinaHost qua FTP.

.DESCRIPTION
    Script nay dong goi source (loai tru file secrets, dev tools, .git)
    va upload len hosting qua FTP thay vi push GitHub Actions.

    Dung khi:
    - FTP username/password co san trong cPanel
    - Muon deploy nhanh tu may local
    - GitHub Actions dang loi hoac chua cau hinh secrets

.EXAMPLE
    .\deploy-vinahost.ps1 -FtpServer "ftp.dienmayhieu.com" -FtpUser "kwkrbcce" -FtpPassword "xxx"

.NOTES
    File: scripts/deploy-vinahost.ps1
    Author: Ho tro boi AI assistant (Thien) cho anh Vinh
    Updated: 2026-06-24
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$FtpServer,
    [Parameter(Mandatory = $true)]
    [string]$FtpUser,
    [Parameter(Mandatory = $true)]
    [string]$FtpPassword,
    [string]$RemotePath = "/public_html",
    [string]$LocalPath = ".",
    [string]$ZipName = "deploy-dienmayhieu.zip"
)

$ErrorActionPreference = "Stop"
$root = Resolve-Path $LocalPath
$staging = Join-Path $env:TEMP "dth-deploy-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
$zipPath = Join-Path $staging $ZipName

Write-Host "`n=== DEPLOY DTH -> VinaHost ===" -ForegroundColor Cyan
Write-Host "Local:     $root"
Write-Host "Staging:   $staging"
Write-Host "Zip:       $zipPath"
Write-Host "FTP:       $FtpServer  as  $FtpUser"
Write-Host "Remote:    $RemotePath"
Write-Host ""

# --- BUOC 1: Staging folder ---
New-Item -ItemType Directory -Path $staging -Force | Out-Null

# Copy tat ca file, tru .git, .github, deploy scripts, .env thuc, node_modules, storage logs
$exclude = @(
    "\\.git[\\\\/]", "\\.github[\\\\/]", "\\.vscode[\\\\/]",
    "\\.env$", "\\.env\\.local$",
    "node_modules", "vendor", "dist", "build",
    "storage\\\\logs", "storage\\\\cache",
    "uploads[\\\\/]", "public\\\\uploads[\\\\/]",
    "scripts\\\\deploy-", "scripts\\\\codex-",
    "error_log", "bot_log\.txt", "\\*\.log",
    "AutoSign\\\\.*\\.exe$", "AutoSign\\\\.*\\.pdb$",
    "test\\.php$", "debug_.*\\.php$", "alter_.*\\.php$", "fix_.*\\.php$",
    "temp.*", "\\.bak$", "old_.*\\.php$", "khach_hang.*\\.json$"
)

Write-Host "[1/4] Copying files to staging..." -ForegroundColor Yellow
$files = Get-ChildItem -Path $root -Recurse -File
$copied = 0
foreach ($f in $files) {
    $rel = $f.FullName.Substring($root.Path.Length)
    $skip = $false
    foreach ($ex in $exclude) {
        if ($rel -match $ex) { $skip = $true; break }
    }
    if (-not $skip) {
        $dest = Join-Path $staging $rel
        New-Item -ItemType Directory -Path (Split-Path $dest) -Force | Out-Null
        Copy-Item $f.FullName -Destination $dest
        $copied++
    }
}
Write-Host "   Copied $copied files"

# --- BUOC 2: Tao .env.example tu .env thuc (KHONG commit secrets) ---
$envExample = Join-Path $staging ".env"
if (Test-Path (Join-Path $root ".env")) {
    Write-Host "[2/4] Writing .env (no secrets)..." -ForegroundColor Yellow
    Get-Content (Join-Path $root ".env") | ForEach-Object {
        if ($_ -match "^\s*#|^\s*$") { $_ }
        elseif ($_ -match "^(.*?)=(.*)$") { "$($matches[1])=$('"' + (Get-Random) + '"')" }
        else { $_ }
    } | Set-Content $envExample
} else {
    Write-Host "[2/4] .env not found, skipping" -ForegroundColor DarkYellow
}

# --- BUOC 3: Nen thanh zip ---
Write-Host "[3/4] Zipping staging folder..." -ForegroundColor Yellow
Compress-Archive -Path "$staging\*" -DestinationPath $zipPath -CompressionLevel Optimal
$zipSize = [math]::Round((Get-Item $zipPath).Length / 1MB, 2)
Write-Host "   Zip size: $zipSize MB"

# --- BUOC 4: Upload len FTP ---
Write-Host "[4/4] Uploading to FTP..." -ForegroundColor Yellow
$ftpUrl = "ftp://$FtpServer$RemotePath/$ZipName"

try {
    $webclient = New-Object System.Net.WebClient
    $webclient.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPassword)
    $webclient.UploadFile($ftpUrl, $zipPath)
    Write-Host "   Uploaded $ZipName to $ftpUrl"
} catch {
    Write-Host "   FTP upload FAILED: $_" -ForegroundColor Red
    Write-Host "   Kiem tra lai FTP_SERVER / FTP_USERNAME / FTP_PASSWORD" -ForegroundColor Red
    exit 1
}

# --- BUOC 5: Cleanup ---
Remove-Item -Recurse -Force $staging
Write-Host ""
Write-Host "=== UPLOAD DONE ===" -ForegroundColor Green
Write-Host "Buoc tiep theo (thu cong trong cPanel File Manager):"
Write-Host "  1. Vao cPanel > File Manager > public_html"
Write-Host "  2. Chon file $ZipName > Extract"
Write-Host "  3. Move noice vao public_html/ root"
Write-Host "  4. Xoa $ZipName sau khi extract"
Write-Host "  5. Edit .env voi thong tin that (DB_PASS, BOT_TOKEN, ...)"
Write-Host ""
