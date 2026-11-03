$ErrorActionPreference = 'Continue'

Set-Location (Split-Path -Parent $PSScriptRoot)

# Docker Desktop starten, falls die Engine noch nicht läuft
docker info *> $null
if ($LASTEXITCODE -ne 0) {
    Start-Process 'C:\Program Files\Docker\Docker\Docker Desktop.exe'
    for ($i = 0; $i -lt 60; $i++) {
        Start-Sleep 5
        docker info *> $null
        if ($LASTEXITCODE -eq 0) { break }
    }
    if ($LASTEXITCODE -ne 0) { throw 'Docker-Engine ist nicht gestartet.' }
}

$stacks = @(
    @{ Name = 'Live';    Project = 'pixel';         Port = 8000; Files = @('docker-compose.yml', 'docker-compose.local-backup.yml', 'docker-compose.local-tls.yml') },
    @{ Name = 'Staging'; Project = 'pixel-staging'; Port = 8001; Files = @('docker-compose.yml', 'docker-compose.local-tls.yml') }
)

foreach ($s in $stacks) {
    $env:HTTPS_PORT = "$($s.Port)"
    $env:HTTPS_SUFFIX = ":$($s.Port)"
    $fileArgs = $s.Files | ForEach-Object { '-f'; $_ }

    docker compose -p $s.Project @fileArgs up -d --build
    if ($LASTEXITCODE -ne 0) { throw "$($s.Name): docker compose up fehlgeschlagen." }

    # Die Migrationen laufen nicht automatisch; sie sind idempotent.
    docker compose -p $s.Project @fileArgs exec -T app php artisan migrate --force
    if ($LASTEXITCODE -ne 0) { throw "$($s.Name): migrate fehlgeschlagen." }
}

foreach ($s in $stacks) {
    $code = $null
    for ($i = 0; $i -lt 20 -and $code -ne '200'; $i++) {
        Start-Sleep 3
        $code = curl.exe -sk -o NUL -w '%{http_code}' "https://localhost:$($s.Port)"
    }
    "{0}: https://localhost:{1} -> {2}" -f $s.Name, $s.Port, $code
}
