# ==============================================================================
# DD Accessories (DDCAR) - Automated APK Release & Deploy Script
# Usage:
#   .\release_apk.ps1
#   .\release_apk.ps1 -Version "2.0.2"
#   npm run release
# ==============================================================================
param(
    [string]$Version = "",
    [string]$NasPath = "\\192.168.1.51\Web\dd-shop",
    [string]$PublicDownloadUrl = "http://ddaccessory.trueddns.com:24330/dd-shop/ddcar-latest.apk",
    [string]$ApiUrl = "http://ddaccessory.trueddns.com:24330/dd-shop/php/set_lastV.php"
)

$ErrorActionPreference = "Stop"
$root = $PSScriptRoot

Write-Host "`n========================================================" -ForegroundColor Cyan
Write-Host "   DDCAR MOBILE APP - 1-CLICK RELEASE SYSTEM           " -ForegroundColor Cyan
Write-Host "========================================================`n" -ForegroundColor Cyan

# 1. Determine Version and Update build.gradle
$gradlePath = Join-Path $root "android\app\build.gradle"
$gradleContent = Get-Content $gradlePath -Raw

$currentCode = 1
$currentVer = "1.0"
if ($gradleContent -match 'versionCode\s+(\d+)') {
    $currentCode = [int]$matches[1]
}
if ($gradleContent -match 'versionName\s+"([^"]+)"') {
    $currentVer = $matches[1]
}

if ([string]::IsNullOrWhiteSpace($Version)) {
    Write-Host "Current App Version in build.gradle: " -NoNewline
    Write-Host "$currentVer (Code: $currentCode)" -ForegroundColor Yellow
    try {
        $promptVer = Read-Host "Enter release version [Press Enter to keep $currentVer]"
    } catch {
        $promptVer = ""
    }
    if ([string]::IsNullOrWhiteSpace($promptVer)) {
        $Version = $currentVer
    } else {
        $Version = $promptVer.Trim()
    }
}

$newCode = $currentCode + 1
Write-Host "`n[1/5] Updating version info to v$Version (buildCode: $newCode)..." -ForegroundColor Green

$targetCode = "versionCode $currentCode"
$replacementCode = "versionCode $newCode"
$updatedGradle = $gradleContent.Replace($targetCode, $replacementCode)

$targetVer = 'versionName "' + $currentVer + '"'
$replacementVer = 'versionName "' + $Version + '"'
$updatedGradle = $updatedGradle.Replace($targetVer, $replacementVer)

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($gradlePath, $updatedGradle, $utf8NoBom)

# 2. Capacitor Sync
Write-Host "`n[2/5] Syncing web assets to Android platform (npx cap sync android)..." -ForegroundColor Green
Set-Location $root
& npx.cmd cap sync android
if ($LASTEXITCODE -ne 0) {
    Write-Error "Capacitor sync failed!"
}

# 3. Build APK with Gradle
Write-Host "`n[3/5] Building Android APK (assembleDebug)..." -ForegroundColor Green
Set-Location (Join-Path $root "android")
& .\gradlew.bat assembleDebug
if ($LASTEXITCODE -ne 0) {
    Set-Location $root
    Write-Error "Gradle build failed!"
}
Set-Location $root

$apkSource = Join-Path $root "android\app\build\outputs\apk\debug\app-debug.apk"
if (-not (Test-Path $apkSource)) {
    Write-Error "APK file not found at: $apkSource"
}
$apkItem = Get-Item $apkSource
$apkSizeMB = [math]::Round($apkItem.Length / 1MB, 2)
Write-Host "[SUCCESS] APK Build successful! Size: $apkSizeMB MB" -ForegroundColor Green

# 4. Copy to NAS / Web Server
Write-Host "`n[4/5] Deploying APK and API to Web Server/NAS..." -ForegroundColor Green
$nasAvailable = Test-Path $NasPath
if ($nasAvailable) {
    # Ensure php directory exists on NAS
    $nasPhpDir = Join-Path $NasPath "php"
    if (-not (Test-Path $nasPhpDir)) {
        New-Item -ItemType Directory -Path $nasPhpDir -Force | Out-Null
    }

    # Copy set_lastV.php & db.php to NAS
    $phpSource = Join-Path $root "www\php\set_lastV.php"
    if (Test-Path $phpSource) {
        Copy-Item -Path $phpSource -Destination (Join-Path $nasPhpDir "set_lastV.php") -Force
        Write-Host "   -> Updated set_lastV.php on NAS" -ForegroundColor Gray
    }
    $dbSource = Join-Path $root "www\php\db.php"
    if (Test-Path $dbSource) {
        Copy-Item -Path $dbSource -Destination (Join-Path $nasPhpDir "db.php") -Force
        Write-Host "   -> Updated db.php on NAS" -ForegroundColor Gray
    }

    # Copy updated HTML and JS files to NAS
    Get-ChildItem -Path (Join-Path $root "www") -Filter "*.html" | ForEach-Object {
        Copy-Item -Path $_.FullName -Destination (Join-Path $NasPath $_.Name) -Force
    }
    $nasJsDir = Join-Path $NasPath "js"
    if (-not (Test-Path $nasJsDir)) { New-Item -ItemType Directory -Path $nasJsDir -Force | Out-Null }
    Get-ChildItem -Path (Join-Path $root "www\js") -Filter "*.js" | ForEach-Object {
        Copy-Item -Path $_.FullName -Destination (Join-Path $nasJsDir $_.Name) -Force
    }
    Write-Host "   -> Synced HTML & JS web assets to NAS" -ForegroundColor Gray

    # Copy APK to NAS as ddcar-latest.apk (Standard download link)
    $destLatest = Join-Path $NasPath "ddcar-latest.apk"
    Copy-Item -Path $apkSource -Destination $destLatest -Force
    Write-Host "   -> Deployed: $destLatest" -ForegroundColor Cyan

    # Copy versioned backup (e.g. ddcar-v2.0.2.apk)
    $destVersioned = Join-Path $NasPath "ddcar-v$Version.apk"
    Copy-Item -Path $apkSource -Destination $destVersioned -Force
    Write-Host "   -> Backup:   $destVersioned" -ForegroundColor Gray
} else {
    Write-Host "[WARN] NAS path $NasPath not accessible. Skipping SMB copy." -ForegroundColor Yellow
}

# 5. Update Database Version via set_lastV.php
Write-Host "`n[5/5] Updating app version in database..." -ForegroundColor Green
try {
    $apiUrlWithParams = "$ApiUrl`?version=$Version&url=$([System.Uri]::EscapeDataString($PublicDownloadUrl))&user_id=1&user_role=%E0%B9%80%E0%B8%88%E0%B9%89%E0%B8%B2%E0%B8%82%E0%B8%AD%E0%B8%87%E0%B8%A3%E0%B9%89%E0%B8%B2%E0%B8%99"
    $response = Invoke-RestMethod -Uri $apiUrlWithParams -Method Get -TimeoutSec 10
    if ($response.status -eq "success") {
        Write-Host "[SUCCESS] Database version updated successfully to v$Version!" -ForegroundColor Green
    } else {
        Write-Host "[WARN] API response: $($response | ConvertTo-Json -Compress)" -ForegroundColor Yellow
    }
} catch {
    Write-Host "[WARN] Could not contact API at ${ApiUrl}: $($_.Exception.Message)" -ForegroundColor Yellow
    Write-Host "   (Note: You can run ${ApiUrl}?version=$Version manually)" -ForegroundColor Gray
}

Write-Host "`n========================================================" -ForegroundColor Cyan
Write-Host "   RELEASE v$Version COMPLETED SUCCESSFULLY!           " -ForegroundColor Green
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "APK Local:    $apkSource"
Write-Host "Download URL: $PublicDownloadUrl"
Write-Host "App Check:    In-app update and update.html will now serve v$Version" -ForegroundColor Yellow
Write-Host "========================================================`n" -ForegroundColor Cyan
