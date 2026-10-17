$urls = @(
    "http://ddaccessory.trueddns.com:24330/dd-shop/web_warehouse.html",
    "http://ddaccessory.trueddns.com:24330/dd-shop/web_customer.html",
    "http://ddaccessory.trueddns.com:24330/dd-shop/web_select.html",
    "http://ddaccessory.trueddns.com:24330/dd-shop/web_stock.html",
    "http://ddaccessory.trueddns.com:24330/dd-shop/web_sale.html",
    "http://ddaccessory.trueddns.com:24330/dd-shop/web_import.html",
    "http://ddaccessory.trueddns.com:24330/dd-shop/web_export.html",
    "http://ddaccessory.trueddns.com:24330/dd-shop/web_mng_bill.html",
    "http://ddaccessory.trueddns.com:24330/dd-shop/web_credit.html",
    "http://ddaccessory.trueddns.com:24330/dd-shop/web_mng_sale.html",
    "http://ddaccessory.trueddns.com:24330/dd-shop/web_mng_bell.html",
    "http://ddaccessory.trueddns.com:24330/dd-shop/web_qrprint.html",
    "http://ddaccessory.trueddns.com:24330/dd-shop/web_login.html",
    "http://ddaccessory.trueddns.com:24330/dd-shop/js/navbar.js",
    "http://ddaccessory.trueddns.com:24330/dd-shop/css/untitled.css"
)

foreach ($u in $urls) {
    try {
        $res = Invoke-WebRequest -Uri $u -UseBasicParsing -TimeoutSec 10
        Write-Host "$u -> Status: $($res.StatusCode), Length: $($res.Content.Length) bytes"
    } catch {
        Write-Host "$u -> FAILED: $($_.Exception.Message)"
    }
}

Write-Host "`n--- Checking Live Web Content Features ---"
$htmlContent = (Invoke-WebRequest -Uri "http://ddaccessory.trueddns.com:24330/dd-shop/web_customer.html" -UseBasicParsing).Content
Write-Host "Contains 'd-lg-none':" ($htmlContent.Contains("d-lg-none"))
Write-Host "Contains 'd-lg-block':" ($htmlContent.Contains("d-lg-block"))
Write-Host "Contains 'overflow-x: hidden':" ($htmlContent.Contains("overflow-x: hidden"))
Write-Host "Contains 'untitled.css?v=2.0':" ($htmlContent.Contains("untitled.css?v=2.0"))

$cssContent = (Invoke-WebRequest -Uri "http://ddaccessory.trueddns.com:24330/dd-shop/css/untitled.css" -UseBasicParsing).Content
Write-Host "CSS Contains 'max-width: 100vw':" ($cssContent.Contains("max-width: 100vw"))
Write-Host "CSS Contains 'overflow-x: hidden':" ($cssContent.Contains("overflow-x: hidden"))
Write-Host "CSS Contains 'calc(100% - 56px)':" ($cssContent.Contains("calc(100% - 56px)"))

