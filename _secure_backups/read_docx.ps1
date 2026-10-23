Add-Type -AssemblyName System.IO.Compression.FileSystem
$docx = "C:\Users\user\Downloads\รายงานบัคแอพสต๊อค_23-10-2026 (1).docx"
$zip = [System.IO.Compression.ZipFile]::OpenRead($docx)
$entry = $zip.GetEntry("word/document.xml")
$stream = $entry.Open()
$reader = New-Object System.IO.StreamReader($stream)
$xmlText = $reader.ReadToEnd()
$reader.Close()
$stream.Close()
$zip.Dispose()

[xml]$xml = $xmlText
$ns = New-Object System.Xml.XmlNamespaceManager($xml.NameTable)
$ns.AddNamespace("w", "http://schemas.openxmlformats.org/wordprocessingml/2006/main")
$paragraphs = $xml.SelectNodes("//w:p", $ns)
$output = foreach ($p in $paragraphs) {
    $text = ($p.SelectNodes(".//w:t", $ns) | ForEach-Object { $_.InnerText }) -join ""
    if ($text.Trim() -ne "") {
        $text
    }
}
$output | Out-File -FilePath "C:\ddcar\_secure_backups\report_text.txt" -Encoding utf8
Write-Output "Done! Paragraphs: $($paragraphs.Count)"
