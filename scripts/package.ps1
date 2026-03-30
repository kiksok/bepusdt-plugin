Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = Split-Path -Parent $scriptDir
$releaseDir = Join-Path $repoRoot 'release'
$zipPath = Join-Path $releaseDir 'bepusdt-plugin-xboard.zip'
$checksumPath = Join-Path $releaseDir 'SHA256SUMS.txt'

New-Item -ItemType Directory -Path $releaseDir -Force | Out-Null

$filesToPack = @(
    'Plugin.php',
    'config.json',
    'README.md',
    'README.zh-CN.md'
)

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

$archive = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in $filesToPack) {
        $sourcePath = Join-Path $repoRoot $file
        $entryPath = "Bepusdt/$file"
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive,
            $sourcePath,
            $entryPath,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
}
finally {
    $archive.Dispose()
}

$hash = (Get-FileHash -Path $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
"$hash  bepusdt-plugin-xboard.zip" | Set-Content -Path $checksumPath -Encoding ascii

Write-Output "Package created: $zipPath"
Write-Output "Checksum written: $checksumPath"
