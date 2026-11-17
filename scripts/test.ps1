# Runs Pollen's tests from the development site beside this folder, on Pollen's own test database.
# Usage: powershell -NoProfile -File scripts/test.ps1 [-Filter name] [-Path tests/Unit]
param(
    [string]$Filter = '',
    [string]$Path = 'tests',
    [string]$Site = (Join-Path $PSScriptRoot '..\..\teme-dev')
)

$env:DB_DATABASE = 'pollen_test'
$env:TEME_VERSION = '1.0.0'
$target = Join-Path (Resolve-Path (Join-Path $PSScriptRoot '..')) $Path

Push-Location $Site
try {
    if ($Filter -ne '') {
        php artisan test $target --filter=$Filter
    } else {
        php artisan test $target
    }
    exit $LASTEXITCODE
} finally {
    Pop-Location
}
