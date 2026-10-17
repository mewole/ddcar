param(
    [string]$NasHost = ""
)

$files = @(
    "web_warehouse.html",
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
    "add_prod.html",
    "edit_prod.html",
    "web_catalog.html",
    "stock.html",
    "js\navbar.js",
    "js\scanqr.js",
    "js\capacitor-bridge.js",
    "index.html",
    "login.html",
    "select_cus.html",
    "show_pic.html",
    "show_credit.html",
    "sale_info.html",
    "bill_info.html",
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
    "php\create_transfer.php",
    "php\get_pending_transfers.php",
    "php\confirm_transfer.php",
    "php\reject_transfer.php",
    "php\get_transfer_to.php",
    "php\get_transfer_to_info.php",
    "php\get_sale.php",
    "php\add_prod.php",
    "php\img_helper.php",
    "php\migrate_images.php",
    "php\rename_prod_web.php",
    "php\rename_prod.php",
    "php\get_low_stock.php",
    "php\get_sales_analytics.php",
    "php\backup_db.php",
    "img\logo.jpg"
)

$srcDir = if (Test-Path "$PSScriptRoot\www") { "$PSScriptRoot\www" } elseif (Test-Path "c:\ddcar\www") { "c:\ddcar\www" } else { "d:\MewP\ddcar\www" }

# Check target destinations (LAN IP or Tailscale IP)
$candidateDirs = @(
    "\\192.168.1.51\Web\dd-shop",
    "\\100.77.57.16\Web\dd-shop"
)

$dstDir = $null
foreach ($dir in $candidateDirs) {
    if (Test-Path $dir) {
        $dstDir = $dir
        break
    }
}

if (-not $dstDir) {
    $dstDir = $candidateDirs[0] # Default fallback
    Write-Warning "Could not automatically reach candidate share paths. Trying $dstDir..."
} else {
    Write-Host "Connected destination: $dstDir"
}

$backupDir = Join-Path $dstDir "_backup_update_customer"
if (-not (Test-Path $backupDir)) {
    try {
        New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
        Write-Host "Created backup folder: $backupDir"
    } catch {
        Write-Warning "Could not create backup directory on destination: $($_.Exception.Message)"
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

# Reset PHP OPcache on server if accessible
try {
    $resetPhp = '<?php if (function_exists("opcache_reset")) { opcache_reset(); echo "OK"; } ?>'
    $tmpReset = Join-Path $dstDir "php\_tmp_opcache_reset.php"
    [System.IO.File]::WriteAllText($tmpReset, $resetPhp, [System.Text.Encoding]::UTF8)
    $null = Invoke-RestMethod -Uri "http://100.77.57.16:8083/dd-shop/php/_tmp_opcache_reset.php" -TimeoutSec 3 -ErrorAction SilentlyContinue
    Remove-Item $tmpReset -Force -ErrorAction SilentlyContinue
    Write-Host "PHP OPcache successfully reset on server."
} catch {
    # Ignore if offline
}
