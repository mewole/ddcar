<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/db.php';

if (!isset($conn) || !$conn) {
    echo json_encode(["success" => false, "error" => "No DB connection"]);
    exit;
}

$accounts = [
    [
        'name'  => 'test_admin',
        'pass'  => 'Admin#2026@dd',
        'role'  => 'แอดมิน',
        'phone' => '0800000001'
    ],
    [
        'name'  => 'test_staff',
        'pass'  => 'Staff#2026@dd',
        'role'  => 'พนักงาน',
        'phone' => '0800000002'
    ],
    [
        'name'  => 'test_viewer',
        'pass'  => 'Viewer#2026@dd',
        'role'  => 'ดูคลังสินค้า',
        'phone' => '0800000003'
    ]
];

$results = [];

foreach ($accounts as $acc) {
    $name = $acc['name'];
    $hash = password_hash($acc['pass'], PASSWORD_BCRYPT);
    $role = $acc['role'];
    $phone = $acc['phone'];

    // Check if exists
    $checkStmt = mysqli_prepare($conn, "SELECT user_id FROM user WHERE user_name = ? LIMIT 1");
    mysqli_stmt_bind_param($checkStmt, "s", $name);
    mysqli_stmt_execute($checkStmt);
    $checkRes = mysqli_stmt_get_result($checkStmt);

    if ($checkRes && mysqli_num_rows($checkRes) > 0) {
        $row = mysqli_fetch_assoc($checkRes);
        $uid = $row['user_id'];
        $upStmt = mysqli_prepare($conn, "UPDATE user SET pass = ?, user_role = ?, phone = ?, status_login = 0 WHERE user_id = ?");
        mysqli_stmt_bind_param($upStmt, "sssi", $hash, $role, $phone, $uid);
        $ok = mysqli_stmt_execute($upStmt);
        $results[] = ["user" => $name, "action" => "updated", "user_id" => $uid, "success" => $ok];
    } else {
        $insStmt = mysqli_prepare($conn, "INSERT INTO user (user_name, pass, user_role, phone, status_login) VALUES (?, ?, ?, ?, 0)");
        mysqli_stmt_bind_param($insStmt, "ssss", $name, $hash, $role, $phone);
        $ok = mysqli_stmt_execute($insStmt);
        $newId = mysqli_insert_id($conn);
        $results[] = ["user" => $name, "action" => "inserted", "user_id" => $newId, "success" => $ok];
    }
}

echo json_encode(["success" => true, "results" => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
