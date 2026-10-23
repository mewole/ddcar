<?php
include_once "auth_guard.php";
require_login();

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include "db.php";

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : 'daily');
$txt_cancel = "(cancel)";

$thai_months = array(
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
);

$thai_days = array(
    'Sunday' => 'อาทิตย์', 'Monday' => 'จันทร์', 'Tuesday' => 'อังคาร',
    'Wednesday' => 'พุธ', 'Thursday' => 'พฤหัสบดี', 'Friday' => 'ศุกร์', 'Saturday' => 'เสาร์'
);

// 0. Dashboard Helpers
function get_top_products($conn, $from_dt, $to_dt, $txt_cancel, $cus_id = null, $limit = 10) {
    $where_cus = "";
    if (!empty($cus_id) && $cus_id !== 'all') {
        $where_cus = " AND s.customer_id = '$cus_id' ";
    }
    $query = "SELECT 
                p.prod_id,
                COALESCE(NULLIF(TRIM(p.prod_name), ''), 'สินค้าไม่มีชื่อ') AS prod_name,
                SUM(COALESCE(soi.prod_amount, 0)) AS total_qty,
                SUM(COALESCE(soi.prod_amount, 0) * COALESCE(soi.prod_price, 0)) AS total_revenue
              FROM sale_order s
              STRAIGHT_JOIN sale_order_item soi ON s.order_id = soi.order_id
              INNER JOIN product p ON soi.prod_id = p.prod_id
              WHERE s.date_time BETWEEN '$from_dt' AND '$to_dt'
                AND s.order_number NOT LIKE '%$txt_cancel%'
                AND s.payment_type != 'ตัดไม่คิดเงิน'
                $where_cus
              GROUP BY p.prod_id, p.prod_name
              ORDER BY total_qty DESC, total_revenue DESC
              LIMIT $limit";
    $res = mysqli_query($conn, $query);
    $items = array();
    if ($res && mysqli_num_rows($res) > 0) {
        $rank = 1;
        while ($row = mysqli_fetch_assoc($res)) {
            $items[] = array(
                'rank' => $rank++,
                'prod_id' => $row['prod_id'],
                'prod_name' => $row['prod_name'],
                'total_qty' => intval($row['total_qty']),
                'total_revenue' => round(floatval($row['total_revenue']), 2)
            );
        }
    }
    return $items;
}

function get_payment_breakdown($conn, $from_dt, $to_dt, $txt_cancel, $cus_id = null) {
    $where_cus = "";
    if (!empty($cus_id) && $cus_id !== 'all') {
        $where_cus = " AND s.customer_id = '$cus_id' ";
    }
    $query = "SELECT 
                COALESCE(NULLIF(TRIM(s.payment_type), ''), 'ไม่ระบุ') AS payment_type,
                COUNT(s.order_id) AS bill_count,
                SUM(s.total_price) AS total_sales
              FROM sale_order s
              WHERE s.date_time BETWEEN '$from_dt' AND '$to_dt'
                AND s.order_number NOT LIKE '%$txt_cancel%'
                AND s.payment_type != 'ตัดไม่คิดเงิน'
                $where_cus
              GROUP BY payment_type
              ORDER BY total_sales DESC";
    $res = mysqli_query($conn, $query);
    $breakdown = array();
    if ($res && mysqli_num_rows($res) > 0) {
        while ($row = mysqli_fetch_assoc($res)) {
            $breakdown[] = array(
                'payment_type' => $row['payment_type'],
                'bill_count' => intval($row['bill_count']),
                'total_sales' => round(floatval($row['total_sales']), 2)
            );
        }
    }
    return $breakdown;
}

function get_hourly_breakdown($conn, $from_dt, $to_dt, $txt_cancel) {
    $q_sales = "SELECT 
                HOUR(s.date_time) AS sale_hour,
                COUNT(s.order_id) AS bill_count,
                SUM(s.total_price) AS hour_sales
              FROM sale_order s
              WHERE s.date_time BETWEEN '$from_dt' AND '$to_dt'
                AND s.order_number NOT LIKE '%$txt_cancel%'
                AND s.payment_type != 'ตัดไม่คิดเงิน'
              GROUP BY HOUR(s.date_time)
              ORDER BY sale_hour ASC";
    $res = mysqli_query($conn, $q_sales);
    $map = array();
    if ($res && mysqli_num_rows($res) > 0) {
        while ($row = mysqli_fetch_assoc($res)) {
            $h = intval($row['sale_hour']);
            $sales = floatval($row['hour_sales']);
            $map[$h] = array(
                'hour' => $h,
                'hour_label' => sprintf('%02d:00', $h),
                'bill_count' => intval($row['bill_count']),
                'sales' => round($sales, 2),
                'cost' => 0,
                'profit' => round($sales, 2)
            );
        }
    }

    $q_cost = "SELECT 
                HOUR(s.date_time) AS sale_hour,
                SUM(COALESCE(soi.prod_amount, 0) * COALESCE(pr.prod_cost, 0)) AS hour_cost
              FROM sale_order s
              STRAIGHT_JOIN sale_order_item soi ON s.order_id = soi.order_id
              INNER JOIN product pr ON soi.prod_id = pr.prod_id
              WHERE s.date_time BETWEEN '$from_dt' AND '$to_dt'
                AND s.order_number NOT LIKE '%$txt_cancel%'
                AND s.payment_type != 'ตัดไม่คิดเงิน'
              GROUP BY HOUR(s.date_time)";
    $res_c = mysqli_query($conn, $q_cost);
    if ($res_c && mysqli_num_rows($res_c) > 0) {
        while ($row = mysqli_fetch_assoc($res_c)) {
            $h = intval($row['sale_hour']);
            $cost = floatval($row['hour_cost']);
            if (isset($map[$h])) {
                $map[$h]['cost'] = round($cost, 2);
                $map[$h]['profit'] = round($map[$h]['sales'] - $cost, 2);
            }
        }
    }

    $hours_list = array();
    $min_h = 8;
    $max_h = 18;
    if (!empty($map)) {
        $keys = array_keys($map);
        $min_h = min(min($keys), 8);
        $max_h = max(max($keys), 18);
    }
    for ($h = $min_h; $h <= $max_h; $h++) {
        if (isset($map[$h])) {
            $hours_list[] = $map[$h];
        } else {
            $hours_list[] = array(
                'hour' => $h,
                'hour_label' => sprintf('%02d:00', $h),
                'bill_count' => 0,
                'sales' => 0,
                'cost' => 0,
                'profit' => 0
            );
        }
    }
    return $hours_list;
}

// 1. Get Customers List
if ($action === 'get_customers') {
    $query = "SELECT cus_id, cus_name, cus_tel, cus_type FROM customer ORDER BY cus_name ASC";
    $result = mysqli_query($conn, $query);
    $customers = array();
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $customers[] = $row;
        }
    }
    echo json_encode(array('success' => true, 'customers' => $customers));
    mysqli_close($conn);
    exit;
}

// 2. Daily Summary & Bills
if ($action === 'daily') {
    $date_from = isset($_REQUEST['date_from']) ? mysqli_real_escape_string($conn, trim($_REQUEST['date_from'])) : (isset($_REQUEST['date']) ? mysqli_real_escape_string($conn, trim($_REQUEST['date'])) : date('Y-m-d'));
    $date_to = isset($_REQUEST['date_to']) ? mysqli_real_escape_string($conn, trim($_REQUEST['date_to'])) : $date_from;
    if (empty($date_from)) $date_from = date('Y-m-d');
    if (empty($date_to)) $date_to = $date_from;

    $from_dt = $date_from . ' 00:00:00';
    $to_dt = $date_to . ' 23:59:59';

    // ขั้นที่ 1: ดึงรายการบิลอย่างรวดเร็วผ่าน Index (date_time)
    $query = "SELECT 
                s.order_id,
                s.order_number,
                s.date_time,
                s.total_price,
                s.sum_price,
                s.total_discount,
                s.payment_type,
                s.customer_id,
                COALESCE(c.cus_name, 'ลูกค้าทั่วไป') AS cus_name,
                COALESCE(c.cus_tel, '-') AS cus_tel,
                u.user_name
              FROM sale_order s
              INNER JOIN user u ON s.user_id = u.user_id
              LEFT JOIN customer c ON s.customer_id = c.cus_id
              WHERE s.date_time BETWEEN '$from_dt' AND '$to_dt'
                AND s.order_number NOT LIKE '%$txt_cancel%'
                AND s.payment_type != 'ตัดไม่คิดเงิน'
              ORDER BY s.order_id DESC";

    $result = mysqli_query($conn, $query);
    $bills = array();
    $order_ids = array();
    $total_sales = 0;
    $total_bills = 0;

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $row['order_id'] = intval($row['order_id']);
            $price = floatval($row['total_price']);
            $row['total_price'] = $price;
            $row['cost'] = 0;
            $row['profit'] = 0;
            $total_sales += $price;
            $total_bills++;
            $order_ids[] = $row['order_id'];
            $bills[] = $row;
        }
    }

    // ขั้นที่ 2: ดึงต้นทุนสินค้าเฉพาะของบิลเหล่านี้ใน 1 Query สั้นๆ ผ่าน Index (order_id)
    $total_cost = 0;
    $total_profit = 0;
    if (!empty($order_ids)) {
        $idList = implode(',', $order_ids);
        $q_cost = "SELECT soi.order_id, SUM(COALESCE(soi.prod_amount, 0) * COALESCE(pr.prod_cost, 0)) AS total_cost 
                   FROM sale_order_item soi 
                   INNER JOIN product pr ON soi.prod_id = pr.prod_id 
                   WHERE soi.order_id IN ($idList) 
                   GROUP BY soi.order_id";
        $res_cost = mysqli_query($conn, $q_cost);
        $cost_map = array();
        if ($res_cost) {
            while ($rc = mysqli_fetch_assoc($res_cost)) {
                $cost_map[intval($rc['order_id'])] = floatval($rc['total_cost']);
            }
        }
        $total_writeoff_cost = 0;
        $total_writeoff_bills = 0;
        $q_w = mysqli_query($conn, "SELECT COUNT(DISTINCT s.order_id) AS cnt, SUM(COALESCE(soi.prod_amount, 0) * COALESCE(pr.prod_cost, 0)) AS cost FROM sale_order s STRAIGHT_JOIN sale_order_item soi ON s.order_id = soi.order_id INNER JOIN product pr ON soi.prod_id = pr.prod_id WHERE s.date_time BETWEEN '$from_dt' AND '$to_dt' AND s.order_number NOT LIKE '%$txt_cancel%' AND TRIM(s.payment_type) LIKE '%ตัดไม่คิดเงิน%'");
        if ($q_w && $rw = mysqli_fetch_assoc($q_w)) {
            $total_writeoff_cost = floatval($rw['cost']);
            $total_writeoff_bills = intval($rw['cnt']);
        }
        foreach ($bills as &$b) {
            $oid = $b['order_id'];
            $c = isset($cost_map[$oid]) ? $cost_map[$oid] : 0;
            $is_writeoff = ($b['payment_type'] === 'ตัดไม่คิดเงิน' || (floatval($b['total_price']) == 0 && ($b['payment_type'] === 'ตัดไม่คิดเงิน' || (isset($b['cus_name']) && strpos($b['cus_name'], 'ตัด') !== false))));
            $b['cost'] = $c;
            $b['is_writeoff'] = $is_writeoff;
            if ($is_writeoff) {
                $b['profit'] = 0;
                $total_writeoff_cost += $c;
                $total_writeoff_bills++;
                $total_sales -= $b['total_price'];
                $total_bills--;
            } else {
                $p = round($b['total_price'] - $c, 2);
                $b['profit'] = $p;
                $total_cost += $c;
                $total_profit += $p;
            }
        }
        unset($b);
        $total_bills = max(0, $total_bills);
    }

    $avg_per_bill = ($total_bills > 0) ? round($total_sales / $total_bills, 2) : 0;
    $margin_pct = ($total_sales > 0) ? round(($total_profit / $total_sales) * 100, 2) : 0;

    // คำนวณ Payment Breakdown และ Hourly Breakdown โดยตรงจากข้อมูลบิล (ความเร็ว <1ms ไม่ต้องรัน SQL ซ้ำ)
    $pay_map = array();
    $hour_map = array();
    foreach ($bills as $b) {
        if (!empty($b['is_writeoff'])) continue;
        // Payment
        $pt = !empty(trim($b['payment_type'])) ? trim($b['payment_type']) : 'ไม่ระบุ';
        if (!isset($pay_map[$pt])) {
            $pay_map[$pt] = array('payment_type' => $pt, 'bill_count' => 0, 'total_sales' => 0);
        }
        $pay_map[$pt]['bill_count']++;
        $pay_map[$pt]['total_sales'] += $b['total_price'];

        // Hourly
        $h = intval(date('H', strtotime($b['date_time'])));
        if (!isset($hour_map[$h])) {
            $hour_map[$h] = array(
                'hour' => $h,
                'hour_label' => sprintf('%02d:00', $h),
                'bill_count' => 0,
                'sales' => 0,
                'cost' => 0,
                'profit' => 0
            );
        }
        $hour_map[$h]['bill_count']++;
        $hour_map[$h]['sales'] += $b['total_price'];
        $hour_map[$h]['cost'] += $b['cost'];
        $hour_map[$h]['profit'] += $b['profit'];
    }

    $payment_breakdown = array_values($pay_map);
    usort($payment_breakdown, function($a, $b) {
        return $b['total_sales'] <=> $a['total_sales'];
    });
    foreach ($payment_breakdown as &$pb) {
        $pb['total_sales'] = round($pb['total_sales'], 2);
    }
    unset($pb);

    $hourly_breakdown = array();
    $min_h = 8;
    $max_h = 18;
    if (!empty($hour_map)) {
        $keys = array_keys($hour_map);
        $min_h = min(min($keys), 8);
        $max_h = max(max($keys), 18);
    }
    for ($h = $min_h; $h <= $max_h; $h++) {
        if (isset($hour_map[$h])) {
            $hourly_breakdown[] = array(
                'hour' => $h,
                'hour_label' => sprintf('%02d:00', $h),
                'bill_count' => $hour_map[$h]['bill_count'],
                'sales' => round($hour_map[$h]['sales'], 2),
                'cost' => round($hour_map[$h]['cost'], 2),
                'profit' => round($hour_map[$h]['profit'], 2)
            );
        } else {
            $hourly_breakdown[] = array(
                'hour' => $h,
                'hour_label' => sprintf('%02d:00', $h),
                'bill_count' => 0,
                'sales' => 0,
                'cost' => 0,
                'profit' => 0
            );
        }
    }

    $top_products = get_top_products($conn, $from_dt, $to_dt, $txt_cancel, null, 10);


    echo json_encode(array(
        'success' => true,
        'mode' => 'daily',
        'date_from' => $date_from,
        'date_to' => $date_to,
        'summary' => array(
            'total_sales' => round($total_sales, 2),
            'total_cost' => round($total_cost, 2),
            'total_profit' => round($total_profit, 2),
            'total_bills' => $total_bills,
            'avg_per_bill' => $avg_per_bill,
            'margin_pct' => $margin_pct,
            'writeoff_cost' => round($total_writeoff_cost, 2),
            'writeoff_bills' => $total_writeoff_bills
        ),
        'hourly_breakdown' => $hourly_breakdown,
        'top_products' => $top_products,
        'payment_breakdown' => $payment_breakdown,
        'bills' => $bills
    ));
    mysqli_close($conn);
    exit;
}

// 3. Monthly Summary (Breakdown by Day in selected month)
if ($action === 'monthly') {
    $year_month = isset($_REQUEST['year_month']) ? mysqli_real_escape_string($conn, trim($_REQUEST['year_month'])) : '';
    if (empty($year_month) && isset($_REQUEST['year']) && isset($_REQUEST['month'])) {
        $year_month = sprintf('%04d-%02d', intval($_REQUEST['year']), intval($_REQUEST['month']));
    }
    if (empty($year_month) || strlen($year_month) < 7) {
        $year_month = date('Y-m');
    }
    $parts = explode('-', $year_month);
    $year = intval($parts[0]);
    $month = intval($parts[1]);

    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $days_in_month = date('t', strtotime($start_date));
    $end_date = sprintf('%04d-%02d-%02d', $year, $month, $days_in_month);

    $from_dt = $start_date . ' 00:00:00';
    $to_dt = $end_date . ' 23:59:59';

    $q_sales = "SELECT 
                DATE(s.date_time) AS sale_date,
                COUNT(s.order_id) AS bill_count,
                SUM(s.total_price) AS day_sales
              FROM sale_order s
              WHERE s.date_time BETWEEN '$from_dt' AND '$to_dt'
                AND s.order_number NOT LIKE '%$txt_cancel%'
                AND s.payment_type != 'ตัดไม่คิดเงิน'
              GROUP BY DATE(s.date_time)
              ORDER BY sale_date ASC";
    $res_sales = mysqli_query($conn, $q_sales);
    $db_days = array();

    if ($res_sales && mysqli_num_rows($res_sales) > 0) {
        while ($row = mysqli_fetch_assoc($res_sales)) {
            $row['day_cost'] = 0;
            $db_days[$row['sale_date']] = $row;
        }
    }

    $q_cost = "SELECT 
                DATE(s.date_time) AS sale_date,
                SUM(COALESCE(soi.prod_amount, 0) * COALESCE(pr.prod_cost, 0)) AS day_cost
              FROM sale_order s
              STRAIGHT_JOIN sale_order_item soi ON s.order_id = soi.order_id
              INNER JOIN product pr ON soi.prod_id = pr.prod_id
              WHERE s.date_time BETWEEN '$from_dt' AND '$to_dt'
                AND s.order_number NOT LIKE '%$txt_cancel%'
                AND s.payment_type != 'ตัดไม่คิดเงิน'
              GROUP BY DATE(s.date_time)";
    $res_cost = mysqli_query($conn, $q_cost);
    if ($res_cost && mysqli_num_rows($res_cost) > 0) {
        while ($row = mysqli_fetch_assoc($res_cost)) {
            if (isset($db_days[$row['sale_date']])) {
                $db_days[$row['sale_date']]['day_cost'] = floatval($row['day_cost']);
            }
        }
    }

    $daily_breakdown = array();
    $total_sales = 0;
    $total_cost = 0;
    $total_profit = 0;
    $total_bills = 0;
    $best_day = null;
    $max_day_sales = -1;

    for ($d = 1; $d <= $days_in_month; $d++) {
        $curr_date = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $day_name_en = date('l', strtotime($curr_date));
        $day_name_th = isset($thai_days[$day_name_en]) ? $thai_days[$day_name_en] : $day_name_en;

        $bill_count = 0;
        $day_sales = 0;
        $day_cost = 0;
        $day_profit = 0;

        if (isset($db_days[$curr_date])) {
            $bill_count = intval($db_days[$curr_date]['bill_count']);
            $day_sales = floatval($db_days[$curr_date]['day_sales']);
            $day_cost = floatval($db_days[$curr_date]['day_cost']);
            $day_profit = round($day_sales - $day_cost, 2);

            $total_sales += $day_sales;
            $total_cost += $day_cost;
            $total_profit += $day_profit;
            $total_bills += $bill_count;

            if ($day_sales > $max_day_sales) {
                $max_day_sales = $day_sales;
                $best_day = array(
                    'date' => $curr_date,
                    'day' => $d,
                    'sales' => $day_sales
                );
            }
        }

        $margin = ($day_sales > 0) ? round(($day_profit / $day_sales) * 100, 2) : 0;

        $daily_breakdown[] = array(
            'date' => $curr_date,
            'day' => $d,
            'day_name' => $day_name_th,
            'bill_count' => $bill_count,
            'sales' => round($day_sales, 2),
            'cost' => round($day_cost, 2),
            'profit' => round($day_profit, 2),
            'margin_pct' => $margin
        );
    }

    $avg_per_day = ($days_in_month > 0) ? round($total_sales / $days_in_month, 2) : 0;
    $avg_per_bill = ($total_bills > 0) ? round($total_sales / $total_bills, 2) : 0;
    $margin_pct = ($total_sales > 0) ? round(($total_profit / $total_sales) * 100, 2) : 0;

    $top_products = get_top_products($conn, $from_dt, $to_dt, $txt_cancel, null, 10);
    $payment_breakdown = get_payment_breakdown($conn, $from_dt, $to_dt, $txt_cancel);

    echo json_encode(array(
        'success' => true,
        'mode' => 'monthly',
        'year_month' => $year_month,
        'year' => $year,
        'month' => $month,
        'month_name' => isset($thai_months[$month]) ? $thai_months[$month] : $month,
        'summary' => array(
            'total_sales' => round($total_sales, 2),
            'total_cost' => round($total_cost, 2),
            'total_profit' => round($total_profit, 2),
            'total_bills' => $total_bills,
            'avg_per_day' => $avg_per_day,
            'avg_per_bill' => $avg_per_bill,
            'margin_pct' => $margin_pct,
            'best_day' => $best_day
        ),
        'top_products' => $top_products,
        'payment_breakdown' => $payment_breakdown,
        'daily_breakdown' => $daily_breakdown
    ));
    mysqli_close($conn);
    exit;
}

// 4. Yearly Summary (Breakdown by 12 Months)
if ($action === 'yearly') {
    $year = isset($_REQUEST['year']) ? intval($_REQUEST['year']) : intval(date('Y'));
    if ($year < 2000 || $year > 2100) $year = intval(date('Y'));

    $from_dt = "$year-01-01 00:00:00";
    $to_dt = "$year-12-31 23:59:59";

    $q_sales = "SELECT 
                MONTH(s.date_time) AS sale_month,
                COUNT(s.order_id) AS bill_count,
                SUM(s.total_price) AS month_sales
              FROM sale_order s
              WHERE s.date_time BETWEEN '$from_dt' AND '$to_dt'
                AND s.order_number NOT LIKE '%$txt_cancel%'
                AND s.payment_type != 'ตัดไม่คิดเงิน'
              GROUP BY MONTH(s.date_time)
              ORDER BY sale_month ASC";
    $res_sales = mysqli_query($conn, $q_sales);
    $db_months = array();

    if ($res_sales && mysqli_num_rows($res_sales) > 0) {
        while ($row = mysqli_fetch_assoc($res_sales)) {
            $row['month_cost'] = 0;
            $db_months[intval($row['sale_month'])] = $row;
        }
    }

    $q_cost = "SELECT 
                MONTH(s.date_time) AS sale_month,
                SUM(COALESCE(soi.prod_amount, 0) * COALESCE(pr.prod_cost, 0)) AS month_cost
              FROM sale_order s
              STRAIGHT_JOIN sale_order_item soi ON s.order_id = soi.order_id
              INNER JOIN product pr ON soi.prod_id = pr.prod_id
              WHERE s.date_time BETWEEN '$from_dt' AND '$to_dt'
                AND s.order_number NOT LIKE '%$txt_cancel%'
                AND s.payment_type != 'ตัดไม่คิดเงิน'
              GROUP BY MONTH(s.date_time)";
    $res_cost = mysqli_query($conn, $q_cost);
    if ($res_cost && mysqli_num_rows($res_cost) > 0) {
        while ($row = mysqli_fetch_assoc($res_cost)) {
            $m = intval($row['sale_month']);
            if (isset($db_months[$m])) {
                $db_months[$m]['month_cost'] = floatval($row['month_cost']);
            }
        }
    }

    $monthly_breakdown = array();
    $total_sales = 0;
    $total_cost = 0;
    $total_profit = 0;
    $total_bills = 0;
    $best_month = null;
    $max_month_sales = -1;

    for ($m = 1; $m <= 12; $m++) {
        $bill_count = 0;
        $m_sales = 0;
        $m_cost = 0;
        $m_profit = 0;

        if (isset($db_months[$m])) {
            $bill_count = intval($db_months[$m]['bill_count']);
            $m_sales = floatval($db_months[$m]['month_sales']);
            $m_cost = floatval($db_months[$m]['month_cost']);
            $m_profit = round($m_sales - $m_cost, 2);

            $total_sales += $m_sales;
            $total_cost += $m_cost;
            $total_profit += $m_profit;
            $total_bills += $bill_count;

            if ($m_sales > $max_month_sales) {
                $max_month_sales = $m_sales;
                $best_month = array(
                    'month' => $m,
                    'month_name' => $thai_months[$m],
                    'sales' => $m_sales
                );
            }
        }

        $margin = ($m_sales > 0) ? round(($m_profit / $m_sales) * 100, 2) : 0;

        $monthly_breakdown[] = array(
            'month' => $m,
            'month_str' => sprintf('%04d-%02d', $year, $m),
            'month_name' => $thai_months[$m],
            'bill_count' => $bill_count,
            'sales' => round($m_sales, 2),
            'cost' => round($m_cost, 2),
            'profit' => round($m_profit, 2),
            'margin_pct' => $margin
        );
    }

    $avg_per_month = round($total_sales / 12, 2);
    $avg_per_bill = ($total_bills > 0) ? round($total_sales / $total_bills, 2) : 0;
    $margin_pct = ($total_sales > 0) ? round(($total_profit / $total_sales) * 100, 2) : 0;

    $top_products = get_top_products($conn, $from_dt, $to_dt, $txt_cancel, null, 10);
    $payment_breakdown = get_payment_breakdown($conn, $from_dt, $to_dt, $txt_cancel);

    echo json_encode(array(
        'success' => true,
        'mode' => 'yearly',
        'year' => $year,
        'summary' => array(
            'total_sales' => round($total_sales, 2),
            'total_cost' => round($total_cost, 2),
            'total_profit' => round($total_profit, 2),
            'total_bills' => $total_bills,
            'avg_per_month' => $avg_per_month,
            'avg_per_bill' => $avg_per_bill,
            'margin_pct' => $margin_pct,
            'best_month' => $best_month
        ),
        'top_products' => $top_products,
        'payment_breakdown' => $payment_breakdown,
        'monthly_breakdown' => $monthly_breakdown
    ));
    mysqli_close($conn);
    exit;
}

// 5. Customer Summary (All Customers Ranking OR Specific Customer Bills)
if ($action === 'customer') {
    $cus_id = isset($_REQUEST['cus_id']) && $_REQUEST['cus_id'] !== '' ? mysqli_real_escape_string($conn, trim($_REQUEST['cus_id'])) : '';
    $date_from = isset($_REQUEST['date_from']) ? mysqli_real_escape_string($conn, trim($_REQUEST['date_from'])) : '';
    $date_to = isset($_REQUEST['date_to']) ? mysqli_real_escape_string($conn, trim($_REQUEST['date_to'])) : '';

    $where_date_s = "";
    $where_date_plain = "";
    if (!empty($date_from) && !empty($date_to)) {
        $from_dt = $date_from . ' 00:00:00';
        $to_dt = $date_to . ' 23:59:59';
        $where_date_s = " AND s.date_time BETWEEN '$from_dt' AND '$to_dt' ";
        $where_date_plain = " AND date_time BETWEEN '$from_dt' AND '$to_dt' ";
    } elseif (!empty($date_from)) {
        $from_dt = $date_from . ' 00:00:00';
        $where_date_s = " AND s.date_time >= '$from_dt' ";
        $where_date_plain = " AND date_time >= '$from_dt' ";
    } elseif (!empty($date_to)) {
        $to_dt = $date_to . ' 23:59:59';
        $where_date_s = " AND s.date_time <= '$to_dt' ";
        $where_date_plain = " AND date_time <= '$to_dt' ";
    }

    if (!empty($cus_id) && $cus_id !== 'all') {
        // Specific Customer View: get customer info and all bills
        $c_query = "SELECT cus_id, cus_name, cus_tel, cus_addr, cus_type FROM customer WHERE cus_id = '$cus_id'";
        $c_res = mysqli_query($conn, $c_query);
        $customer_info = ($c_res && mysqli_num_rows($c_res) > 0) ? mysqli_fetch_assoc($c_res) : null;

        $query = "SELECT 
                    s.order_id,
                    s.order_number,
                    s.date_time,
                    s.total_price,
                    s.sum_price,
                    s.total_discount,
                    s.payment_type,
                    s.customer_id,
                    COALESCE(c.cus_name, 'ลูกค้าทั่วไป') AS cus_name,
                    COALESCE(c.cus_tel, '-') AS cus_tel,
                    COALESCE(u.user_name, '-') AS user_name,
                    COALESCE(cost_data.total_cost, 0) AS cost
                  FROM sale_order s
                  LEFT JOIN user u ON s.user_id = u.user_id
                  LEFT JOIN customer c ON s.customer_id = c.cus_id
                  LEFT JOIN (
                      SELECT soi.order_id, SUM(COALESCE(soi.prod_amount, 0) * COALESCE(pr.prod_cost, 0)) AS total_cost
                      FROM sale_order_item soi
                      INNER JOIN product pr ON soi.prod_id = pr.prod_id
                      WHERE soi.order_id IN (
                          SELECT order_id FROM sale_order 
                          WHERE customer_id = '$cus_id' $where_date_plain
                            AND order_number NOT LIKE '%$txt_cancel%'
                      )
                      GROUP BY soi.order_id
                  ) cost_data ON s.order_id = cost_data.order_id
                  WHERE s.customer_id = '$cus_id'
                    $where_date_s
                    AND s.order_number NOT LIKE '%$txt_cancel%'
                  ORDER BY s.order_id DESC";

        $result = mysqli_query($conn, $query);
        if (!$result) {
            echo json_encode(array(
                'success' => false,
                'message' => 'Query error: ' . mysqli_error($conn)
            ));
            mysqli_close($conn);
            exit;
        }

        $bills = array();
        $total_sales = 0;
        $total_cost = 0;
        $total_profit = 0;
        $total_bills = 0;

        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $price = floatval($row['total_price']);
                $cost = floatval($row['cost']);
                $profit = ($price == 0) ? 0 : round($price - $cost, 2);

                $row['total_price'] = $price;
                $row['cost'] = $cost;
                $row['profit'] = $profit;

                $total_sales += $price;
                $total_cost += $cost;
                $total_profit += $profit;
                $total_bills++;

                $bills[] = $row;
            }
        }

        $margin_pct = ($total_sales > 0) ? round(($total_profit / $total_sales) * 100, 2) : 0;

        $from_dt_c = !empty($date_from) ? $date_from . ' 00:00:00' : '2000-01-01 00:00:00';
        $to_dt_c = !empty($date_to) ? $date_to . ' 23:59:59' : date('Y-m-d 23:59:59');
        $top_products = get_top_products($conn, $from_dt_c, $to_dt_c, $txt_cancel, $cus_id, 10);
        $payment_breakdown = get_payment_breakdown($conn, $from_dt_c, $to_dt_c, $txt_cancel, $cus_id);

        echo json_encode(array(
            'success' => true,
            'mode' => 'customer_detail',
            'customer' => $customer_info,
            'summary' => array(
                'total_sales' => round($total_sales, 2),
                'total_cost' => round($total_cost, 2),
                'total_profit' => round($total_profit, 2),
                'total_bills' => $total_bills,
                'margin_pct' => $margin_pct
            ),
            'top_products' => $top_products,
            'payment_breakdown' => $payment_breakdown,
            'bills' => $bills
        ));
    } else {
        // All Customers Ranking View
        // 1. Fetch sales and bill count by customer
        $query = "SELECT 
                    s.customer_id,
                    COALESCE(c.cus_name, 'ลูกค้าทั่วไป (ไม่ระบุ)') AS cus_name,
                    COALESCE(c.cus_tel, '-') AS cus_tel,
                    COALESCE(c.cus_type, 'ทั่วไป') AS cus_type,
                    COUNT(s.order_id) AS bill_count,
                    SUM(s.total_price) AS total_sales,
                    MAX(s.date_time) AS last_order_date
                  FROM sale_order s
                  LEFT JOIN customer c ON s.customer_id = c.cus_id
                  WHERE s.order_number NOT LIKE '%$txt_cancel%'
                    $where_date_s
                  GROUP BY s.customer_id
                  ORDER BY total_sales DESC";

        $result = mysqli_query($conn, $query);
        if (!$result) {
            echo json_encode(array(
                'success' => false,
                'message' => 'Query error: ' . mysqli_error($conn)
            ));
            mysqli_close($conn);
            exit;
        }

        // 2. Fetch total cost per customer via indexed STRAIGHT_JOIN
        $cost_map = array();
        $q_cost = "SELECT 
                    s.customer_id,
                    SUM(COALESCE(soi.prod_amount, 0) * COALESCE(pr.prod_cost, 0)) AS total_cost
                  FROM sale_order s
                  STRAIGHT_JOIN sale_order_item soi ON s.order_id = soi.order_id
                  INNER JOIN product pr ON soi.prod_id = pr.prod_id
                  WHERE s.order_number NOT LIKE '%$txt_cancel%'
                    $where_date_s
                  GROUP BY s.customer_id";
        $res_cost = mysqli_query($conn, $q_cost);
        if ($res_cost && mysqli_num_rows($res_cost) > 0) {
            while ($cr = mysqli_fetch_assoc($res_cost)) {
                $cost_map[$cr['customer_id']] = floatval($cr['total_cost']);
            }
        }

        $customers = array();
        $total_sales = 0;
        $total_cost = 0;
        $total_profit = 0;
        $total_bills = 0;
        $top_customer = null;

        if ($result && mysqli_num_rows($result) > 0) {
            $rank = 1;
            while ($row = mysqli_fetch_assoc($result)) {
                $sales = floatval($row['total_sales']);
                $cost = isset($cost_map[$row['customer_id']]) ? $cost_map[$row['customer_id']] : 0;
                $profit = round($sales - $cost, 2);
                $b_count = intval($row['bill_count']);

                $row['rank'] = $rank++;
                $row['total_sales'] = round($sales, 2);
                $row['total_cost'] = round($cost, 2);
                $row['total_profit'] = $profit;
                $row['bill_count'] = $b_count;
                $row['margin_pct'] = ($sales > 0) ? round(($profit / $sales) * 100, 2) : 0;

                $total_sales += $sales;
                $total_cost += $cost;
                $total_profit += $profit;
                $total_bills += $b_count;

                if ($top_customer === null && $row['customer_id'] > 0) {
                    $top_customer = $row;
                }

                $customers[] = $row;
            }
        }

        $margin_pct = ($total_sales > 0) ? round(($total_profit / $total_sales) * 100, 2) : 0;

        $from_dt_c = !empty($date_from) ? $date_from . ' 00:00:00' : '2000-01-01 00:00:00';
        $to_dt_c = !empty($date_to) ? $date_to . ' 23:59:59' : date('Y-m-d 23:59:59');
        $top_products = get_top_products($conn, $from_dt_c, $to_dt_c, $txt_cancel, null, 10);
        $payment_breakdown = get_payment_breakdown($conn, $from_dt_c, $to_dt_c, $txt_cancel);

        echo json_encode(array(
            'success' => true,
            'mode' => 'customer_ranking',
            'summary' => array(
                'total_sales' => round($total_sales, 2),
                'total_cost' => round($total_cost, 2),
                'total_profit' => round($total_profit, 2),
                'total_bills' => $total_bills,
                'customer_count' => count($customers),
                'margin_pct' => $margin_pct,
                'top_customer' => $top_customer
            ),
            'top_products' => $top_products,
            'payment_breakdown' => $payment_breakdown,
            'customers' => $customers
        ));
    }

    mysqli_close($conn);
    exit;
}

echo json_encode(array('success' => false, 'message' => 'Invalid action'));
mysqli_close($conn);
?>
