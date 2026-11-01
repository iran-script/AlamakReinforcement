param(
    [Parameter(Mandatory = $true)]
    [string] $SourceRepository
)

$ErrorActionPreference = 'Stop'
$sourceRoot = [System.IO.Path]::GetFullPath($SourceRepository)
$destinationRoot = [System.IO.Path]::GetFullPath($PSScriptRoot)
$checksumFile = Join-Path $sourceRoot 'SHA256SUMS.txt'

if (-not (Test-Path -LiteralPath $checksumFile -PathType Leaf)) {
    throw "Missing SHA256SUMS.txt in source repository: $sourceRoot"
}

$entries = @{}
foreach ($line in [System.IO.File]::ReadLines($checksumFile)) {
    if ([string]::IsNullOrWhiteSpace($line)) { continue }
    if ($line -notmatch '^([A-Fa-f0-9]{64})\s+\*(.+)$') {
        throw "Invalid checksum line: $line"
    }

    $relativePath = $Matches[2].Replace('/', [System.IO.Path]::DirectorySeparatorChar)
    if ([System.IO.Path]::IsPathRooted($relativePath) -or $relativePath -match '(^|[\\])\.\.([\\]|$)') {
        throw "Unsafe relative path in checksum manifest: $relativePath"
    }
    $entries[$relativePath] = $Matches[1].ToUpperInvariant()
}

if ($entries.Count -eq 0) { throw 'The source checksum manifest contains no files.' }

foreach ($relativePath in $entries.Keys) {
    $sourceFile = Join-Path $sourceRoot $relativePath
    $destinationFile = Join-Path $destinationRoot $relativePath
    if (-not (Test-Path -LiteralPath $sourceFile -PathType Leaf)) {
        throw "Manifest file is missing: $relativePath"
    }

    $sourceHash = (Get-FileHash -LiteralPath $sourceFile -Algorithm SHA256).Hash
    if ($sourceHash -ne $entries[$relativePath]) {
        throw "SHA-256 mismatch in transfer package: $relativePath"
    }

    if (Test-Path -LiteralPath $destinationFile -PathType Leaf) {
        $destinationHash = (Get-FileHash -LiteralPath $destinationFile -Algorithm SHA256).Hash
        if ($destinationHash -ne $sourceHash) {
            throw "Refusing to overwrite a different repository file: $relativePath"
        }
        continue
    }

    $destinationDirectory = Split-Path -Parent $destinationFile
    New-Item -ItemType Directory -Path $destinationDirectory -Force | Out-Null
    Copy-Item -LiteralPath $sourceFile -Destination $destinationFile
}

$sumPath = Join-Path $destinationRoot 'SHA256SUMS.txt'
$existing = @{}
if (Test-Path -LiteralPath $sumPath -PathType Leaf) {
    foreach ($line in [System.IO.File]::ReadLines($sumPath)) {
        if ($line -match '^([A-Fa-f0-9]{64})\s+\*(.+)$') {
            $existing[$Matches[2].Replace('/', [System.IO.Path]::DirectorySeparatorChar)] = $Matches[1].ToUpperInvariant()
        }
    }
}

foreach ($relativePath in $entries.Keys) {
    if ($existing.ContainsKey($relativePath) -and $existing[$relativePath] -ne $entries[$relativePath]) {
        throw "Existing checksum manifest conflicts for: $relativePath"
    }
    $existing[$relativePath] = $entries[$relativePath]
}

$lines = foreach ($relativePath in ($existing.Keys | Sort-Object)) {
    "$($existing[$relativePath]) *$($relativePath.Replace([System.IO.Path]::DirectorySeparatorChar, '/'))"
}
[System.IO.File]::WriteAllLines($sumPath, $lines, [System.Text.UTF8Encoding]::new($false))
Write-Output "Verified/imported $($entries.Count) Maven repository files."
