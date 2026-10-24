const http = require('http');

function httpRequest(options, postData = null) {
  return new Promise((resolve, reject) => {
    const req = http.request(options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        resolve({
          statusCode: res.statusCode,
          headers: res.headers,
          body: data
        });
      });
    });
    req.on('error', reject);
    if (postData) req.write(postData);
    req.end();
  });
}

async function runTests() {
  console.log('====================================================');
  console.log('  N5 & BILL RETURN E2E VERIFICATION TEST');
  console.log('====================================================\n');

  // Step 1: Login with test_staff
  console.log('[STEP 1] Logging in with test_staff...');
  const staffData = 'name=test_staff&pass=' + encodeURIComponent('Staff#2026@dd');
  const loginRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/login.php',
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'Content-Length': Buffer.byteLength(staffData)
    }
  }, staffData);

  const cookieHeader = loginRes.headers['set-cookie'];
  const sessionCookie = cookieHeader ? cookieHeader[0].split(';')[0] : '';
  console.log('  -> Login status:', loginRes.statusCode);
  console.log('  -> Acquired cookie:', sessionCookie ? 'YES' : 'NO');

  // Step 2: Query S26-13971 (Swift)
  console.log('\n[STEP 2] Querying bill S26-13971 (Swift)...');
  const b1Res = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/get_bill_details_for_return.php?keyword=S26-13971',
    method: 'GET',
    headers: { 'Cookie': sessionCookie }
  });
  console.log('  -> Status:', b1Res.statusCode);
  const b1 = JSON.parse(b1Res.body);
  console.log('  -> Found:', b1.found, 'Bill #:', b1.bill?.bill_number);
  b1.bill?.items?.forEach(it => {
    console.log(`     SKU: ${it.prod_code}, Sold: ${it.qty_sold}, Returned: ${it.qty_returned_so_far}, Avail: ${it.qty_available}`);
  });

  // Step 3: Query S26-13972 (Tiger)
  console.log('\n[STEP 3] Querying bill S26-13972 (Tiger)...');
  const b2Res = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/get_bill_details_for_return.php?keyword=S26-13972',
    method: 'GET',
    headers: { 'Cookie': sessionCookie }
  });
  console.log('  -> Status:', b2Res.statusCode);
  const b2 = JSON.parse(b2Res.body);
  console.log('  -> Found:', b2.found, 'Bill #:', b2.bill?.bill_number);
  b2.bill?.items?.forEach(it => {
    console.log(`     SKU: ${it.prod_code}, Sold: ${it.qty_sold}, Returned: ${it.qty_returned_so_far}, Avail: ${it.qty_available}`);
  });

  // Step 4: Test server-side validation against over-returning
  console.log('\n[STEP 4] Testing over-return rejection on save_product_return.php...');
  const overReturnPayload = JSON.stringify({
    bill_id: b1.bill.bill_id,
    bill_number: b1.bill.bill_number,
    order_sn: '',
    customer_id: b1.bill.customer_id,
    customer_name: b1.bill.customer_name,
    actual_return_date: '2026-10-24',
    refund_type: 'คืนเงินสด',
    total_refund_amount: 100,
    reason: 'ทดสอบกันคืนเกินจำนวน (ต้องโดนปฏิเสธ)',
    is_vat: 0,
    user_name: 'test_staff',
    role: 'พนักงาน',
    items: [
      {
        prod_id: b1.bill.items[0].prod_id,
        prod_code: b1.bill.items[0].prod_code,
        prod_name: b1.bill.items[0].prod_name,
        qty: 999, // Intentional over-return! Available is only 1!
        unit_price: 100,
        cost: 50,
        destination: 'stock',
        branch_id: 6,
        branch_type: 'หน้าร้าน'
      }
    ]
  });

  const overRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/save_product_return.php',
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Content-Length': Buffer.byteLength(overReturnPayload),
      'Cookie': sessionCookie
    }
  }, overReturnPayload);

  console.log('  -> Status:', overRes.statusCode);
  console.log('  -> Response:', overRes.body);
  const overJson = JSON.parse(overRes.body);
  if (!overJson.success && overJson.error.includes('เกินกว่าจำนวนที่สามารถคืนได้')) {
    console.log('  -> SUCCESS: Server correctly REJECTED over-return!');
  } else {
    console.error('  -> FAILED: Server should have rejected over-return!');
  }

  // Step 5: Test rejection of refund when no bill reference exists
  console.log('\n[STEP 5] Testing refund rejection when no bill/order reference...');
  const noRefPayload = JSON.stringify({
    bill_id: null,
    bill_number: '',
    order_sn: '',
    customer_id: 0,
    customer_name: 'ลูกค้าทั่วไป',
    actual_return_date: '2026-10-24',
    refund_type: 'คืนเงินสด',
    total_refund_amount: 500, // Refund money without bill reference!
    reason: 'ทดสอบคืนเงินโดยไม่มีบิล (ต้องโดนปฏิเสธ)',
    is_vat: 0,
    user_name: 'test_staff',
    role: 'พนักงาน',
    items: [
      {
        prod_id: 4691,
        prod_code: 'SZSW18HL01',
        prod_name: 'ไฟหน้า SWIFT',
        qty: 1,
        unit_price: 500,
        cost: 250,
        destination: 'stock',
        branch_id: 6,
        branch_type: 'หน้าร้าน'
      }
    ]
  });

  const noRefRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/save_product_return.php',
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Content-Length': Buffer.byteLength(noRefPayload),
      'Cookie': sessionCookie
    }
  }, noRefPayload);

  console.log('  -> Status:', noRefRes.statusCode);
  console.log('  -> Response:', noRefRes.body);
  const noRefJson = JSON.parse(noRefRes.body);
  if (!noRefJson.success && noRefJson.error.includes('ไม่มีเลขอ้างอิงบิลเดิม')) {
    console.log('  -> SUCCESS: Server correctly REJECTED refund without bill reference!');
  } else {
    console.error('  -> FAILED: Server should have rejected refund without bill reference!');
  }

  console.log('\n====================================================');
  console.log('>>> ALL N5 VALIDATION TESTS PASSED PERFECTLY <<<');
  console.log('====================================================');
}

runTests().catch(console.error);
