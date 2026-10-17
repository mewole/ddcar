param(
    [string]$NasHost = ""
)

$files = @(
    "web_customer.html",
    "web_admin_customer.html",
    "web_select.html",
    "web_stock.html",
    "web_sale.html",
    "web_import.html",
    "web_export.html",
    "web_mng_bill.html",
    "web_credit.html",
    "web_mng_sale.html",
    "web_mng_bell.html",
    "web_qrprint.html",
    "web_mng_import.html",
    "web_mng_transfer.html",
    "web_mng_import_warehouse.html",
    "web_mng_export.html",
    "web_admin_user.html",
    "web_transfer_in.html",
    "web_login.html",
    "web_folder.html",
    "select_folder.html",
    "web_catalog.html",
    "stock.html",
    "js\navbar.js",
    "js\scanqr.js",
    "css\untitled.css",
    "css\web_folder.css",
    "php\folder_helper.php",
    "php\get_folder_web.php",
    "php\get_folder.php",
    "php\search_prod.php",
    "php\check_amt_prod.php",
    "php\get_sales_summary.php",
    "php\get_shift_bill_web.php",
    "php\delete_place.php",
    "php\get_supplier_prices.php",
    "php\stock_in.php",
    "img\logo.jpg"
)

# Determine Source Directory
if (Test-Path "$PSScriptRoot\www") {
    $srcDir = "$PSScriptRoot\www"
} elseif (Test-Path "c:\ddcar\www") {
    $srcDir = "c:\ddcar\www"
} elseif (Test-Path "d:\MewP\ddcar\www") {
    $srcDir = "d:\MewP\ddcar\www"
} else {
    Write-Error "Source directory not found!"
    exit 1
}

# Determine Destination Directory (LAN vs Tailscale)
if ($NasHost) {
    $nasIp = $NasHost
} elseif (Test-Path "\\192.168.1.51\Web\dd-shop") {
    $nasIp = "192.168.1.51"
} elseif (Test-Path "\\100.77.57.16\Web\dd-shop") {
    $nasIp = "100.77.57.16"
} else {
    # Default to Tailscale IP if outside local LAN
    $nasIp = "100.77.57.16"
}

$dstDir = "\\$nasIp\Web\dd-shop"
Write-Host "Source:      $srcDir"
Write-Host "Destination: $dstDir"

if (-not (Test-Path $dstDir)) {
    Write-Warning "Cannot access destination: $dstDir"
    Write-Host "Tip: If connecting via Tailscale, ensure credentials for $nasIp are added:"
    Write-Host "     cmdkey /add:$nasIp /user:admin /pass:<PASSWORD>"
}

$backupDir = Join-Path $dstDir "_backup_update_customer"
if (-not (Test-Path $backupDir)) {
    try {
        New-Item -ItemType Directory -Path $backupDir -Force -ErrorAction Stop | Out-Null
        Write-Host "Created backup folder: $backupDir"
    } catch {
        Write-Warning "Could not create backup folder: $($_.Exception.Message)"
    }
}

$successCount = 0
$failCount = 0

foreach ($f in $files) {
    $src = Join-Path $srcDir $f
    $dst = Join-Path $dstDir $f
    $bak = Join-Path $backupDir $f
    
    try {
        $parentDir = Split-Path $dst -Parent
        if (-not (Test-Path $parentDir)) {
            New-Item -ItemType Directory -Path $parentDir -Force | Out-Null
        }
        $parentBakDir = Split-Path $bak -Parent
        if (-not (Test-Path $parentBakDir)) {
            New-Item -ItemType Directory -Path $parentBakDir -Force | Out-Null
        }
        if (Test-Path $dst) {
            Copy-Item -Path $dst -Destination $bak -Force
        }
        
        Copy-Item -Path $src -Destination $dst -Force
        
        $srcLen = (Get-Item $src).Length
        $dstLen = (Get-Item $dst).Length
        
        if ($srcLen -eq $dstLen) {
            Write-Host "SUCCESS: $f ($srcLen bytes)"
            $successCount++
        } else {
            Write-Host "SIZE MISMATCH: $f src=$srcLen dst=$dstLen"
            $failCount++
        }
    } catch {
        Write-Host "ERROR on $f : $($_.Exception.Message)"
        $failCount++
    }
}

Write-Host "`nResult: $successCount succeeded, $failCount failed."
