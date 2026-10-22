$ErrorActionPreference = 'Stop'

$workspaceRoot = Split-Path -Parent $PSScriptRoot
Set-Location $workspaceRoot

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    throw 'PHP was not found on PATH. Add the PHP executable to PATH before starting the scheduler.'
}

& php artisan schedule:work
exit $LASTEXITCODE