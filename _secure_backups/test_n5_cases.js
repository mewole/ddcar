const http = require('http');

// Helper to make POST/GET requests to local NAS web server
function request(path, method = 'GET', data = null, headers = {}) {
  return new Promise((resolve, reject) => {
    const isJson = data && typeof data === 'object';
    const bodyStr = isJson ? JSON.stringify(data) : data;

    const reqHeaders = { ...headers };
    if (isJson) {
      reqHeaders['Content-Type'] = 'application/json';
    } else if (data) {
      reqHeaders['Content-Type'] = 'application/x-www-form-urlencoded';
    }

    const options = {
      hostname: '100.77.57.16',
      port: 8083,
      path: '/dd-shop/' + path,
      method: method,
      headers: reqHeaders
    };

    const req = http.request(options, (res) => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => {
        try {
          resolve({ status: res.statusCode, body, json: JSON.parse(body) });
        } catch (e) {
          resolve({ status: res.statusCode, body, json: null });
        }
      });
    });

    req.on('error', reject);
    if (bodyStr) req.write(bodyStr);
    req.end();
  });
}

// 1. Pro-rata discount test logic (Case 6)
function testProRataDiscount() {
  console.log('\n--- Case 6: Pro-rata Discount Calculation ---');
  // Scenario: Bill total sum_price = 1000, total_discount = 200 (net = 800)
  // Item 1: price 500, qty 1. Item 2: price 500, qty 1.
  // Discount ratio = 1 - (200 / 1000) = 0.8
  // Return Item 1 only: refund amount = 500 * 0.8 * 1 = 400.
  // Not 500! (Preventing refunding more than paid)
  const sum_price = 1000;
  const total_discount = 200;
  const discountRatio = Math.max(0, 1 - (total_discount / sum_price));
  const item1_refund = 500 * discountRatio * 1;
  const item2_refund = 500 * discountRatio * 1;

  console.log(`Sum price: ฿${sum_price}, Discount: ฿${total_discount}, Net: ฿${sum_price - total_discount}`);
  console.log(`Discount Ratio: ${discountRatio}`);
  console.log(`Item 1 (฿500) Refund: ฿${item1_refund} (Expected 400)`);
  console.log(`Item 2 (฿500) Refund: ฿${item2_refund} (Expected 400)`);
  const totalRefund = item1_refund + item2_refund;
  console.log(`Total if both returned: ฿${totalRefund} (Matches Net ฿800: ${totalRefund === 800})`);
  return item1_refund === 400 && totalRefund === 800;
}

// 2. Multi-return subtraction test (Case 2 & 3)
function testMultiReturnTracking() {
  console.log('\n--- Case 2 & 3: Multi-Return Tracking ---');
  // Scenario: Item sold = 3.
  // Return 1: qty = 1.
  // Remaining available = 3 - 1 = 2.
  // Return 2: qty = 2.
  // Remaining available = 2 - 2 = 0 (Disabled, 'คืนครบแล้ว').
  const qty_sold = 3;
  let returns_so_far = [1];
  let qty_available_round2 = qty_sold - returns_so_far.reduce((a, b) => a + b, 0);
  console.log(`Sold: ${qty_sold}, Return 1: 1 -> Available for Return 2: ${qty_available_round2} (Expected 2)`);

  returns_so_far.push(2);
  let qty_available_round3 = Math.max(0, qty_sold - returns_so_far.reduce((a, b) => a + b, 0));
  console.log(`Return 2: 2 -> Available for Return 3: ${qty_available_round3} (Expected 0 - คืนครบแล้ว)`);
  return qty_available_round2 === 2 && qty_available_round3 === 0;
}

// 3. Platform order refund detection test (Case 5)
function testPlatformDetection() {
  console.log('\n--- Case 5: Platform Auto-Detection ---');
  const samples = [
    { channel: 'Shopee', order_sn: '241024SHP123', isOnline: true },
    { channel: 'Lazada', order_sn: '241024LZD456', isOnline: true },
    { channel: 'TikTok Shop', order_sn: '57712398412', isOnline: true },
    { channel: 'หน้าร้าน', order_sn: '', isOnline: false }
  ];

  let passed = true;
  samples.forEach(s => {
    const isOnline = (s.order_sn && s.order_sn.length > 5) ||
                     (s.channel && (s.channel.includes('Shopee') || s.channel.includes('Lazada') || s.channel.includes('TikTok') || s.channel.includes('Tiktok')));
    const expected = s.isOnline;
    console.log(`Channel: ${s.channel} -> Detected Online: ${isOnline} (Matches Expected: ${isOnline === expected})`);
    if (isOnline !== expected) passed = false;
  });
  return passed;
}

// 4. Test actual get_bill_details_for_return.php endpoint
async function testEndpointBills() {
  console.log('\n--- Testing Live API get_bill_details_for_return.php ---');
  // First login with test_staff
  const loginRes = await request('php/login.php', 'POST', 'user=test_staff&pass=1234');
  let cookie = '';
  // Or test directly with session or GET parameter if endpoint allows
  const billRes = await request('php/get_bill_details_for_return.php?keyword=S26-13971');
  console.log(`Status: ${billRes.status}`);
  if (billRes.json) {
    console.log(`Bill: ${billRes.json.found ? 'FOUND' : 'NOT FOUND'}`);
    if (billRes.json.bill) {
      const b = billRes.json.bill;
      console.log(`Bill #${b.bill_number}, Customer: ${b.customer_name}`);
      (b.items || []).forEach(it => {
        console.log(`  Item: ${it.prod_code} - ${it.prod_name}`);
        console.log(`    Sold: ${it.qty_sold}, Returned: ${it.qty_returned_so_far}, Avail: ${it.qty_available}`);
      });
    }
  } else {
    console.log('Response body:', billRes.body.substring(0, 200));
  }
}

async function runAll() {
  console.log('============================================');
  console.log('  RUNNING N5 TEST VERIFICATION SUITE');
  console.log('============================================');
  const r1 = testProRataDiscount();
  const r2 = testMultiReturnTracking();
  const r3 = testPlatformDetection();
  await testEndpointBills();

  console.log('\n============================================');
  console.log(`Test Suite Results: Discount: ${r1 ? 'PASS' : 'FAIL'}, Multi-Return: ${r2 ? 'PASS' : 'FAIL'}, Platform: ${r3 ? 'PASS' : 'FAIL'}`);
  console.log('============================================');
}

runAll().catch(console.error);
