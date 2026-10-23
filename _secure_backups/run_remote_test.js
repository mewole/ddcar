const fs = require('fs');
const http = require('http');

const phpCode = `<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . "/db.php";

$q_idx = mysqli_query($conn, "SHOW INDEX FROM sale_order_item");
$indexes = [];
while ($r = mysqli_fetch_assoc($q_idx)) {
    $indexes[] = $r['Key_name'] . '(' . $r['Column_name'] . ')';
}

echo json_encode(array_values(array_unique($indexes)));
`;

fs.writeFileSync('//100.77.57.16/Web/dd-shop/php/test_db_debug.php', phpCode);

http.get('http://100.77.57.16:8083/dd-shop/php/test_db_debug.php', (res) => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    console.log('Response:', d);
    try {
      fs.unlinkSync('//100.77.57.16/Web/dd-shop/php/test_db_debug.php');
    } catch(e){}
  });
}).on('error', err => console.log('Err:', err.message));
