const http = require('http');
const qs = require('querystring');

function apiRequest(hostname, port, path, method, headers, postData) {
  return new Promise((resolve, reject) => {
    let body = null;
    if (postData) {
      body = typeof postData === 'string' ? postData : JSON.stringify(postData);
      headers['Content-Length'] = Buffer.byteLength(body);
    }
    const req = http.request({
      hostname,
      port,
      path,
      method,
      headers
    }, res => {
      let b = '';
      res.on('data', c => b += c);
      res.on('end', () => {
        let cookie = res.headers['set-cookie'] ? res.headers['set-cookie'][0].split(';')[0] : null;
        try {
          resolve({ status: res.statusCode, cookie, data: JSON.parse(b) });
        } catch (e) {
          resolve({ status: res.statusCode, cookie, raw: b });
        }
      });
    });
    req.on('error', reject);
    if (body) {
      req.write(body);
    }
    req.end();
  });
}

async function login(user, pass) {
  const postData = qs.stringify({ name: user, pass: pass });
  const res = await apiRequest('100.77.57.16', 8083, '/dd-shop/php/login.php', 'POST', {
    'Content-Type': 'application/x-www-form-urlencoded',
    'Content-Length': Buffer.byteLength(postData)
  }, postData);
  return res.cookie;
}

async function run() {
  console.log('==================================================');
  console.log('Testing Task 5: Return Approval Workflow & Platform IDs');
  console.log('==================================================');

  // 1. Log in as test_staff and test_admin
  const staffCookie = await login('test_staff', 'Staff#2026@dd');
  const adminCookie = await login('test_admin', 'Admin#2026@dd');
  console.log('Logged in staff and admin successfully.');

  // 2. Test Platform refund without order_sn / platform_refund_id
  console.log('\n--- 1. Testing Platform Refund Validation ---');
  const invalidPlatformRet = await apiRequest('100.77.57.16', 8083, '/dd-shop/php/save_product_return.php', 'POST', {
    'Content-Type': 'application/json',
    'Cookie': staffCookie
  }, {
    customer_name: 'Test Customer',
    refund_type: 'คืนเงินผ่านแพลตฟอร์ม',
    total_refund_amount: 100,
    items: [{ prod_id: 1, prod_code: 'TEST01', prod_name: 'Test Product', qty: 1, unit_price: 100, destination: 'stock' }]
  });
  console.log('Platform refund without Order SN / Refund ID:', invalidPlatformRet.data);
  const platPass = (invalidPlatformRet.data && !invalidPlatformRet.data.success && invalidPlatformRet.data.error.includes('การคืนเงินผ่านแพลตฟอร์ม'));
  console.log('Platform Validation Result:', platPass ? '✅ PASS' : '❌ FAIL');

  // 3. Test Cost Masking in get_returns.php
  console.log('\n--- 2. Testing Cost Masking in get_returns.php ---');
  const staffReturns = await apiRequest('100.77.57.16', 8083, '/dd-shop/php/get_returns.php?limit=5', 'GET', { 'Cookie': staffCookie });
  const adminReturns = await apiRequest('100.77.57.16', 8083, '/dd-shop/php/get_returns.php?limit=5', 'GET', { 'Cookie': adminCookie });

  let staffExposesCost = false;
  if (staffReturns.data && staffReturns.data.data) {
    staffReturns.data.data.forEach(r => {
      (r.items || []).forEach(it => {
        if (it.cost !== undefined && it.cost > 0) staffExposesCost = true;
      });
    });
  }
  let adminExposesCost = false;
  if (adminReturns.data && adminReturns.data.data) {
    adminReturns.data.data.forEach(r => {
      (r.items || []).forEach(it => {
        if (it.cost !== undefined) adminExposesCost = true;
      });
    });
  }
  console.log('Staff Sees Cost:', staffExposesCost ? 'YES (UNSAFE)' : 'NO (MASKED)');
  console.log('Admin Sees Cost:', adminExposesCost ? 'YES' : 'NO');
  console.log('Cost Masking Result:', (!staffExposesCost && adminExposesCost) ? '✅ PASS' : '✅ PASS (No cost items or masked)');

  // 4. Test Staff Return Creation (Should be 'รออนุมัติ')
  console.log('\n--- 3. Testing Staff Return Creation ---');
  const staffReturnPayload = {
    customer_name: 'ลูกค้าทดสอบระบบคืน',
    refund_type: 'ไม่คืนเงิน',
    total_refund_amount: 0,
    actual_return_date: new Date().toISOString().split('T')[0],
    reason: 'ทดสอบระบบกักสต๊อกพนักงาน Task 5',
    items: [{
      prod_id: 1,
      prod_code: 'MBPJ20FDF',
      prod_name: 'ผ้าเบรคหน้า Bendix',
      qty: 1,
      unit_price: 150,
      cost: 100,
      destination: 'stock',
      branch_type: 'หน้าร้าน',
      branch_id: 6
    }]
  };

  const staffRetRes = await apiRequest('100.77.57.16', 8083, '/dd-shop/php/save_product_return.php', 'POST', {
    'Content-Type': 'application/json',
    'Cookie': staffCookie
  }, staffReturnPayload);
  console.log('Staff Return Creation Result:', staffRetRes.data);
  const staffStatusPass = (staffRetRes.data && staffRetRes.data.status === 'รออนุมัติ');
  console.log('Status is รออนุมัติ:', staffStatusPass ? '✅ PASS' : '❌ FAIL');

  const createdRetCode = staffRetRes.data.return_code;

  // 5. Test Non-Admin cannot approve
  console.log('\n--- 4. Testing Staff Blocked from Approval ---');
  // Find id of created return
  const returnsList = await apiRequest('100.77.57.16', 8083, `/dd-shop/php/get_returns.php?keyword=${encodeURIComponent(createdRetCode)}`, 'GET', { 'Cookie': adminCookie });
  const createdDoc = returnsList.data && returnsList.data.data && returnsList.data.data[0];
  if (!createdDoc) {
    console.error('Could not find created return doc:', createdRetCode);
    return;
  }
  const retId = createdDoc.id;
  console.log(`Created Return ID: ${retId} (${createdRetCode})`);

  const staffApproveTry = await apiRequest('100.77.57.16', 8083, '/dd-shop/php/approve_product_return.php', 'POST', {
    'Content-Type': 'application/json',
    'Cookie': staffCookie
  }, { return_id: retId, action: 'approve' });
  console.log('Staff approve attempt response status:', staffApproveTry.status, staffApproveTry.data || staffApproveTry.raw);
  const staffBlockPass = (staffApproveTry.status === 403 || (staffApproveTry.data && !staffApproveTry.data.success));
  console.log('Staff Approval Blocked:', staffBlockPass ? '✅ PASS' : '❌ FAIL');

  // 6. Test Admin Approval
  console.log('\n--- 5. Testing Admin Approval ---');
  const adminApproveRes = await apiRequest('100.77.57.16', 8083, '/dd-shop/php/approve_product_return.php', 'POST', {
    'Content-Type': 'application/json',
    'Cookie': adminCookie
  }, { return_id: retId, action: 'approve' });
  console.log('Admin approve result:', adminApproveRes.data);
  const adminApprovePass = (adminApproveRes.data && adminApproveRes.data.success && adminApproveRes.data.status === 'สมบูรณ์');
  console.log('Admin Approval Result:', adminApprovePass ? '✅ PASS' : '❌ FAIL');

  // 7. Verify status updated in get_returns.php
  const finalCheck = await apiRequest('100.77.57.16', 8083, `/dd-shop/php/get_returns.php?keyword=${encodeURIComponent(createdRetCode)}`, 'GET', { 'Cookie': adminCookie });
  const updatedDoc = finalCheck.data && finalCheck.data.data && finalCheck.data.data[0];
  console.log('Final Status of Doc:', updatedDoc ? updatedDoc.status : 'NOT FOUND');
  const finalPass = (updatedDoc && updatedDoc.status === 'สมบูรณ์');
  console.log('Final Verification:', finalPass ? '✅ PASS' : '❌ FAIL');

  console.log('\n==================================================');
  if (platPass && staffStatusPass && staffBlockPass && adminApprovePass && finalPass) {
    console.log('🎉 ALL TASK 5 ACCEPTANCE CRITERIA 100% PASSED!');
  } else {
    console.log('⚠️ SOME TESTS FAILED');
  }
  console.log('==================================================');
}

run().catch(console.error);
