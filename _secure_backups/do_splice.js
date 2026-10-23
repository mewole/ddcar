const fs = require('fs');

let content = fs.readFileSync('c:/ddcar/www/php/get_sales_summary.php', 'utf8');
let lines = content.split(/\r?\n/);

// 1. Splice daily loop
const newDailyLines = [
  '        $total_writeoff_cost = 0;',
  '        $total_writeoff_bills = 0;',
  '        foreach ($bills as &$b) {',
  '            $oid = $b[\'order_id\'];',
  '            $c = isset($cost_map[$oid]) ? $cost_map[$oid] : 0;',
  '            $is_writeoff = ($b[\'payment_type\'] === \'ตัดไม่คิดเงิน\' || (floatval($b[\'total_price\']) == 0 && ($b[\'payment_type\'] === \'ตัดไม่คิดเงิน\' || (isset($b[\'cus_name\']) && strpos($b[\'cus_name\'], \'ตัด\') !== false))));',
  '            $b[\'cost\'] = $c;',
  '            $b[\'is_writeoff\'] = $is_writeoff;',
  '            if ($is_writeoff) {',
  '                $b[\'profit\'] = 0;',
  '                $total_writeoff_cost += $c;',
  '                $total_writeoff_bills++;',
  '                $total_sales -= $b[\'total_price\'];',
  '                $total_bills--;',
  '            } else {',
  '                $p = round($b[\'total_price\'] - $c, 2);',
  '                $b[\'profit\'] = $p;',
  '                $total_cost += $c;',
  '                $total_profit += $p;',
  '            }',
  '        }',
  '        unset($b);',
  '        $total_bills = max(0, $total_bills);'
];

let loopIdx = lines.findIndex(l => l.includes('foreach ($bills as &$b) {'));
if (loopIdx !== -1) {
  let unsetIdx = lines.findIndex((l, i) => i > loopIdx && l.includes('unset($b);'));
  if (unsetIdx !== -1) {
    lines.splice(loopIdx, (unsetIdx - loopIdx + 1), ...newDailyLines);
    console.log(`Replaced daily loop lines ${loopIdx} to ${unsetIdx}`);
  }
}

// 2. Find and update payment breakdown loop
let payIdx = lines.findIndex(l => l.includes('foreach ($bills as $b) {') && lines[lines.indexOf(l) + 1].includes('// Payment'));
if (payIdx !== -1) {
  lines.splice(payIdx + 1, 0, '        if (!empty($b[\'is_writeoff\'])) continue;');
  console.log(`Added is_writeoff check at line ${payIdx + 1}`);
}

// 3. Find and update summary
let marginIdx = lines.findIndex(l => l.includes('\'margin_pct\' => $margin_pct'));
if (marginIdx !== -1) {
  lines.splice(marginIdx + 1, 0, 
    '            \'writeoff_cost\' => round($total_writeoff_cost, 2),',
    '            \'writeoff_bills\' => $total_writeoff_bills'
  );
  console.log(`Added writeoff_cost and writeoff_bills at line ${marginIdx + 1}`);
}

// 4. Update monthly & yearly queries to exclude ตัดไม่คิดเงิน
let newContent = lines.join('\n');
newContent = newContent.replace(
  /WHERE s\.date_time BETWEEN '\$from_dt' AND '\$to_dt'\s+AND s\.order_number NOT LIKE '%\$txt_cancel%'/g,
  "WHERE s.date_time BETWEEN '$from_dt' AND '$to_dt'\n                AND s.order_number NOT LIKE '%$txt_cancel%'\n                AND s.payment_type != 'ตัดไม่คิดเงิน'"
);

fs.writeFileSync('c:/ddcar/www/php/get_sales_summary.php', newContent, 'utf8');
console.log('Successfully updated get_sales_summary.php!');
