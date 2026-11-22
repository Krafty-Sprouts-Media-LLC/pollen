# Runs Pollen's tests from the development site, on Pollen's own test database (pollen_test).
#
#   powershell -NoProfile -File scripts/test.ps1 [-Filter name] [-Path tests/Unit]
#
# The development site is found in the POLLEN_TEME_SITE environment variable, or at
# C:\Users\kings\Downloads\Projects\teme-plugins\teme-dev when that is not set.
param(
    [string]$Filter = '',
    [string]$Path = 'tests',
    [string]$Site = $(if ($env:POLLEN_TEME_SITE) { $env:POLLEN_TEME_SITE } else { 'C:\Users\kings\Downloads\Projects\teme-plugins\teme-dev' })
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
