const fs = require('fs');
const http = require('http');

const phpCode = `<?php
require_once __DIR__ . "/db.php";

$q = mysqli_query($conn, "SELECT channel_code, COUNT(*) as c FROM platform_sku_mapping GROUP BY channel_code");
$rows = [];
while ($r = mysqli_fetch_assoc($q)) $rows[] = $r;
echo json_encode($rows);
`;

fs.writeFileSync('//100.77.57.16/Web/dd-shop/php/test_ch_codes.php', phpCode);

http.get('http://100.77.57.16:8083/dd-shop/php/test_ch_codes.php', (res) => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    console.log(d);
    try { fs.unlinkSync('//100.77.57.16/Web/dd-shop/php/test_ch_codes.php'); } catch(e){}
  });
});
