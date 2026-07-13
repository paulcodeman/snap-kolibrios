# Snap! 5.4 download & setup
# Run this script to download Snap! 5.4 locally

$snapUrl = "https://github.com/jmoenig/Snap/archive/refs/tags/v5.4.0.zip"
$zipPath = "$env:TEMP\snap-5.4.zip"
$extractPath = "$env:TEMP\snap-5.4"
$targetDir = Split-Path -Parent $PSCommandPath

Write-Host "Downloading Snap! 5.4 from GitHub..." -ForegroundColor Cyan

try {
    $wc = [System.Net.WebClient]::new()
    $wc.DownloadFile($snapUrl, $zipPath)
    Write-Host "OK: $((Get-Item $zipPath).Length / 1KB) KB" -ForegroundColor Green
} catch {
    Write-Host "Download failed: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "Manual setup:" -ForegroundColor Yellow
    Write-Host "1. Download from: https://github.com/jmoenig/Snap/archive/refs/tags/v5.4.0.zip"
    Write-Host "2. Extract contents to: $targetDir"
    Write-Host "3. Make sure snap.html is at: $targetDir\snap.html"
    exit 1
}

Write-Host "Extracting..." -ForegroundColor Cyan
if (Test-Path $extractPath) { Remove-Item -Recurse -Force $extractPath }
Expand-Archive -Path $zipPath -DestinationPath $extractPath

# Copy only needed files to snap/ directory
Copy-Item -Recurse -Force "$extractPath\Snap-5.4.0\*" $targetDir

# Cleanup
Remove-Item -Recurse -Force $extractPath
Remove-Item -Force $zipPath

Write-Host "Done! Snap! 5.4 installed to: $targetDir" -ForegroundColor Green
Write-Host "Open editor.php to start building!" -ForegroundColor Cyan
