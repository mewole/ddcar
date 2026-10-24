const fs = require('fs');

console.log('--- 1. Updating get_bill_details_for_return.php ---');
let billPhp = fs.readFileSync('c:/ddcar/www/php/get_bill_details_for_return.php', 'utf8');

// Replace status check
billPhp = billPhp.replace(
    "AND r.status != 'ยกเลิก'",
    "AND r.status NOT LIKE 'ยกเลิก%'"
);

// Add platform orders previous return check
const platOld = `'qty_returned_so_far' => 0,\r\n                    'qty_available'       => $qty_sold,`;
const platOldLF = `'qty_returned_so_far' => 0,\n                    'qty_available'       => $qty_sold,`;

const platNew = `// Check previous returns for platform orders
                $q_prev_p = mysqli_query($conn, "SELECT COALESCE(SUM(ri.qty), 0) as returned_qty 
                                                FROM product_return_item ri
                                                JOIN product_return r ON ri.return_id = r.id
                                                WHERE r.order_sn = '$sn' 
                                                  AND (ri.prod_id = '$pid' OR ri.prod_code = '" . mysqli_real_escape_string($conn, $item['target_sku'] ?: $item['platform_sku']) . "')
                                                  AND r.status NOT LIKE 'ยกเลิก%'");
                $prev_plat_ret = 0;
                if ($q_prev_p && $r_pp = mysqli_fetch_assoc($q_prev_p)) {
                    $prev_plat_ret = intval($r_pp['returned_qty']);
                }
                $qty_avail_plat = max(0, $qty_sold - $prev_plat_ret);

                    'qty_returned_so_far' => $prev_plat_ret,
                    'qty_available'       => $qty_avail_plat,`;

if (billPhp.includes(platOld)) {
    billPhp = billPhp.replace(platOld, platNew);
    console.log('  -> Platform orders return check added (CRLF)');
} else if (billPhp.includes(platOldLF)) {
    billPhp = billPhp.replace(platOldLF, platNew);
    console.log('  -> Platform orders return check added (LF)');
}

fs.writeFileSync('c:/ddcar/www/php/get_bill_details_for_return.php', billPhp, 'utf8');
console.log('  -> Saved get_bill_details_for_return.php');

console.log('\n--- 2. Updating save_product_return.php ---');
let savePhp = fs.readFileSync('c:/ddcar/www/php/save_product_return.php', 'utf8');

// Branch ID safety check: If branch_type is 'หน้าร้าน' and branch_id is 1 or 0, set to 6
const branchSafetyCheck = `        if ($branch_type === 'หน้าร้าน' && ($branch_id === 1 || $branch_id === 0)) {
            $branch_id = 6; // Shop ID for หน้าร้าน is 6 in shop_detail
        }`;

// Insert server-side over-return check inside item loop
const itemLoopStart = `$pid         = intval($item['prod_id']);`;
const itemLoopNew = `$pid         = intval($item['prod_id']);
        $pcode       = trim($item['prod_code'] ?? '');
        $pname       = trim($item['prod_name'] ?? '');
        $qty         = intval($item['qty'] ?? 0);

        // N5 Validation: ตรวจสอบจำนวนที่ขอคืน ต้องไม่เกินจำนวนที่สามารถคืนได้จริง
        if (!empty($bill_id) && $bill_id > 0 && $pid > 0) {
            $q_orig = mysqli_query($conn, "SELECT prod_amount FROM sale_order_item WHERE order_id = '$bill_id' AND prod_id = '$pid' LIMIT 1");
            if ($q_orig && $r_orig = mysqli_fetch_assoc($q_orig)) {
                $orig_qty = intval($r_orig['prod_amount']);
                $q_done = mysqli_query($conn, "SELECT COALESCE(SUM(ri.qty), 0) as done_qty 
                                               FROM product_return_item ri
                                               JOIN product_return r ON ri.return_id = r.id
                                               WHERE r.bill_id = '$bill_id' AND ri.prod_id = '$pid' AND r.status NOT LIKE 'ยกเลิก%'");
                $already_returned = 0;
                if ($q_done && $r_done = mysqli_fetch_assoc($q_done)) {
                    $already_returned = intval($r_done['done_qty']);
                }
                $allowed_qty = max(0, $orig_qty - $already_returned);
                if ($qty > $allowed_qty) {
                    throw new Exception("สินค้า {$pcode} ({$pname}) ขอคืน {$qty} ชิ้น เกินกว่าจำนวนที่คืนได้ (สามารถคืนได้อีก {$allowed_qty} จากทั้งหมด {$orig_qty} ชิ้น)");
                }
            }
        }`;

if (savePhp.includes(itemLoopStart) && !savePhp.includes('N5 Validation')) {
    savePhp = savePhp.replace(
        `$pid         = intval($item['prod_id']);\r\n        $pcode       = trim($item['prod_code'] ?? '');\r\n        $pname       = trim($item['prod_name'] ?? '');\r\n        $qty         = intval($item['qty'] ?? 0);`,
        itemLoopNew
    );
    if (!savePhp.includes('N5 Validation')) {
        savePhp = savePhp.replace(
            `$pid         = intval($item['prod_id']);\n        $pcode       = trim($item['prod_code'] ?? '');\n        $pname       = trim($item['prod_name'] ?? '');\n        $qty         = intval($item['qty'] ?? 0);`,
            itemLoopNew
        );
    }
    console.log('  -> Server-side over-return validation added');
}

// Add branch safety check before stock adjustment
const branchCheckTarget = `if ($destination === 'stock') {`;
if (savePhp.includes(branchCheckTarget) && !savePhp.includes('Shop ID for หน้าร้าน is 6')) {
    savePhp = savePhp.replace(branchCheckTarget, branchSafetyCheck + '\r\n\r\n        ' + branchCheckTarget);
    console.log('  -> Branch ID safety check added');
}

fs.writeFileSync('c:/ddcar/www/php/save_product_return.php', savePhp, 'utf8');
console.log('  -> Saved save_product_return.php');
