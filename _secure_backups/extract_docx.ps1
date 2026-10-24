Add-Type -AssemblyName System.IO.Compression.FileSystem
$targetFile = (Get-ChildItem -Path "C:\Users\user\Downloads" -Filter "*24-10-2026*.docx" | Sort-Object LastWriteTime -Descending | Select-Object -First 1).FullName
Write-Host "Target file: $targetFile"
$zip = [System.IO.Compression.ZipFile]::OpenRead($targetFile)
$entry = $zip.GetEntry("word/document.xml")
$stream = $entry.Open()
$reader = New-Object System.IO.StreamReader($stream)
$xmlText = $reader.ReadToEnd()
$reader.Close()
$stream.Close()
$zip.Dispose()

[xml]$doc = $xmlText
$ns = New-Object System.Xml.XmlNamespaceManager($doc.NameTable)
$ns.AddNamespace("w", "http://schemas.openxmlformats.org/wordprocessingml/2006/main")

$paras = $doc.SelectNodes("//w:p", $ns)
$lines = @()
foreach ($p in $paras) {
    $tNodes = $p.SelectNodes(".//w:t", $ns)
    $tArr = @()
    foreach ($t in $tNodes) {
        $tArr += $t.InnerText
    }
    $text = $tArr -join ""
    if ($text) {
        $lines += $text
    }
}

$lines -join "`r`n" | Out-File -FilePath "c:\ddcar\_secure_backups\latest_all_dev_24-10-2026.txt" -Encoding utf8
Write-Host "Done! Extracted $($lines.Count) lines."
