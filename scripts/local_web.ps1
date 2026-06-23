param(
    [int]$Port = 8090
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $ProjectRoot

Write-Host "Starting Dien Tu Hieu local web at http://127.0.0.1:$Port"
Write-Host "Press Ctrl+C to stop."
php -S "127.0.0.1:$Port" -t $ProjectRoot
