const http = require('http');
const qs = require('querystring');

function login(username, password) {
  return new Promise((resolve, reject) => {
    const postData = qs.stringify({ name: username, pass: password });
    const req = http.request({
      hostname: '100.77.57.16',
      port: 8083,
      path: '/dd-shop/php/login.php',
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(postData)
      }
    }, res => {
      let body = '';
      res.on('data', c => body += c);
      res.on('end', () => {
        const cookie = res.headers['set-cookie'] ? res.headers['set-cookie'][0].split(';')[0] : '';
        resolve({ cookie, body: JSON.parse(body) });
      });
    });
    req.on('error', reject);
    req.write(postData);
    req.end();
  });
}

const zlib = require('zlib');

function getApi(path, cookie) {
  return new Promise((resolve, reject) => {
    http.get('http://100.77.57.16:8083/dd-shop/php/' + path, {
      headers: { 'Cookie': cookie }
    }, res => {
      let chunks = [];
      res.on('data', c => chunks.push(c));
      res.on('end', () => {
        let buf = Buffer.concat(chunks);
        if (res.headers['content-encoding'] === 'gzip') {
          try { buf = zlib.gunzipSync(buf); } catch(e) {}
        }
        const body = buf.toString('utf8');
        try {
          resolve({ status: res.statusCode, data: JSON.parse(body) });
        } catch(e) {
          resolve({ status: res.statusCode, raw: body });
        }
      });
    }).on('error', reject);
  });
}

function postApi(path, postDataObj, cookie) {
  return new Promise((resolve, reject) => {
    const postData = qs.stringify(postDataObj);
    const req = http.request({
      hostname: '100.77.57.16',
      port: 8083,
      path: '/dd-shop/php/' + path,
      method: 'POST',
      headers: {
        'Cookie': cookie,
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(postData)
      }
    }, res => {
      let body = '';
      res.on('data', c => body += c);
      res.on('end', () => {
        try {
          resolve({ status: res.statusCode, data: JSON.parse(body) });
        } catch(e) {
          resolve({ status: res.statusCode, raw: body });
        }
      });
    });
    req.on('error', reject);
    req.write(postData);
    req.end();
  });
}

async function runTests() {
  console.log('=================================================================');
  console.log('TEST SUITE: Task 1 - Blind Cost & Security Verification');
  console.log('=================================================================\n');

  // --- PART 1: TEST STAFF (Must NOT see ANY cost) ---
  console.log('1. Logging in as test_staff (Role: พนักงาน)...');
  const staff = await login('test_staff', 'Staff#2026@dd');
  console.log('   Logged in:', staff.body[0]);

  // Test get_autoprod.php
  const autoStaff = await getApi('get_autoprod.php', staff.cookie);
  const sampleAuto = autoStaff.data[0];
  const hasCostAutoStaff = sampleAuto && ('prod_cost' in sampleAuto || 'cost' in sampleAuto);
  console.log('   [API] get_autoprod.php for Staff:');
  console.log('         - Keys in first item:', Object.keys(sampleAuto));
  console.log('         - Contains cost/prod_cost:', hasCostAutoStaff, hasCostAutoStaff ? '❌ FAIL' : '✅ PASS');

  // Test get_bill_details_for_return.php
  const retStaff = await getApi('get_bill_details_for_return.php?keyword=S26-13631', staff.cookie);
  const firstItemRet = retStaff.data && retStaff.data.bill && retStaff.data.bill.items ? retStaff.data.bill.items[0] : null;
  const hasCostRetStaff = firstItemRet && ('cost' in firstItemRet || 'prod_cost' in firstItemRet);
  console.log('   [API] get_bill_details_for_return.php for Staff:');
  console.log('         - Keys in returned item:', firstItemRet ? Object.keys(firstItemRet) : 'null');
  console.log('         - Contains cost/prod_cost:', hasCostRetStaff, hasCostRetStaff ? '❌ FAIL' : '✅ PASS');

  // Test search_prod.php
  const searchStaff = await postApi('search_prod.php', { data: 'กระจังหน้า', limit: 5 }, staff.cookie);
  const firstSearch = Array.isArray(searchStaff.data) ? searchStaff.data[0] : null;
  const hasCostSearchStaff = firstSearch && ('cost' in firstSearch || 'prod_cost' in firstSearch);
  console.log('   [API] search_prod.php for Staff:');
  console.log('         - Keys in search item:', firstSearch ? Object.keys(firstSearch) : 'null');
  console.log('         - Contains cost/prod_cost:', hasCostSearchStaff, hasCostSearchStaff ? '❌ FAIL' : '✅ PASS');

  // Test search_prod_full.php
  const fullStaff = await postApi('search_prod_full.php', { phrase: 'dmax' }, staff.cookie);
  const firstFull = Array.isArray(fullStaff.data) ? fullStaff.data[0] : null;
  const hasCostFullStaff = firstFull && ('cost' in firstFull || 'prod_cost' in firstFull);
  console.log('   [API] search_prod_full.php for Staff:');
  console.log('         - Contains cost/prod_cost in item:', hasCostFullStaff, hasCostFullStaff ? '❌ FAIL' : '✅ PASS');

  // Test get_low_stock.php
  const lowStaff = await getApi('get_low_stock.php?filter=all', staff.cookie);
  const firstLow = lowStaff.data && lowStaff.data.items ? lowStaff.data.items[0] : null;
  const hasCostLowStaff = firstLow && ('cost' in firstLow || 'prod_cost' in firstLow);
  console.log('   [API] get_low_stock.php for Staff:');
  console.log('         - Contains cost/prod_cost in item:', hasCostLowStaff, hasCostLowStaff ? '❌ FAIL' : '✅ PASS');

  // --- PART 2: TEST ADMIN (MUST see costs for inventory management) ---
  console.log('\n2. Logging in as test_admin (Role: แอดมิน)...');
  const admin = await login('test_admin', 'Admin#2026@dd');
  console.log('   Logged in:', admin.body[0]);

  // Test get_autoprod.php
  const autoAdmin = await getApi('get_autoprod.php', admin.cookie);
  const sampleAdmin = autoAdmin.data[0];
  const hasCostAutoAdmin = sampleAdmin && ('prod_cost' in sampleAdmin || 'cost' in sampleAdmin);
  console.log('   [API] get_autoprod.php for Admin:');
  console.log('         - Contains cost/prod_cost:', hasCostAutoAdmin, hasCostAutoAdmin ? '✅ PASS' : '❌ FAIL');
  if (hasCostAutoAdmin) {
    console.log('         - Sample cost values: prod_cost =', sampleAdmin.prod_cost, ', cost =', sampleAdmin.cost);
  }

  // Test get_bill_details_for_return.php
  const retAdmin = await getApi('get_bill_details_for_return.php?keyword=S26-13631', admin.cookie);
  const firstItemAdmin = retAdmin.data && retAdmin.data.bill && retAdmin.data.bill.items ? retAdmin.data.bill.items[0] : null;
  const hasCostRetAdmin = firstItemAdmin && ('cost' in firstItemAdmin);
  console.log('   [API] get_bill_details_for_return.php for Admin:');
  console.log('         - Contains cost:', hasCostRetAdmin, hasCostRetAdmin ? '✅ PASS' : '❌ FAIL');
  if (hasCostRetAdmin) {
    console.log('         - Sample cost =', firstItemAdmin.cost);
  }

  // Test search_prod.php
  const searchAdmin = await postApi('search_prod.php', { data: 'กระจังหน้า', limit: 5 }, admin.cookie);
  const firstSearchAdmin = Array.isArray(searchAdmin.data) ? searchAdmin.data[0] : null;
  const hasCostSearchAdmin = firstSearchAdmin && ('prod_cost' in firstSearchAdmin);
  console.log('   [API] search_prod.php for Admin:');
  console.log('         - Contains prod_cost:', hasCostSearchAdmin, hasCostSearchAdmin ? '✅ PASS' : '❌ FAIL');

  console.log('\n=================================================================');
  console.log('OVERALL TASK 1 VERIFICATION RESULT:');
  const staffPassed = !hasCostAutoStaff && !hasCostRetStaff && !hasCostSearchStaff && !hasCostFullStaff && !hasCostLowStaff;
  const adminPassed = hasCostAutoAdmin && hasCostRetAdmin && hasCostSearchAdmin;
  if (staffPassed && adminPassed) {
    console.log('🎉 ALL TASK 1 PASS CRITERIA VERIFIED 100% SUCCESSFUL!');
  } else {
    console.log('⚠️ TASK 1 VERIFICATION FAILED (Staff passed:', staffPassed, ', Admin passed:', adminPassed, ')');
  }
  console.log('=================================================================');
}

runTests().catch(console.error);
