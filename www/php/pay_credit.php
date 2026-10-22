<?php
    include "db.php";
include_once "auth_guard.php";
require_login();
    header("Content-Type: application/json; charset=UTF-8");

    // Auto-create payment audit table if not exists
    $sql_init = "CREATE TABLE IF NOT EXISTS credit_payment (
        pay_id INT AUTO_INCREMENT PRIMARY KEY,
        credit_id INT NOT NULL,
        cus_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        note TEXT,
        user_id INT,
        user_name VARCHAR(100),
        date_time DATETIME NOT NULL,
        INDEX idx_credit (credit_id),
        INDEX idx_date (date_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    @mysqli_query($conn, $sql_init);

    $action = isset($_POST['action']) ? $_POST['action'] : 'pay';

    if ($action === 'get_history') {
        $credit_id = isset($_POST['credit_id']) ? intval($_POST['credit_id']) : 0;
        if ($credit_id <= 0) {
            echo json_encode(['status' => 'fail', 'message' => 'Missing credit_id'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $res = mysqli_query($conn, "SELECT pay_id, amount, payment_method, note, user_name, date_time 
                                    FROM credit_payment 
                                    WHERE credit_id = '$credit_id' 
                                    ORDER BY pay_id DESC");
        $history = [];
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $history[] = $r;
            }
        }
        echo json_encode(['status' => 'success', 'history' => $history], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'pay') {
        $credit_id = isset($_POST['credit_id']) ? intval($_POST['credit_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $payment_method = isset($_POST['payment_method']) ? mysqli_real_escape_string($conn, trim($_POST['payment_method'])) : 'เงินสด';
        $note = isset($_POST['note']) ? mysqli_real_escape_string($conn, trim($_POST['note'])) : '';
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $user_name = isset($_POST['user_name']) ? mysqli_real_escape_string($conn, trim($_POST['user_name'])) : '';

        if ($credit_id <= 0) {
            echo json_encode(['status' => 'fail', 'message' => 'รหัสเครดิตไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($amount <= 0) {
            echo json_encode(['status' => 'fail', 'message' => 'จำนวนเงินที่รับชำระต้องมากกว่า 0 บาท'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        mysqli_begin_transaction($conn);

        $q_chk = mysqli_query($conn, "SELECT credit_id, cus_id, credit_price, credit_num FROM credit WHERE credit_id = '$credit_id' FOR UPDATE");
        if (!$q_chk || mysqli_num_rows($q_chk) === 0) {
            mysqli_rollback($conn);
            echo json_encode(['status' => 'fail', 'message' => 'ไม่พบข้อมูลเครดิตนี้ในระบบ'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $row_cr = mysqli_fetch_assoc($q_chk);
        $cus_id = intval($row_cr['cus_id']);
        $cur_price = floatval($row_cr['credit_price']);
        $credit_num = $row_cr['credit_num'];

        $new_price = max(0.0, $cur_price - $amount);
        $now = date('Y-m-d H:i:s');

        // Update remaining balance on credit
        $q_upd = mysqli_query($conn, "UPDATE credit SET credit_price = '$new_price' WHERE credit_id = '$credit_id'");
        if (!$q_upd) {
            mysqli_rollback($conn);
            echo json_encode(['status' => 'fail', 'message' => 'ไม่สามารถอัปเดตยอดคงเหลือได้: ' . mysqli_error($conn)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Insert payment history
        $q_ins = mysqli_query($conn, "INSERT INTO credit_payment (credit_id, cus_id, amount, payment_method, note, user_id, user_name, date_time) 
                                      VALUES ('$credit_id', '$cus_id', '$amount', '$payment_method', '$note', '$user_id', '$user_name', '$now')");
        if (!$q_ins) {
            mysqli_rollback($conn);
            echo json_encode(['status' => 'fail', 'message' => 'ไม่สามารถบันทึกประวัติการรับชำระได้: ' . mysqli_error($conn)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $pay_id = mysqli_insert_id($conn);
        mysqli_commit($conn);

        echo json_encode([
            'status' => 'success',
            'credit_id' => $credit_id,
            'credit_num' => $credit_num,
            'paid_amount' => $amount,
            'previous_balance' => $cur_price,
            'remaining_balance' => $new_price,
            'is_cleared' => ($new_price <= 0),
            'pay_id' => $pay_id,
            'payment_method' => $payment_method,
            'date_time' => $now
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['status' => 'fail', 'message' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
    mysqli_close($conn);
?>
