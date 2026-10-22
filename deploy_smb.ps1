param(
    [string]$NasHost = "",
    [switch]$All
)

$srcDir = if (Test-Path "$PSScriptRoot\www") { "$PSScriptRoot\www" } elseif (Test-Path "c:\ddcar\www") { "c:\ddcar\www" } else { "d:\MewP\ddcar\www" }

$files = @(
    # Core Web Pages
    "web_warehouse.html",
    "web_customer.html",
    "web_admin_customer.html",
    "web_select.html",
    "web_stock.html",
    "web_bundle.html",
    "web_platform_mapping.html",
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
    "web_mng_adjust.html",
    "web_admin_user.html",
    "web_transfer_in.html",
    "web_login.html",
    "web_folder.html",
    "web_catalog.html",
    "bill_info.html",
    "sale_info.html",
    "select_folder.html",
    "add_prod.html",
    "edit_prod.html",
    "stock.html",
    "index.html",
    "login.html",
    "select_cus.html",
    "show_pic.html",
    "show_credit.html",

    # JavaScript Components
    "js\navbar.js",
    "js\hover_preview.js",
    "js\scanqr.js",
    "js\capacitor-bridge.js",
    "search_trans.js",

    # Stylesheets
    "css\untitled.css",
    "css\web_folder.css",
    "css\hover_preview.css",

    # Core Backend PHP Handlers
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
    "php\get_transfer.php",
    "php\get_transfer_info.php",
    "php\get_sale.php",
    "php\save_order.php",
    "php\add_prod.php",
    "php\img_helper.php",
    "php\migrate_images.php",
    "php\rename_prod_web.php",
    "php\rename_prod.php",
    "php\get_low_stock.php",
    "php\get_sales_analytics.php",
    "php\backup_db.php",
    "php\get_stock_adjustments.php",
    "php\save_stock_adjustment.php",
    "php\migrate_adjustment.php",
    "php\update_bell_status.php",
    "php\get_status_prod.php",
    "php\get_import.php",
    "php\get_import_info.php",
    "php\get_credit.php",
    "php\get_credit_info.php",
    "php\pay_credit.php",
    "php\get_bill.php",
    "php\get_bill_info.php",
    "php\rename_bill.php",
    "php\delete_bill.php",
    "php\restore_product.php",
    "php\save_credit.php",
    "php\check_version.php",
    "php\search_img.php",
    "php\search_amount.php",
    "php\search_cusprice.php",
    "php\get_autoprod.php",
    "php\setup_status_ext.php",
    "php\add_performance_indexes.php",
    "php\platform_mapping_init.php",
    "php\get_platform_channels.php",
    "php\save_platform_channel.php",
    "php\get_platform_mappings.php",
    "php\save_platform_mapping.php",
    "php\delete_platform_mapping.php",
    "php\import_platform_skus.php",
    "php\search_stock_autocomplete.php",
    "php\save_platform_sku.php",
    "php\get_bundle_recipes.php",
    "php\save_bundle_recipe.php",
    "php\db.php",

    # Assets
    "img\logo.jpg"
)

if ($All) {
    Write-Host "Mode: Sync all active web files from www/" -ForegroundColor Cyan
    $activeWww = Get-ChildItem -Path $srcDir -Recurse -File | Where-Object {
        $_.FullName -notmatch '\\(android|node_modules|\.git|_backup.*)\\' -and
        $_.Name -notmatch '(_bk\.html$|_old|\.bak|\.tmp|\.txt$|test_.*\.php$)'
    } | ForEach-Object {
        $_.FullName.Substring($srcDir.Length).TrimStart('\', '/')
    }
    $files = $activeWww | Select-Object -Unique
}

# Check target destinations (LAN IP or Tailscale IP or Custom NasHost)
$candidateDirs = @()
if ($NasHost) {
    $candidateDirs += "\\$NasHost\Web\dd-shop"
}
$candidateDirs += @(
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
    $dstDir = $candidateDirs[0]
    Write-Warning "Could not automatically reach candidate share paths. Trying $dstDir..."
} else {
    Write-Host "Connected destination: $dstDir" -ForegroundColor Green
}

$backupDir = Join-Path $dstDir "_backup_deploy"
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
$skipCount = 0

foreach ($f in $files) {
    $src = Join-Path $srcDir $f
    $dst = Join-Path $dstDir $f
    $bak = Join-Path $backupDir $f
    
    if (-not (Test-Path $src)) {
        Write-Host "SKIP (not found locally): $f" -ForegroundColor Yellow
        $skipCount++
        continue
    }

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
            Write-Host "SUCCESS: $f ($srcLen bytes)" -ForegroundColor Green
            $successCount++
        } else {
            Write-Host "SIZE MISMATCH: $f src=$srcLen dst=$dstLen" -ForegroundColor Red
            $failCount++
        }
    } catch {
        Write-Host "ERROR on $f : $($_.Exception.Message)" -ForegroundColor Red
        $failCount++
    }
}

Write-Host "`nResult: $successCount succeeded, $failCount failed, $skipCount skipped." -ForegroundColor Cyan

# Reset PHP OPcache on server if accessible
try {
    $resetPhp = '<?php if (function_exists("opcache_reset")) { opcache_reset(); echo "OK"; } ?>'
    $tmpReset = Join-Path $dstDir "php\_tmp_opcache_reset.php"
    [System.IO.File]::WriteAllText($tmpReset, $resetPhp, [System.Text.Encoding]::UTF8)
    $null = Invoke-RestMethod -Uri "http://100.77.57.16:8083/dd-shop/php/_tmp_opcache_reset.php" -TimeoutSec 3 -ErrorAction SilentlyContinue
    Remove-Item $tmpReset -Force -ErrorAction SilentlyContinue
    Write-Host "PHP OPcache successfully reset on server." -ForegroundColor Green
} catch {
    # Ignore if offline
}
