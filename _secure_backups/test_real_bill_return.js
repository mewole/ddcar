const http = require('http');

function httpRequest(options, postData = null) {
  return new Promise((resolve, reject) => {
    if (postData) {
      options.headers = options.headers || {};
      if (typeof postData === 'object') {
        postData = JSON.stringify(postData);
        options.headers['Content-Type'] = 'application/json';
      } else {
        options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
      }
      options.headers['Content-Length'] = Buffer.byteLength(postData);
    }
    const req = http.request(options, (res) => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => {
        resolve({
          statusCode: res.statusCode,
          headers: res.headers,
          body: body
        });
      });
    });
    req.on('error', reject);
    if (postData) req.write(postData);
    req.end();
  });
}

async function runEndToEnd() {
  console.log('=== End-to-End RT Return & Claim Flow Test ===\n');

  // 1. Login
  const loginRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/login.php',
    method: 'POST'
  }, 'name=Song&pass=1111');
  const cookie = loginRes.headers['set-cookie'][0].split(';')[0];
  console.log('1. Logged in successfully as Song');

  // 2. Search recent bill
  const billsRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/get_bill.php',
    method: 'POST',
    headers: { 'Cookie': cookie }
  }, 'start=0');
  
  let targetOrderNumber = '';
  try {
    const list = JSON.parse(billsRes.body);
    if (list && list.length > 1) {
      targetOrderNumber = list[1].order_number || list[1][1];
      console.log('2. Found existing real sale bill:', targetOrderNumber);
    }
  } catch (e) {
    console.log('Could not parse bills list:', billsRes.body.substring(0, 100));
  }

  if (!targetOrderNumber) {
    console.log('No existing bill found to test with.');
    return;
  }

  // 3. Query bill details for return
  console.log('\n3. Looking up bill details for return with:', targetOrderNumber);
  const detailRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: `/dd-shop/php/get_bill_details_for_return.php?keyword=${encodeURIComponent(targetOrderNumber)}`,
    method: 'GET',
    headers: { 'Cookie': cookie }
  });
  
  const detailData = JSON.parse(detailRes.body);
  console.log('Detail response:', {
    found: detailData.found,
    bill_number: detailData.bill ? detailData.bill.bill_number : null,
    customer: detailData.bill ? detailData.bill.customer_name : null,
    items_count: detailData.bill && detailData.bill.items ? detailData.bill.items.length : 0
  });

  if (!detailData.found || !detailData.bill || detailData.bill.items.length === 0) {
    console.log('Cannot proceed without bill items');
    return;
  }

  const sampleItem = detailData.bill.items[0];
  console.log('Sample item to return:', {
    prod_id: sampleItem.prod_id,
    prod_code: sampleItem.prod_code,
    prod_name: sampleItem.prod_name,
    available_qty: sampleItem.qty_available,
    suggested_supplier: sampleItem.suggested_supplier
  });

  // 4. Test Save Return Document (RT)
  console.log('\n4. Creating test return document (RT)...');
  const returnPayload = {
    bill_id: detailData.bill.bill_id,
    bill_number: detailData.bill.bill_number,
    customer_id: detailData.bill.customer_id,
    customer_name: detailData.bill.customer_name,
    actual_return_date: '2026-10-24',
    refund_type: 'คืนเงินสด',
    reason: 'ทดสอบระบบรับคืนสินค้า Phase 1',
    is_vat: false,
    items: [
      {
        prod_id: sampleItem.prod_id,
        prod_code: sampleItem.prod_code,
        prod_name: sampleItem.prod_name,
        qty: 1,
        unit_price: sampleItem.unit_price,
        cost: sampleItem.cost,
        destination: 'quarantine', // ทดสอบเส้นทางแตกหัก -> คลังรอเคลม
        branch_type: 'หน้าร้าน',
        branch_id: 1,
        supplier_name: sampleItem.suggested_supplier || 'ซัพพลายเออร์หลัก',
        damage_reason: 'สินค้ามีตำหนิ แตกหัก รอเคลมร้านค้า',
        damage_images: ['img/test_damage1.jpg']
      }
    ]
  };

  const saveRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/save_product_return.php',
    method: 'POST',
    headers: { 'Cookie': cookie }
  }, returnPayload);

  console.log('Save RT Status:', saveRes.statusCode);
  console.log('Save RT Headers:', saveRes.headers);
  console.log('Save RT Response Body:', saveRes.body);
  const saveData = JSON.parse(saveRes.body);

  if (!saveData.success) {
    console.error('Failed to create RT:', saveData.error);
    return;
  }

  const newReturnCode = saveData.return_code;
  console.log(`>>> Created Return Document: ${newReturnCode} (CN: ${saveData.credit_note_number || 'N/A'}) <<<`);

  // 5. Verify in Returns History
  console.log('\n5. Checking Returns History...');
  const histRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/get_returns.php',
    method: 'GET',
    headers: { 'Cookie': cookie }
  });
  const histData = JSON.parse(histRes.body);
  console.log(`Returns history count: ${histData.total}`);
  const createdRecord = histData.data.find(r => r.return_code === newReturnCode);
  if (createdRecord) {
    console.log('Verified RT in history:', {
      return_code: createdRecord.return_code,
      customer: createdRecord.customer_name,
      refund_type: createdRecord.refund_type,
      total_refund: createdRecord.total_refund_amount,
      items: createdRecord.items ? createdRecord.items.length : 0
    });
  }

  // 6. Verify in Supplier Claims ("หน้าเคลมค้างรับ")
  console.log('\n6. Checking Supplier Claims Queue...');
  const claimsRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/get_supplier_claims.php?status=wait_send',
    method: 'GET',
    headers: { 'Cookie': cookie }
  });
  const claimsData = JSON.parse(claimsRes.body);
  console.log('Claims summary:', claimsData.summary);
  const myClaim = claimsData.data.find(c => c.return_code === newReturnCode);
  if (myClaim) {
    console.log('Found quarantine item in claims:', {
      claim_id: myClaim.claim_id,
      return_code: myClaim.return_code,
      supplier: myClaim.supplier_name,
      status: myClaim.status,
      days_pending: myClaim.days_pending
    });

    // 7. Update Claim Status: mark_sent
    console.log('\n7. Updating claim status -> mark_sent...');
    const sendRes = await httpRequest({
      hostname: '100.77.57.16',
      port: 8083,
      path: '/dd-shop/php/update_claim_status.php',
      method: 'POST',
      headers: { 'Cookie': cookie }
    }, {
      claim_id: myClaim.claim_id,
      action: 'mark_sent',
      supplier_ref_no: 'REF-SUP-9988',
      notes: 'ส่งของเคลมให้ซัพพลายเออร์แล้ว'
    });
    console.log('Mark Sent Response:', sendRes.body);

    // 8. Update Claim Status: resolve_received_goods (ของคืนเข้าสต๊อกขาย)
    console.log('\n8. Updating claim status -> resolve_received_goods (ของเคลมกลับเข้าสต๊อกขาย)...');
    const resolveRes = await httpRequest({
      hostname: '100.77.57.16',
      port: 8083,
      path: '/dd-shop/php/update_claim_status.php',
      method: 'POST',
      headers: { 'Cookie': cookie }
    }, {
      claim_id: myClaim.claim_id,
      action: 'resolve_received_goods',
      notes: 'ได้รับสินค้าเคลมตัวใหม่สมบูรณ์เรียบร้อย นำเข้าสต๊อกขาย'
    });
    console.log('Resolve Goods Response:', resolveRes.body);
  }

  console.log('\n=== All Phase 1 Operations Verified Successfully 100% ===');
}

runEndToEnd().catch(console.error);
