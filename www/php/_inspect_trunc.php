<?php
require_once "db.php";

$res = mysqli_query($conn, "SELECT id, channel_code, platform_sku, platform_name FROM platform_sku_mapping WHERE platform_name LIKE '%[%]' ORDER BY id DESC");
$items = [];
while ($r = mysqli_fetch_assoc($res)) {
    $items[] = $r;
}

echo "Total items with [ ]: " . count($items) . "\n\n";

foreach ($items as $it) {
    $name = $it['platform_name'];
    if (preg_match('/^(.*?)\[(.*?)\]$/u', $name, $m)) {
        $title = trim($m[1]);
        $var = trim($m[2]);
        // If title length >= 35, or ends abruptly without punctuation/space
        if (mb_strlen($title, 'utf-8') >= 38 || mb_strlen($var, 'utf-8') >= 16) {
            echo "ID: " . $it['id'] . "\n";
            echo "SKU: " . $it['platform_sku'] . "\n";
            echo "Title: " . $title . " (len: " . mb_strlen($title, 'utf-8') . ")\n";
            echo "Var: [" . $var . "] (len: " . mb_strlen($var, 'utf-8') . ")\n";
            echo "Full: " . $name . "\n";
            echo "----------------------------------------\n";
        }
    }
}
?>
