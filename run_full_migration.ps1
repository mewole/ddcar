$start = 101
$chunk = 200
$maxId = 14000
$totalMigrated = 0
$totalMB = 0

Write-Host "Starting batch migration from ID $start to $maxId (chunk size $chunk)..."

while ($start -le $maxId) {
    $uri = "http://ddaccessory.trueddns.com:24330/dd-shop/php/migrate_images.php?start_id=$start&chunk=$chunk"
    try {
        $res = Invoke-RestMethod -Uri $uri -TimeoutSec 120
        $migrated = $res.migrated
        $mb = $res.bytes_saved_mb
        $totalMigrated += $migrated
        $totalMB += $mb
        Write-Host ("IDs {0}..{1}: {2} migrated ({3} MB) | Total: {4} images, {5:N2} MB" -f $res.start_id, $res.end_id, $migrated, $mb, $totalMigrated, $totalMB)
        $start = $res.next_id
        if ($res.done -eq $true) {
            break
        }
    } catch {
        Write-Host "Error at start_id $($start): $_. Retrying in 3s..."
        Start-Sleep -Seconds 3
    }
}

Write-Host "Migration completed! Total migrated: $totalMigrated images, Total saved: $totalMB MB"
