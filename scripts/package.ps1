Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = Split-Path -Parent $scriptDir
$releaseDir = Join-Path $repoRoot 'release'
$tempDir = Join-Path $repoRoot '.tmp-package'
$pluginDir = Join-Path $tempDir 'Bepusdt'
$zipPath = Join-Path $releaseDir 'bepusdt-plugin-xboard.zip'
$checksumPath = Join-Path $releaseDir 'SHA256SUMS.txt'

if (Test-Path $tempDir) {
    Remove-Item $tempDir -Recurse -Force
}

New-Item -ItemType Directory -Path $pluginDir -Force | Out-Null
New-Item -ItemType Directory -Path $releaseDir -Force | Out-Null

$filesToCopy = @(
    'Plugin.php',
    'config.json',
    'README.md',
    'README.zh-CN.md'
)

foreach ($file in $filesToCopy) {
    Copy-Item (Join-Path $repoRoot $file) (Join-Path $pluginDir $file) -Force
}

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Compress-Archive -Path $pluginDir -DestinationPath $zipPath -Force

$hash = (Get-FileHash -Path $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
"$hash  bepusdt-plugin-xboard.zip" | Set-Content -Path $checksumPath -Encoding ascii

Remove-Item $tempDir -Recurse -Force

Write-Output "Package created: $zipPath"
Write-Output "Checksum written: $checksumPath"
