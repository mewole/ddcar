const fs = require('fs');

const filePath = 'c:/ddcar/www/php/get_sales_summary.php';
let content = fs.readFileSync(filePath, 'utf8');

// 1. Patch Daily:
const oldDailyLoop = `        foreach ($bills as &$b) {
            $oid = $b['order_id'];
            $c = isset($cost_map[$oid]) ? $cost_map[$oid] : 0;
            $p = ($b['total_price'] == 0) ? 0 : round($b['total_price'] - $c, 2);
            $b['cost'] = $c;
            $b['profit'] = $p;
            $total_cost += $c;
            $total_profit += $p;
        }
        unset($b);`;

const newDailyLoop = `        $total_writeoff_cost = 0;
        $total_writeoff_bills = 0;
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
        $total_bills = max(0, $total_bills);`;

content = content.replace(oldDailyLoop, newDailyLoop);

// Also skip writeoff in daily hourly and payment maps
content = content.replace(
  `    foreach ($bills as $b) {
        // Payment`,
  `    foreach ($bills as $b) {
        if (!empty($b['is_writeoff'])) continue;
        // Payment`
);

// Daily summary output: add writeoff_cost and writeoff_bills
content = content.replace(
  `            'margin_pct' => $margin_pct
        ),
        'hourly_breakdown'`,
  `            'margin_pct' => $margin_pct,
            'writeoff_cost' => round($total_writeoff_cost, 2),
            'writeoff_bills' => $total_writeoff_bills
        ),
        'hourly_breakdown'`
);

// 2. Patch Monthly:
// Exclude ตัดไม่คิดเงิน from monthly sales
content = content.replace(
  `                AND s.order_number NOT LIKE '%$txt_cancel%'
              GROUP BY DATE(s.date_time)
              ORDER BY sale_date ASC";`,
  `                AND s.order_number NOT LIKE '%$txt_cancel%'
                AND s.payment_type != 'ตัดไม่คิดเงิน'
              GROUP BY DATE(s.date_time)
              ORDER BY sale_date ASC";`
);

// Exclude ตัดไม่คิดเงิน from monthly cost
content = content.replace(
  `                AND s.order_number NOT LIKE '%$txt_cancel%'
              GROUP BY DATE(s.date_time)";
    $res_cost = mysqli_query($conn, $q_cost);`,
  `                AND s.order_number NOT LIKE '%$txt_cancel%'
                AND s.payment_type != 'ตัดไม่คิดเงิน'
              GROUP BY DATE(s.date_time)";
    $res_cost = mysqli_query($conn, $q_cost);`
);

// 3. Patch Yearly:
// Exclude ตัดไม่คิดเงิน from yearly sales
content = content.replace(
  `                AND s.order_number NOT LIKE '%$txt_cancel%'
              GROUP BY MONTH(s.date_time)
              ORDER BY sale_month ASC";`,
  `                AND s.order_number NOT LIKE '%$txt_cancel%'
                AND s.payment_type != 'ตัดไม่คิดเงิน'
              GROUP BY MONTH(s.date_time)
              ORDER BY sale_month ASC";`
);

// Exclude ตัดไม่คิดเงิน from yearly cost
content = content.replace(
  `                AND s.order_number NOT LIKE '%$txt_cancel%'
              GROUP BY MONTH(s.date_time)";
    $res_cost = mysqli_query($conn, $q_cost);`,
  `                AND s.order_number NOT LIKE '%$txt_cancel%'
                AND s.payment_type != 'ตัดไม่คิดเงิน'
              GROUP BY MONTH(s.date_time)";
    $res_cost = mysqli_query($conn, $q_cost);`
);

fs.writeFileSync(filePath, content, 'utf8');
console.log('Successfully patched get_sales_summary.php!');
