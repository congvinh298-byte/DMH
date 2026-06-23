param(
    [string]$Model = "gemma4:12b",
    [string]$BaseUrl = "http://127.0.0.1:11434",
    [string]$ModelDir = "D:\Ollama\models",
    [switch]$SkipInstall,
    [switch]$SkipPull
)

$ErrorActionPreference = "Stop"

if ($ModelDir -ne "") {
    New-Item -ItemType Directory -Force -Path $ModelDir | Out-Null
    $env:OLLAMA_MODELS = $ModelDir
    [Environment]::SetEnvironmentVariable("OLLAMA_MODELS", $ModelDir, "User")
    Write-Host "Using Ollama model directory: $ModelDir"
}

function Add-OllamaPath {
    $candidates = @(
        (Join-Path $env:LOCALAPPDATA "Programs\Ollama")
        (Join-Path $env:ProgramFiles "Ollama")
    )
    foreach ($path in $candidates) {
        if ((Test-Path $path) -and (($env:Path -split ";") -notcontains $path)) {
            $env:Path = "$path;$env:Path"
        }
    }
}

function Get-OllamaCommand {
    Add-OllamaPath
    return Get-Command ollama -ErrorAction SilentlyContinue
}

function Wait-OllamaApi {
    param([int]$Seconds = 30)
    $deadline = (Get-Date).AddSeconds($Seconds)
    do {
        try {
            Invoke-RestMethod "$BaseUrl/api/tags" -TimeoutSec 2 | Out-Null
            return $true
        } catch {
            Start-Sleep -Seconds 1
        }
    } while ((Get-Date) -lt $deadline)
    return $false
}

$ollama = Get-OllamaCommand
if (-not $ollama) {
    if ($SkipInstall) {
        throw "Ollama is not installed or not in PATH."
    }
    $winget = Get-Command winget -ErrorAction SilentlyContinue
    if (-not $winget) {
        throw "winget is not available. Install Ollama from https://ollama.com/download/windows, then rerun this script."
    }

    Write-Host "Installing Ollama with winget..."
    & $winget.Source install -e --id Ollama.Ollama --silent --accept-source-agreements --accept-package-agreements
    $ollama = Get-OllamaCommand
    if (-not $ollama) {
        throw "Ollama install finished, but ollama.exe was not found in PATH. Open a new PowerShell window and rerun this script."
    }
}

if (-not (Wait-OllamaApi -Seconds 5)) {
    Write-Host "Starting Ollama server..."
    Start-Process -FilePath $ollama.Source -ArgumentList "serve" -WindowStyle Hidden
    if (-not (Wait-OllamaApi -Seconds 45)) {
        throw "Ollama server did not answer at $BaseUrl."
    }
}

if (-not $SkipPull) {
    Write-Host "Pulling model $Model. This can take a while..."
    & $ollama.Source pull $Model
}

$body = @{
    model = $Model
    stream = $false
    think = $false
    messages = @(
        @{ role = "system"; content = "Tra loi tieng Viet ngan gon." },
        @{ role = "user"; content = "Hay noi mot cau xac nhan Anh Thien AI da san sang." }
    )
} | ConvertTo-Json -Depth 6

$reply = Invoke-RestMethod "$BaseUrl/api/chat" -Method Post -ContentType "application/json" -Body $body -TimeoutSec 120
Write-Host "Ollama is ready."
Write-Host ("Model: " + $Model)
Write-Host ("Reply: " + $reply.message.content)
