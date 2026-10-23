[CmdletBinding()]
param(
    [string]$OutputRoot = (Join-Path (Split-Path -Parent $PSScriptRoot) 'release')
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$RepoRoot = Split-Path -Parent $PSScriptRoot
$ApiSource = Join-Path $RepoRoot 'api.untoquedeluz'
$FrontendSource = Join-Path $RepoRoot 'React.js\un_toque_de_luz'
$FrontendEnv = Join-Path $FrontendSource '.env.production'
$ComposerManifest = Join-Path $ApiSource 'composer.json'
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$releaseName = "untoquedeluz-$stamp"
$releaseDirectory = Join-Path $OutputRoot $releaseName
$workspace = Join-Path $env:TEMP ("untoquedeluz-build-" + [guid]::NewGuid().ToString('N'))
$apiStage = Join-Path $workspace 'api'
$frontendStage = Join-Path $workspace 'frontend'
$frontendPackage = Join-Path $workspace 'frontend-package'
$apiArchive = Join-Path $releaseDirectory ($releaseName + '-api.zip')
$frontendArchive = Join-Path $releaseDirectory ($releaseName + '-frontend.zip')

function Copy-ReleaseTree {
    param(
        [Parameter(Mandatory = $true)][string]$Source,
        [Parameter(Mandatory = $true)][string]$Destination,
        [Parameter(Mandatory = $true)][string[]]$ExcludedDirectories
    )

    New-Item -ItemType Directory -Path $Destination -Force | Out-Null

    $excludedFiles = @(
        '.env*', '*.sqlite', '*.sqlite-*',
        '*.sql', '*.sql.*', '*.dump', '*.dump.*', '*.bak', '*.backup',
        '*.log', '.phpunit*', 'phpunit*.xml'
    )
    $copyArguments = @(
        $Source, $Destination, '/E', '/COPY:DAT', '/DCOPY:DAT',
        '/R:1', '/W:1', '/NFL', '/NDL', '/NJH', '/NJS', '/NP', '/XJ',
        '/XD'
    ) + $ExcludedDirectories + @('/XF') + $excludedFiles

    & robocopy @copyArguments | Out-Null
    if ($LASTEXITCODE -ge 8) {
        throw "Could not copy release source tree (robocopy exit $LASTEXITCODE)."
    }
}

function Read-FrontendValue {
    param(
        [Parameter(Mandatory = $true)][string]$Contents,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $escapedName = [regex]::Escape($Name)
    $match = [regex]::Match($Contents, "(?m)^\s*$escapedName\s*=\s*(?<value>.*?)\s*$")
    if (-not $match.Success) {
        throw "Missing required frontend variable: $Name. Values are not displayed by this script."
    }

    return $match.Groups['value'].Value.Trim().Trim('"').Trim("'")
}

function Assert-ArchiveContainsNoPrivateFiles {
    param([Parameter(Mandatory = $true)][string]$Path)

    $archive = [System.IO.Compression.ZipFile]::OpenRead($Path)
    try {
        $forbidden = @($archive.Entries | Where-Object {
            $_.FullName -match '(?i)(^|/)\.env(?:$|\.)' -or
            $_.FullName -match '(?i)(^|/)(tests?|node_modules|\.git)(/|$)' -or
            $_.FullName -match '(?i)\.(sql|dump|bak|backup)(\.|$)' -or
            $_.FullName -match '(?i)(^|/)storage/logs/'
        })

        if ($forbidden.Count -gt 0) {
            throw "Release archive contains excluded files: $($forbidden.Count)"
        }
    } finally {
        $archive.Dispose()
    }
}

if (-not (Test-Path $ApiSource -PathType Container)) {
    throw 'Laravel API source directory was not found.'
}
if (-not (Test-Path $FrontendSource -PathType Container)) {
    throw 'Frontend source directory was not found.'
}
if (-not (Test-Path $FrontendEnv -PathType Leaf)) {
    throw 'Create React.js/un_toque_de_luz/.env.production locally before building. The file is never included in release archives.'
}

$manifest = Get-Content -Raw $ComposerManifest | ConvertFrom-Json
if ($manifest.config.platform.php -notmatch '^8\.3(?:\.\d+)?$') {
    throw 'composer.json must pin config.platform.php to PHP 8.3.'
}

$frontendEnvironment = [System.IO.File]::ReadAllText($FrontendEnv)
$frontendApiUrl = Read-FrontendValue -Contents $frontendEnvironment -Name 'VITE_API_URL'
$whatsappNumber = Read-FrontendValue -Contents $frontendEnvironment -Name 'VITE_WHATSAPP_NUMBER'
if ($frontendApiUrl -notmatch '^https://') {
    throw 'VITE_API_URL must use HTTPS.'
}
if ([string]::IsNullOrWhiteSpace($whatsappNumber) -or $whatsappNumber -notmatch '^\d+$') {
    throw 'VITE_WHATSAPP_NUMBER must contain digits only. Its value is not displayed.'
}

$phpVersion = (& php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;').Trim()
if ($LASTEXITCODE -ne 0 -or $phpVersion -ne '8.3') {
    throw 'Run this script with PHP 8.3 on PATH.'
}
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    throw 'Composer was not found on PATH.'
}
if (-not (Get-Command npm -ErrorAction SilentlyContinue)) {
    throw 'npm was not found on PATH.'
}
if (Test-Path $releaseDirectory) {
    throw 'Release output already exists; choose a new timestamp or output directory.'
}

$releaseCompleted = $false
$releaseCreated = $false
$previousLegalApiConfigPath = [Environment]::GetEnvironmentVariable('LEGAL_API_CONFIG_PATH', 'Process')
$previousLegalProductionEnvPath = [Environment]::GetEnvironmentVariable('LEGAL_PRODUCTION_ENV_PATH', 'Process')

try {
    New-Item -ItemType Directory -Path $OutputRoot -Force | Out-Null
    New-Item -ItemType Directory -Path $releaseDirectory | Out-Null
    $releaseCreated = $true
    New-Item -ItemType Directory -Path $workspace -Force | Out-Null
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    $apiExcludedDirectories = @(
        (Join-Path $ApiSource '.git'),
        (Join-Path $ApiSource 'vendor'),
        (Join-Path $ApiSource 'node_modules'),
        (Join-Path $ApiSource 'tests'),
        (Join-Path $ApiSource 'public\storage'),
        (Join-Path $ApiSource 'storage\logs'),
        (Join-Path $ApiSource 'storage\framework\cache'),
        (Join-Path $ApiSource 'storage\framework\sessions'),
        (Join-Path $ApiSource 'storage\framework\views'),
        (Join-Path $ApiSource 'bootstrap\cache')
    )
    Copy-ReleaseTree -Source $ApiSource -Destination $apiStage -ExcludedDirectories $apiExcludedDirectories

    foreach ($directory in @(
        'public\storage\img',
        'storage\logs',
        'storage\framework\cache\data',
        'storage\framework\sessions',
        'storage\framework\views',
        'bootstrap\cache'
    )) {
        New-Item -ItemType Directory -Path (Join-Path $apiStage $directory) -Force | Out-Null
    }

    $platform = (& composer --working-dir $apiStage config platform.php).Trim()
    if ($LASTEXITCODE -ne 0 -or $platform -notmatch '^8\.3(?:\.\d+)?$') {
        throw 'The copied API does not declare composer config.platform.php=8.3.'
    }

    Push-Location $apiStage
    try {
        & composer install --no-dev --optimize-autoloader --no-interaction
        if ($LASTEXITCODE -ne 0) {
            throw 'Composer install failed for the API release.'
        }
    } finally {
        Pop-Location
    }

    if (-not (Test-Path (Join-Path $apiStage 'vendor\autoload.php'))) {
        throw 'Composer did not create the API vendor autoloader.'
    }

    $frontendExcludedDirectories = @(
        (Join-Path $FrontendSource '.git'),
        (Join-Path $FrontendSource 'node_modules'),
        (Join-Path $FrontendSource 'dist'),
        (Join-Path $FrontendSource 'tests')
    )
    Copy-ReleaseTree -Source $FrontendSource -Destination $frontendStage -ExcludedDirectories $frontendExcludedDirectories
    Copy-Item -LiteralPath $FrontendEnv -Destination (Join-Path $frontendStage '.env.production')

    Push-Location $frontendStage
    try {
        & npm ci --no-audit --no-fund
        if ($LASTEXITCODE -ne 0) {
            throw 'npm ci failed for the frontend release.'
        }

        $env:LEGAL_API_CONFIG_PATH = Join-Path $apiStage 'config/legal.php'
        $env:LEGAL_PRODUCTION_ENV_PATH = Join-Path $ApiSource '.env.production.example'
        & npm run check:legal
        if ($LASTEXITCODE -ne 0) {
            throw 'Legal content still has unresolved markers; release build is blocked.'
        }

        & npm run build
        if ($LASTEXITCODE -ne 0) {
            throw 'Vite production build failed.'
        }
    } finally {
        Pop-Location
    }

    $frontendDist = Join-Path $frontendStage 'dist'
    if (-not (Test-Path (Join-Path $frontendDist 'img\ActiYoga.jpg'))) {
        throw 'The production frontend build is missing img/ActiYoga.jpg.'
    }

    New-Item -ItemType Directory -Path $frontendPackage -Force | Out-Null
    Copy-Item -Path (Join-Path $frontendDist '*') -Destination $frontendPackage -Recurse -Force
    [System.IO.Compression.ZipFile]::CreateFromDirectory($apiStage, $apiArchive)
    [System.IO.Compression.ZipFile]::CreateFromDirectory($frontendPackage, $frontendArchive)

    Assert-ArchiveContainsNoPrivateFiles -Path $apiArchive
    Assert-ArchiveContainsNoPrivateFiles -Path $frontendArchive

    Write-Output 'Release archives created (environment values are not displayed):'
    Write-Output $apiArchive
    Write-Output $frontendArchive
    $releaseCompleted = $true
} finally {
    if (Test-Path $workspace) {
        Remove-Item -LiteralPath $workspace -Recurse -Force
    }
    if ($null -eq $previousLegalApiConfigPath) {
        Remove-Item Env:LEGAL_API_CONFIG_PATH -ErrorAction SilentlyContinue
    } else {
        $env:LEGAL_API_CONFIG_PATH = $previousLegalApiConfigPath
    }
    if ($null -eq $previousLegalProductionEnvPath) {
        Remove-Item Env:LEGAL_PRODUCTION_ENV_PATH -ErrorAction SilentlyContinue
    } else {
        $env:LEGAL_PRODUCTION_ENV_PATH = $previousLegalProductionEnvPath
    }
    if (-not $releaseCompleted -and $releaseCreated -and (Test-Path $releaseDirectory)) {
        Remove-Item -LiteralPath $releaseDirectory -Recurse -Force
    }
}