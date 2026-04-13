$preferredPort = 8000
$maxPort = 8100
$selectedPort = $preferredPort

while ($selectedPort -le $maxPort) {
    $inUse = Get-NetTCPConnection -LocalPort $selectedPort -ErrorAction SilentlyContinue
    if (-not $inUse) {
        break
    }

    $selectedPort++
}

if ($selectedPort -gt $maxPort) {
    throw "No free port found between $preferredPort and $maxPort."
}

$env:APP_PORT = [string]$selectedPort

Write-Host "Using API host port $selectedPort"
docker compose up --build -d

if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

Write-Host "API available at http://localhost:$selectedPort"
