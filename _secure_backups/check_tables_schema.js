const fs = require('fs');
const http = require('http');

const debugScript = `<?php
require_once "db.php";
function desc($t, $c) {
    $r = mysqli_query($c, "DESCRIBE " . $t);
    $f = [];
    if ($r) {
        while($w = mysqli_fetch_assoc($r)) $f[] = $w['Field'];
    }
    echo $t . ": " . implode(", ", $f) . "\\n";
}
desc("shop", $conn);
desc("warehouse", $conn);
desc("product", $conn);
?>`;

fs.writeFileSync('//100.77.57.16/Web/dd-shop/php/_debug_schema.php', debugScript, 'utf8');

http.get('http://100.77.57.16:8083/dd-shop/php/_debug_schema.php', (res) => {
    let data = '';
    res.on('data', c => data += c);
    res.on('end', () => {
        console.log(data);
        try { fs.unlinkSync('//100.77.57.16/Web/dd-shop/php/_debug_schema.php'); } catch(e){}
    });
});
