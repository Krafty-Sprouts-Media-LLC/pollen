# Runs an artisan command, or serves the site, from the development site beside this folder,
# on Pollen's own database (pollen_dev), with Pollen's folder as the only plugin path and
# Teme reporting 1.0.0. Nothing in the development site's .env is changed.
#
#   powershell -NoProfile -File scripts/dev.ps1 migrate --force
#   powershell -NoProfile -File scripts/dev.ps1 serve
param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Command)

$site = Resolve-Path (Join-Path $PSScriptRoot '..\..\teme-dev')
$pollen = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path -replace '\\', '/'

$env:DB_DATABASE = 'pollen_dev'
$env:TEME_VERSION = '1.0.0'
$env:TEME_PLUGIN_PATHS = $pollen
$env:APP_URL = 'http://127.0.0.1:8199'
$env:QUEUE_CONNECTION = 'sync'

Push-Location $site
try {
    if ($Command.Count -gt 0 -and $Command[0] -eq 'serve') {
        # PHP's own server passes this process's environment on; artisan serve doesn't.
        Set-Location public
        php -S 127.0.0.1:8199 ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php
    } else {
        php artisan @Command
    }
    exit $LASTEXITCODE
} finally {
    Pop-Location
}
