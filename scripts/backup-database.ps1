param(
    [string] $OutputDirectory = "storage/backups",
    [string] $PgDumpPath = ""
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$envPath = Join-Path $projectRoot '.env'

if (-not (Test-Path -LiteralPath $envPath)) {
    throw '.env file was not found.'
}

function Get-EnvValue([string] $Name, [string] $Default = '') {
    $line = Get-Content -LiteralPath $envPath | Where-Object { $_ -match "^$([regex]::Escape($Name))=" } | Select-Object -Last 1
    if (-not $line) { return $Default }
    $value = $line.Substring($line.IndexOf('=') + 1).Trim()
    if (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'"))) {
        return $value.Substring(1, $value.Length - 2)
    }
    return $value
}

if (-not $PgDumpPath) {
    $command = Get-Command pg_dump -ErrorAction SilentlyContinue
    if ($command) {
        $PgDumpPath = $command.Source
    } else {
        $PgDumpPath = Get-ChildItem -LiteralPath 'C:\Program Files\PostgreSQL' -Filter pg_dump.exe -Recurse -ErrorAction SilentlyContinue |
            Sort-Object FullName -Descending |
            Select-Object -First 1 -ExpandProperty FullName
    }
}

if (-not $PgDumpPath -or -not (Test-Path -LiteralPath $PgDumpPath)) {
    throw 'pg_dump was not found. Pass its full path with -PgDumpPath.'
}

$outputRoot = [System.IO.Path]::GetFullPath((Join-Path $projectRoot $OutputDirectory))
New-Item -ItemType Directory -Path $outputRoot -Force | Out-Null
$stamp = (Get-Date).ToUniversalTime().ToString('yyyyMMdd-HHmmss')
$database = Get-EnvValue 'DB_DATABASE'
$databaseHost = Get-EnvValue 'DB_HOST' '127.0.0.1'
$databasePort = Get-EnvValue 'DB_PORT' '5432'
$databaseUser = Get-EnvValue 'DB_USERNAME' 'postgres'
$outputFile = Join-Path $outputRoot "$database-$stamp.dump"

$previousPassword = $env:PGPASSWORD
try {
    $env:PGPASSWORD = Get-EnvValue 'DB_PASSWORD'
    & $PgDumpPath `
        -h $databaseHost `
        -p $databasePort `
        -U $databaseUser `
        -d $database `
        -F custom `
        -Z 9 `
        --no-owner `
        --no-privileges `
        -f $outputFile
    if ($LASTEXITCODE -ne 0) { throw "pg_dump failed with exit code $LASTEXITCODE." }
} finally {
    $env:PGPASSWORD = $previousPassword
}

$hash = (Get-FileHash -LiteralPath $outputFile -Algorithm SHA256).Hash.ToLowerInvariant()
Set-Content -LiteralPath "$outputFile.sha256" -Value "$hash  $([System.IO.Path]::GetFileName($outputFile))" -Encoding ascii
Write-Output $outputFile
