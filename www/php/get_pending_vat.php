<?php
require_once "db.php";
header("Content-Type: application/json; charset=utf-8");

$status = isset($_GET['status']) ? mysqli_real_escape_string($conn, trim($_GET['status'])) : 'pending';

$where = "WHERE 1=1";
if ($status === 'pending') {
    $where .= " AND status = 'pending_invoice'";
} elseif ($status === 'cleared') {
    $where .= " AND status = 'cleared'";
}

$sql = "SELECT * FROM pending_input_vat $where ORDER BY id DESC";
$res = mysqli_query($conn, $sql);

$items = [];
$total_pending_vat = 0;
$total_cost = 0;
$suppliers_map = [];

while ($row = mysqli_fetch_assoc($res)) {
    $cost = floatval($row['cost_amount']);
    $vat = floatval($row['pending_vat_amount']);
    if ($row['status'] === 'pending_invoice') {
        $total_cost += $cost;
        $total_pending_vat += $vat;

        $sup = $row['supplier_name'] ?: 'ไม่ระบุซัพพลายเออร์';
        if (!isset($suppliers_map[$sup])) {
            $suppliers_map[$sup] = [
                'supplier_name' => $sup,
                'count' => 0,
                'total_cost' => 0,
                'total_vat' => 0,
                'items' => []
            ];
        }
        $suppliers_map[$sup]['count']++;
        $suppliers_map[$sup]['total_cost'] += $cost;
        $suppliers_map[$sup]['total_vat'] += $vat;
        $suppliers_map[$sup]['items'][] = [
            'prod_code' => $row['prod_code'],
            'prod_name' => $row['prod_name'],
            'qty' => $row['qty'],
            'cost' => $cost,
            'vat' => $vat
        ];
    }

    $items[] = [
        'id' => intval($row['id']),
        'supplier_name' => $row['supplier_name'],
        'ref_invoice' => $row['ref_invoice'],
        'order_date' => $row['order_date'],
        'prod_code' => $row['prod_code'],
        'prod_name' => $row['prod_name'],
        'qty' => intval($row['qty']),
        'cost_amount' => $cost,
        'pending_vat_amount' => $vat,
        'platform' => $row['platform'],
        'status' => $row['status'],
        'tax_invoice_no' => $row['tax_invoice_no'],
        'notes' => $row['notes'],
        'created_at' => $row['created_at'],
        'cleared_at' => $row['cleared_at']
    ];
}

// Calculate days to 15th of next month (PP.30 filing deadline)
$today = new DateTime();
$deadline = new DateTime($today->format('Y-m-15'));
if ($today > $deadline) {
    // Next month's 15th
    $deadline->modify('+1 month');
}
$interval = $today->diff($deadline);
$days_to_deadline = $interval->days;

echo json_encode([
    'success' => true,
    'total_count' => count($items),
    'pending_vat_amount' => $total_pending_vat,
    'total_cost' => $total_cost,
    'days_to_deadline' => $days_to_deadline,
    'deadline_date' => $deadline->format('d/m/Y'),
    'suppliers' => array_values($suppliers_map),
    'items' => $items
], JSON_UNESCAPED_UNICODE);
?>