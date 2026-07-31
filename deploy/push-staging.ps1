param(
    [string] $PhpBinary = 'php',
    [string] $NpmBinary = 'npm'
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$applicationRoot = Join-Path $projectRoot 'application'

if ((git -C $projectRoot branch --show-current) -ne 'main') {
    throw 'Staging deployment is allowed only from main.'
}

if (git -C $projectRoot status --porcelain) {
    throw 'Commit or discard local changes before staging deployment.'
}

$phpDirectory = Split-Path -Parent $PhpBinary
$npmDirectory = Split-Path -Parent $NpmBinary

if ($phpDirectory) {
    $env:Path = "$phpDirectory;$env:Path"
}

if ($npmDirectory) {
    $env:Path = "$npmDirectory;$env:Path"
}

Push-Location $applicationRoot

try {
    & $PhpBinary vendor/bin/pest
    if ($LASTEXITCODE -ne 0) { throw 'Pest failed.' }

    & $NpmBinary run build
    if ($LASTEXITCODE -ne 0) { throw 'Frontend build failed.' }

    & $NpmBinary run test:e2e
    if ($LASTEXITCODE -ne 0) { throw 'Playwright failed.' }
}
finally {
    Pop-Location
}

git -C $projectRoot push staging main
if ($LASTEXITCODE -ne 0) { throw 'Git push or remote deployment failed.' }
