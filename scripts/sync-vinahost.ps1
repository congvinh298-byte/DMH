<#
.SYNOPSIS
    Sync DTH source files to VinaHost public_html via FTP (file-by-file).

.DESCRIPTION
    Dong bo tung file tu local len host qua FTP, khong can giai nen zip thu cong.

.EXAMPLE
    .\sync-vinahost.ps1 -FtpServer "dienmayhieu.com" -FtpUser "dth@dienmayhieu.com" -FtpPassword "Anhthien369@"

.NOTES
    File: scripts/sync-vinahost.ps1
    Author: Thien (AI assistant) for anh Vinh
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
    [string]$LocalPath = "."
)

$ErrorActionPreference = "Stop"
$root = Resolve-Path $LocalPath

Write-Host "`n=== SYNC DTH -> VinaHost ===" -ForegroundColor Cyan
Write-Host "Local:  $root"
Write-Host "FTP:    $FtpServer  as  $FtpUser"
Write-Host "Remote: $RemotePath"
Write-Host ""

function Should-Exclude($fullPath) {
    $p = $fullPath.ToLowerInvariant()
    # Skip VCS/dev/tool folders and sensitive files.
    if ($p -like '*\.git\*' -or $p -like '*\AutoSign\*') { return $true }
    if ($p -like '*\.github\*' -or $p -like '*\.vscode\*') { return $true }
    if ($p -like '*\node_modules\*' -or $p -like '*\vendor\*' -or $p -like '*\dist\*' -or $p -like '*\build\*') { return $true }
    if ($p -like '*\storage\logs\*' -or $p -like '*\storage\cache\*') { return $true }
    if ($p -like '*\uploads\*' -and -not ($p -like '*\uploads\index.html')) { return $true }
    if ($p -like '*\public\uploads\*') { return $true }
    if ($p -like '*\scripts\deploy-*' -or $p -like '*\scripts\codex-*' -or $p -like '*\scripts\sync-*') { return $true }
    $name = Split-Path $p -Leaf
    if ($name -eq 'error_log' -or $name -like '*.log' -or $name -like '*.bak') { return $true }
    if ($name -like 'temp*' -or $name -like 'test.php' -or $name -like 'debug_*.php' -or $name -like 'alter_*.php' -or $name -like 'fix_*.php' -or $name -like 'old_*.php' -or $name -like 'khach_hang*.json') { return $true }
    return $false
}

function Ensure-FtpDirectory($ftpDirUrl) {
    try {
        $req = [System.Net.FtpWebRequest]::Create($ftpDirUrl)
        $req.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
        $req.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPassword)
        $req.UseBinary = $true
        $req.KeepAlive = $false
        $resp = $req.GetResponse()
        $resp.Close()
    } catch {
        # Directory may already exist; ignore error.
    }
}

function Upload-File($localFile, $ftpFileUrl) {
    $req = [System.Net.FtpWebRequest]::Create($ftpFileUrl)
    $req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $req.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPassword)
    $req.UseBinary = $true
    $req.KeepAlive = $false
    $content = [System.IO.File]::ReadAllBytes($localFile)
    $req.ContentLength = $content.Length
    $stream = $req.GetRequestStream()
    $stream.Write($content, 0, $content.Length)
    $stream.Close()
    $resp = $req.GetResponse()
    $resp.Close()
}

$files = Get-ChildItem -Path $root -Recurse -File | Where-Object { -not (Should-Exclude $_.FullName) } | Sort-Object FullName

$total = $files.Count
$uploaded = 0
$errors = @()

foreach ($f in $files) {
    $rel = $f.FullName.Substring($root.Path.Length).Replace('\', '/').TrimStart('/')
    $ftpFileUrl = "ftp://$FtpServer$RemotePath/$rel"
    $dir = ($rel -replace '/[^/]+$', '')

    if ($dir -and $dir -ne $rel) {
        $ftpDirUrl = "ftp://$FtpServer$RemotePath/$dir"
        Ensure-FtpDirectory $ftpDirUrl
    }

    try {
        Upload-File $f.FullName $ftpFileUrl
        $uploaded++
        Write-Host "[$uploaded/$total] $rel" -ForegroundColor Green
    } catch {
        $errors += "$rel : $_"
        Write-Host "[FAIL] $rel : $_" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "=== SYNC DONE ===" -ForegroundColor Cyan
Write-Host "Uploaded: $uploaded / $total" -ForegroundColor Green
if ($errors.Count -gt 0) {
    Write-Host "Errors: $($errors.Count)" -ForegroundColor Red
    $errors | ForEach-Object { Write-Host "  $_" -ForegroundColor Red }
} else {
    Write-Host "No errors." -ForegroundColor Green
}
Write-Host ""
Write-Host "Next steps on host:"
Write-Host "  1. Ensure database kwkrbcce_dienmayhieulapvo exists and user kwkrbcce_baocao has privileges."
Write-Host "  2. Run: php scripts/seed-bct-test-customer.php"
Write-Host "  3. Visit https://dienmayhieu.com and login demo with anhthien / Anhthien369@"
