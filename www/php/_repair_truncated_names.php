<?php
require_once "db.php";

$repairs = [
    // [search_pattern, replacement, field]
    ['ลาย Thund', 'ลาย Thunder'],
    ['Altis 2001 2003 2004 2005 2006 20 ', 'Altis 2001 2003 2004 2005 2006 2007 '],
    ['Altis 2001 2003 2004 2005 2006 20 [', 'Altis 2001 2003 2004 2005 2006 2007 ['],
    ['ข้างซ้าย คนนั]', 'ข้างซ้าย คนนั่ง]'],
    ['ข้างซ้าย ฝั่งคนนั]', 'ข้างซ้าย ฝั่งคนนั่ง]'],
    ['สีแ [', 'สีแดง ['],
    ['ไม่มีข [', 'ไม่มีขั้ว ['],
    ['2018 2020 ร [', '2018 2020 รูไฟเลี้ยว ['],
    ['Space Arm toyota v [', 'Space Arm toyota vigo ['],
    ['สีโคเมีย [', 'สีโครเมียม ['],
    ['ลายเดิมๆข [', 'ลายเดิมๆขาวแดง ['],
    ['สำหรับกระ [', 'สำหรับกระบะ ['],
    ['ตัวเตี้ยตัวสูง:ตัว]', 'ตัวเตี้ยตัวสูง:ตัวเตี้ย]'],
    ['ตัวเตี้ยตัวสูง:ตัวส]', 'ตัวเตี้ยตัวสูง:ตัวสูง]'],
    ['ไม่ทำสีกับทำสี:ไม]', 'ไม่ทำสีกับทำสี:ไม่ทำสี]'],
    ['ไม่ทำสีกับทำสี:ทำ]', 'ไม่ทำสีกับทำสี:ทำสี]'],
    ['หลอดไฟเบรคไฟถอย:หล]', 'หลอดไฟเบรคไฟถอย:หลอดไฟเบรค]'],
    ['สี:สีดำ 1 คู่ 2 ชิ]', 'สี:สีดำ 1 คู่ 2 ชิ้น]'],
    ['ชนิดเกจวัด:Boost (]', 'ชนิดเกจวัด:Boost (วัดบูสท์)]'],
    ['ตอนเดียว 1.30 ม, REv]', 'ตอนเดียว 1.30 ม, REVO]'],
    ['ใส่ได้ทุกรุ่ [', 'ใส่ได้ทุกรุ่น [']
];

$updated = 0;
foreach ($repairs as $r) {
    $search = $r[0];
    $replace = $r[1];

    $q = mysqli_query($conn, "SELECT id, platform_name FROM platform_sku_mapping WHERE platform_name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'");
    while ($row = mysqli_fetch_assoc($q)) {
        $newName = str_replace($search, $replace, $row['platform_name']);
        $up = mysqli_query($conn, "UPDATE platform_sku_mapping SET platform_name = '" . mysqli_real_escape_string($conn, $newName) . "' WHERE id = " . $row['id']);
        if ($up) $updated++;
    }

    // Also update platform_order_items
    $q_it = mysqli_query($conn, "SELECT id, platform_name FROM platform_order_items WHERE platform_name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'");
    while ($row = mysqli_fetch_assoc($q_it)) {
        $newName = str_replace($search, $replace, $row['platform_name']);
        mysqli_query($conn, "UPDATE platform_order_items SET platform_name = '" . mysqli_real_escape_string($conn, $newName) . "' WHERE id = " . $row['id']);
    }
}

echo json_encode(["success" => true, "updated_rows" => $updated]);
?>
