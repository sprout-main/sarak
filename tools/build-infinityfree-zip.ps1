<#
.SYNOPSIS
Builds sarak_infinityfree_deployment.zip, a bundle that extracts straight into
InfinityFree's /htdocs with no post-extract commands.

.DESCRIPTION
Copies an explicit allowlist into a clean staging tree, installs production
vendors, then archives it. Deny by default: anything not named below is not
shipped. That matters because the root .htaccess deny list does not cover
bangued/, woman/, tools/ or tasks/, so a stray copy would be web readable.

Uses [System.IO.Compression.ZipFile] rather than Compress-Archive on purpose:
PowerShell 5.1's Compress-Archive silently skips hidden files, which would drop
.env and both .htaccess files and leave a bundle that cannot boot.
#>
[CmdletBinding()]
param(
    [string] $ProjectRoot,
    [string] $OutFile     = 'sarak_infinityfree_deployment.zip',
    [switch] $SkipComposer
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

# $PSScriptRoot is not reliably bound inside a param default when this runs as
# `powershell -File`, so resolve it here and step up out of tools/.
if (-not $ProjectRoot) {
    $ProjectRoot = if ($PSScriptRoot) { Split-Path -Parent $PSScriptRoot } else { (Get-Location).Path }
}

$stage = Join-Path $env:TEMP ('sarak-deploy-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
$out   = Join-Path $ProjectRoot $OutFile

function Copy-Tree([string] $Relative) {
    $src = Join-Path $ProjectRoot $Relative
    if (-not (Test-Path -LiteralPath $src)) { throw "Missing source tree: $Relative" }
    $dst = Join-Path $stage $Relative
    New-Item -ItemType Directory -Path $dst -Force | Out-Null
    Copy-Item -Path (Join-Path $src '*') -Destination $dst -Recurse -Force
}

function Write-Guard([string] $RelativeDir) {
    $dir = Join-Path $stage $RelativeDir
    if (-not (Test-Path -LiteralPath $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
    $body = @(
        '<IfModule mod_authz_core.c>',
        '    Require all denied',
        '</IfModule>',
        '<IfModule !mod_authz_core.c>',
        '    Order allow,deny',
        '    Deny from all',
        '</IfModule>'
    )
    Set-Content -LiteralPath (Join-Path $dir '.htaccess') -Value $body -Encoding ASCII
    # Keeps the directory in the archive when it holds nothing else.
    Set-Content -LiteralPath (Join-Path $dir '.gitignore') -Value "*`n!.gitignore" -Encoding ASCII
}

Write-Host "staging -> $stage"

foreach ($tree in @('app', 'bootstrap', 'config', 'database', 'resources', 'routes')) {
    Copy-Tree $tree
}

foreach ($file in @('artisan', 'composer.json', 'composer.lock', '.gitattributes')) {
    Copy-Item -LiteralPath (Join-Path $ProjectRoot $file) -Destination $stage -Force
}

# public/, minus the dev-only Vite inputs.
New-Item -ItemType Directory -Path (Join-Path $stage 'public') -Force | Out-Null
Copy-Item -Path (Join-Path $ProjectRoot 'public/*') -Destination (Join-Path $stage 'public') -Recurse -Force
if (Test-Path (Join-Path $stage 'public/hot')) { Remove-Item (Join-Path $stage 'public/hot') -Force }
if (Test-Path (Join-Path $stage 'public/storage')) { Remove-Item (Join-Path $stage 'public/storage') -Recurse -Force }

# Production vendors only.
if (-not $SkipComposer) {
    $vendorDir = Join-Path $stage 'vendor'
    New-Item -ItemType Directory -Path $vendorDir -Force | Out-Null

    $composer = Get-Command composer -ErrorAction SilentlyContinue
    if (-not $composer) { throw 'composer not found on PATH; install it or pass -SkipComposer.' }
    Write-Host "composer: $($composer.Source)"

    Push-Location $stage
    try {
        & $composer.Source install --no-dev --optimize-autoloader --no-scripts --no-interaction --quiet
        if ($LASTEXITCODE -ne 0) { throw "composer install failed with exit code $LASTEXITCODE" }
    } finally { Pop-Location }
}

# Photos ship byte-identical to the database paths, re-encoded or not.
$photosDest = Join-Path $stage 'public/storage/photos'
New-Item -ItemType Directory -Path $photosDest -Force | Out-Null
Copy-Item -Path (Join-Path $ProjectRoot 'storage/app/public/photos/*') -Destination $photosDest -Recurse -Force
New-Item -ItemType Directory -Path (Join-Path $stage 'public/storage/avatars') -Force | Out-Null
Write-Guard 'public/storage'

# Map tiles are served by TileProxyController out of storage/app.
Copy-Tree 'storage/app/tiles'

# The repository's cached package/provider manifests were generated on a machine
# that had the dev dependencies installed, so they list laravel/breeze,
# laravel/sail, nunomaduro/collision and spatie/laravel-ignition. Shipping them
# makes the deployed app try to register classes that --no-dev never installed
# and it dies with "CollisionServiceProvider not found" on the first request.
# Both are rebuilt lazily on first boot, so they are deliberately left out.
foreach ($cache in @('bootstrap/cache/packages.php', 'bootstrap/cache/services.php')) {
    $path = Join-Path $stage $cache
    if (Test-Path -LiteralPath $path) {
        Remove-Item -LiteralPath $path -Force
        Write-Host "dropped  : $cache (stale dev manifest)"
    }
}

foreach ($dir in @(
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/framework/testing',
    'storage/logs'
)) {
    Write-Guard $dir
}
Write-Guard 'storage/app'
Write-Guard 'bootstrap/cache'

# .env ships ready to run. APP_KEY here must match the live one or every
# existing session and encrypted column is invalidated.
Copy-Item -LiteralPath (Join-Path $ProjectRoot '.env.infinityfree') -Destination (Join-Path $stage '.env') -Force
Copy-Item -LiteralPath (Join-Path $ProjectRoot '.env.infinityfree') -Destination (Join-Path $stage '.env.infinityfree') -Force
Copy-Item -LiteralPath (Join-Path $ProjectRoot '.htaccess') -Destination $stage -Force

# phpMyAdmin import. The root .htaccess already blocks *.sql over HTTP.
$dump = Join-Path $ProjectRoot 'database/infinityfree_database.sql'
if (Test-Path -LiteralPath $dump) {
    Copy-Item -LiteralPath $dump -Destination (Join-Path $stage 'infinityfree_database.sql') -Force
} else {
    Write-Warning 'No database/infinityfree_database.sql found; zip will ship without a dump.'
}

function New-ZipFromTree([string] $SourceDir, [string] $ZipPath) {
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    Add-Type -AssemblyName System.IO.Compression

    # [System.IO.Compression.ZipFile]::CreateFromDirectory writes entry names with
    # the Windows separator, so it emits "app\Http\Controllers\X.php" as one flat
    # filename. The ZIP spec requires forward slashes and Linux unzip will honour
    # that literally, creating a single file with backslashes in its name. Entries
    # are therefore written by hand with normalised names.
    #
    # DirectoryInfo.FullName is used rather than the string passed in: the temp
    # path arrives in 8.3 short form (ADMINI~1) while the enumerated children
    # report the long form, and subtracting lengths from mismatched prefixes
    # silently truncates entry names instead of failing.
    $root = (New-Object System.IO.DirectoryInfo($SourceDir)).FullName.TrimEnd('\')

    if (Test-Path -LiteralPath $ZipPath) { Remove-Item -LiteralPath $ZipPath -Force }

    $stream = [System.IO.File]::Open($ZipPath, [System.IO.FileMode]::CreateNew)
    try {
        $archive = New-Object System.IO.Compression.ZipArchive($stream, [System.IO.Compression.ZipArchiveMode]::Create)
        try {
            $dirs = @(Get-ChildItem -LiteralPath $root -Recurse -Directory -Force)
            $dirsWithFiles = @{}
            foreach ($d in $dirs) { $dirsWithFiles[$d.FullName] = $false }

            foreach ($file in Get-ChildItem -LiteralPath $root -Recurse -File -Force) {
                $dir = Split-Path -Parent $file.FullName
                while ($dir -and $dir.StartsWith($root)) {
                    if ($dirsWithFiles.ContainsKey($dir)) { $dirsWithFiles[$dir] = $true }
                    if ($dir -eq $root) { break }
                    $dir = Split-Path -Parent $dir
                }

                if (-not $file.FullName.StartsWith($root + '\')) {
                    throw "Path escapes the archive root: $($file.FullName)"
                }

                $entryName = $file.FullName.Substring($root.Length + 1).Replace('\', '/')
                [void] [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                    $archive, $file.FullName, $entryName, [System.IO.Compression.CompressionLevel]::Optimal
                )
            }

            # Empty directories carry no file entries, so they have to be added
            # explicitly or they vanish on extraction.
            foreach ($d in $dirs) {
                if (-not $dirsWithFiles[$d.FullName]) {
                    $entryName = $d.FullName.Substring($root.Length + 1).Replace('\', '/') + '/'
                    [void] $archive.CreateEntry($entryName)
                }
            }
        } finally { $archive.Dispose() }
    } finally { $stream.Dispose() }
}

function Assert-ZipEntries([string] $ZipPath) {
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip = [System.IO.Compression.ZipFile]::OpenRead($ZipPath)
    try { $names = @($zip.Entries | ForEach-Object { $_.FullName }) } finally { $zip.Dispose() }

    $bad = @($names | Where-Object { $_ -like '*\*' })
    if ($bad.Count -gt 0) { throw "ZIP spec violation: $($bad.Count) entries use backslashes." }

    $mustHave = @(
        '.env', '.htaccess', 'artisan', 'composer.json', 'composer.lock',
        'infinityfree_database.sql', 'public/.htaccess', 'public/index.php',
        'public/build/manifest.json', 'public/storage/.htaccess',
        'storage/logs/.htaccess', 'storage/framework/views/.htaccess'
    )
    $missing = @($mustHave | Where-Object { $names -notcontains $_ })
    if ($missing.Count -gt 0) { throw "Required entries absent from the zip: $($missing -join ', ')" }

    # A cached manifest naming a dev-only package is a fatal on first request.
    $mustNotHave = @('bootstrap/cache/packages.php', 'bootstrap/cache/services.php')
    $present = @($mustNotHave | Where-Object { $names -contains $_ })
    if ($present.Count -gt 0) { throw "Stale dev manifests shipped: $($present -join ', ')" }

    $forbidden = @('bangued/', 'woman/', 'node_modules/', 'tests/', 'tools/',
        'scratch/', 'tasks/', 'docs/', 'sarakbangued/', 'vendor/phpunit/',
        'vendor/laravel/pint/', 'vendor/fakerphp/', 'vendor/spatie/laravel-ignition/')
    $leaked = @($forbidden | Where-Object {
        $prefix = $_
        @($names | Where-Object { $_.StartsWith($prefix) }).Count -gt 0
    })
    if ($leaked.Count -gt 0) { throw "Forbidden trees present in the zip: $($leaked -join ', ')" }

    $flat = @($names | Where-Object { $_ -match '^[^/]+/[^/]+\.(env|htaccess)$' -and $_ -notmatch '^public/' })
    if ($flat.Count -gt 0) { throw "Suspicious nested root entries: $($flat -join ', ')" }

    Write-Host ("verified : {0} entries, 0 backslash paths, 0 forbidden trees" -f $names.Count)
    return $names
}

if (Test-Path -LiteralPath $out) { Remove-Item -LiteralPath $out -Force }
New-ZipFromTree -SourceDir $stage -ZipPath $out
$zipNames = Assert-ZipEntries -ZipPath $out

$stagedPhotos = @(Get-ChildItem (Join-Path $stage 'public/storage/photos') -Recurse -File -Force)
$zippedPhotos = @($zipNames | Where-Object { $_.StartsWith('public/storage/photos/') })
if ($stagedPhotos.Count -ne $zippedPhotos.Count) {
    throw "Photo count mismatch: staged $($stagedPhotos.Count), zipped $($zippedPhotos.Count)"
}

$files = Get-ChildItem $stage -Recurse -File -Force
Write-Host ''
Write-Host ("zip     : {0}  ({1:N1} MB)" -f $out, ((Get-Item $out).Length / 1MB))
Write-Host ("staged  : {0} files, {1:N1} MB" -f $files.Count, (($files | Measure-Object Length -Sum).Sum / 1MB))
Write-Host ("photos  : {0} files, {1:N1} MB" -f $zippedPhotos.Count, (($stagedPhotos | Measure-Object Length -Sum).Sum / 1MB))
Write-Host "staging dir kept at $stage"