$baseUrl = 'http://ddaccessory.trueddns.com:24330/dd-shop/php'

$tests = @(
    @{ file = 'get_bill.php'; label = 'get_bill'; body = @{ start = 0 } },
    @{ file = 'get_credit.php'; label = 'get_credit'; body = @{ val = '' } },
    @{ file = 'get_sales_summary.php'; label = 'sales_day'; body = @{ mode = 'day'; date = (Get-Date -Format 'yyyy-MM-dd') } },
    @{ file = 'get_sales_summary.php'; label = 'sales_month'; body = @{ mode = 'month'; month = (Get-Date -Format 'yyyy-MM') } },
    @{ file = 'get_sales_summary.php'; label = 'sales_year'; body = @{ mode = 'year'; year = (Get-Date -Format 'yyyy') } },
    @{ file = 'get_sales_analytics.php'; label = 'sales_analytics'; body = @{ days = 30 } },
    @{ file = 'get_stock_adjustments.php'; label = 'adjustments'; body = @{ start = 0 } },
    @{ file = 'get_import.php'; label = 'import'; body = @{ start = 0 } },
    @{ file = 'get_transfer.php'; label = 'transfer_in'; body = @{ start = 0 } },
    @{ file = 'get_transfer_to.php'; label = 'transfer_records'; body = @{ start = 0 } },
    @{ file = 'get_export.php'; label = 'export'; body = @{ start = 0 } },
    @{ file = 'get_low_stock.php'; label = 'low_stock_reorder'; body = @{ mode = 'reorder' } },
    @{ file = 'get_low_stock.php'; label = 'low_stock_zero'; body = @{ mode = 'zero' } },
    @{ file = 'get_shift_bill.php'; label = 'shift_bill'; body = @{ date = (Get-Date -Format 'yyyy-MM-dd') } }
)

foreach ($t in $tests) {
    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    $url = "$baseUrl/$($t.file)"
    try {
        $res = Invoke-WebRequest -Uri $url -Method Post -Body $t.body -UseBasicParsing -TimeoutSec 30
        $sw.Stop()
        $len = $res.Content.Length
        $sample = $res.Content.Substring(0, [Math]::Min(120, $len)).Replace("`r", '').Replace("`n", ' ')
        Write-Host ("SUCCESS [{0}] {1}ms (Len={2}) -> {3}" -f $t.label, $sw.ElapsedMilliseconds, $len, $sample)
    } catch {
        $sw.Stop()
        Write-Host ("FAILED  [{0}] {1}ms -> {2}" -f $t.label, $sw.ElapsedMilliseconds, $_.Exception.Message)
    }
}
