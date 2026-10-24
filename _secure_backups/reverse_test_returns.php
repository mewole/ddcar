<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/db.php';

if (!isset($conn) || !$conn) {
    echo json_encode(["success" => false, "error" => "No DB connection"]);
    exit;
}

mysqli_begin_transaction($conn);

try {
    $report = [];

    // 1. Check stock of prod_id 4691 before reversal
    $q_before_4691 = mysqli_query($conn, "SELECT shop_id, prod_id, amount FROM shop WHERE prod_id = 4691");
    $stock_before_4691 = [];
    while ($r = mysqli_fetch_assoc($q_before_4691)) {
        $stock_before_4691[] = $r;
    }
    $report['stock_4691_before'] = $stock_before_4691;

    // Remove false test stock in shop_id = 1 for prod_id 4691
    $del_shop1 = mysqli_query($conn, "DELETE FROM shop WHERE shop_id = 1 AND prod_id = 4691");
    $report['del_shop1_4691_affected'] = mysqli_affected_rows($conn);

    // Check stock of prod_id 4691 after reversal
    $q_after_4691 = mysqli_query($conn, "SELECT shop_id, prod_id, amount FROM shop WHERE prod_id = 4691");
    $stock_after_4691 = [];
    while ($r = mysqli_fetch_assoc($q_after_4691)) {
        $stock_after_4691[] = $r;
    }
    $report['stock_4691_after'] = $stock_after_4691;

    // Check stock of prod_id 9287
    $q_9287 = mysqli_query($conn, "SELECT shop_id, prod_id, amount FROM shop WHERE prod_id = 9287");
    $stock_9287 = [];
    while ($r = mysqli_fetch_assoc($q_9287)) {
        $stock_9287[] = $r;
    }
    $report['stock_9287_current'] = $stock_9287;

    // 2. Update status of RT26-00001 and RT26-00002 to 'ยกเลิก-ทดสอบ' (DO NOT DELETE)
    $up_rt1 = mysqli_query($conn, "UPDATE product_return 
        SET status = 'ยกเลิก-ทดสอบ', 
            reason = CONCAT(reason, ' [ยกเลิกรายการทดสอบระบบ]') 
        WHERE return_code = 'RT26-00001'");
    $report['rt1_updated'] = mysqli_affected_rows($conn);

    $up_rt2 = mysqli_query($conn, "UPDATE product_return 
        SET status = 'ยกเลิก-ทดสอบ', 
            reason = CONCAT(reason, ' [ยกเลิกรายการทดสอบระบบ]') 
        WHERE return_code = 'RT26-00002'");
    $report['rt2_updated'] = mysqli_affected_rows($conn);

    // 3. Update status of CLM26-00001 and CLM26-00002 to 'ยกเลิก-ทดสอบ' and fix prod_name
    $up_clm1 = mysqli_query($conn, "UPDATE product_claim_supplier 
        SET status = 'ยกเลิก-ทดสอบ',
            prod_name = 'ไฟหน้า swift 2018 pro ดำ sonar V1',
            notes = CONCAT(notes, ' [ยกเลิกรายการทดสอบระบบ]') 
        WHERE claim_code = 'CLM26-00001'");
    $report['clm1_updated'] = mysqli_affected_rows($conn);

    $up_clm2 = mysqli_query($conn, "UPDATE product_claim_supplier 
        SET status = 'ยกเลิก-ทดสอบ',
            prod_name = 'กระจังหน้า Tiger D4D ลาย Sport Rider',
            notes = CONCAT(notes, ' [ยกเลิกรายการทดสอบระบบ]') 
        WHERE claim_code = 'CLM26-00002'");
    $report['clm2_updated'] = mysqli_affected_rows($conn);

    // Verify all rows
    $q_rt_all = mysqli_query($conn, "SELECT return_code, bill_number, return_type, status, reason FROM product_return WHERE return_code IN ('RT26-00001', 'RT26-00002')");
    $rt_rows = [];
    while ($r = mysqli_fetch_assoc($q_rt_all)) {
        $rt_rows[] = $r;
    }
    $report['returns_state'] = $rt_rows;

    $q_clm_all = mysqli_query($conn, "SELECT claim_code, return_code, prod_code, prod_name, status, notes FROM product_claim_supplier WHERE claim_code IN ('CLM26-00001', 'CLM26-00002')");
    $clm_rows = [];
    while ($r = mysqli_fetch_assoc($q_clm_all)) {
        $clm_rows[] = $r;
    }
    $report['claims_state'] = $clm_rows;

    mysqli_commit($conn);
    echo json_encode(["success" => true, "report" => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
