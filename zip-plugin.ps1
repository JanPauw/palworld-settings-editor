param(
    [string]$PluginId = "palworld-settings-editor"
)

# This repo root IS the plugin. Pelican expects the uploaded zip to contain a
# top-level "<plugin-id>/" directory, so stage the files under that folder name
# before compressing.

$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$distPath = Join-Path $repoRoot "dist"
$zipPath = Join-Path $distPath "$PluginId.zip"

$stagingRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("plugin-pack-" + [System.Guid]::NewGuid().ToString("N"))
$stagingPlugin = Join-Path $stagingRoot $PluginId

# Repo-root entries that are tooling/build output, not part of the plugin.
$excludes = @("dist", ".git", ".github", ".gitignore", "zip-plugin.ps1")

try {
    New-Item -ItemType Directory -Path $stagingPlugin -Force | Out-Null

    Get-ChildItem -LiteralPath $repoRoot -Force |
        Where-Object { $excludes -notcontains $_.Name } |
        ForEach-Object { Copy-Item -LiteralPath $_.FullName -Destination $stagingPlugin -Recurse -Force }

    if (-not (Test-Path -LiteralPath $distPath -PathType Container)) {
        New-Item -ItemType Directory -Path $distPath | Out-Null
    }

    if (Test-Path -LiteralPath $zipPath) {
        Remove-Item -LiteralPath $zipPath -Force
    }

    Compress-Archive -Path $stagingPlugin -DestinationPath $zipPath -CompressionLevel Optimal

    Write-Host "Created plugin archive: $zipPath"
}
finally {
    if (Test-Path -LiteralPath $stagingRoot) {
        Remove-Item -LiteralPath $stagingRoot -Recurse -Force
    }
}
