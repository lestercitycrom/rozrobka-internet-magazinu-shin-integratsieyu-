param(
    [string] $PhpBinary = 'php',
    [string] $NpmBinary = 'npm',
    [string] $DeployTarget = 'easytire-deploy@95.217.153.189'
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

$expectedRevision = (git -C $projectRoot rev-parse HEAD).Trim()
$deployedRevision = & ssh $DeployTarget 'cat /var/www/staging-easytire.bethub.fun/current/REVISION'
$sshExitCode = $LASTEXITCODE
$deployedRevision = $deployedRevision | Select-Object -First 1

if ($sshExitCode -ne 0 -or [string]::IsNullOrWhiteSpace($deployedRevision) -or $deployedRevision.Trim() -ne $expectedRevision) {
    throw "Git received $expectedRevision, but staging did not activate that revision."
}

Write-Host "Staging activated revision $($deployedRevision.Trim())."
