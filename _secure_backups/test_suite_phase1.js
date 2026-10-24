const http = require('http');

function httpRequest(options, postData = null) {
  return new Promise((resolve, reject) => {
    if (postData) {
      options.headers = options.headers || {};
      options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
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

async function runTests() {
  console.log('=== Starting Phase 0 & Phase 1 Verification ===\n');

  // Test 1: Page Status Codes on NAS
  const pages = [
    '/dd-shop/web_return_claim.html',
    '/dd-shop/web_stock.html',
    '/dd-shop/web_mng_bill.html'
  ];

  for (const page of pages) {
    const res = await httpRequest({
      hostname: '100.77.57.16',
      port: 8083,
      path: page,
      method: 'GET'
    });
    console.log(`[HTTP GET] ${page} -> Status: ${res.statusCode} (Length: ${res.body.length})`);
  }

  // Test 2: Login
  console.log('\n--- Logging in with staff test account ---');
  const loginRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/login.php',
    method: 'POST'
  }, 'name=Song&pass=1111');

  const cookies = (loginRes.headers['set-cookie'] || []).map(c => c.split(';')[0]).join('; ');
  console.log('Login Response:', loginRes.body.trim());
  console.log('Session Cookie:', cookies);

  // Test 3: Invalid SKUs API
  console.log('\n--- Testing get_invalid_skus.php ---');
  const skuRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/get_invalid_skus.php',
    method: 'GET',
    headers: { 'Cookie': cookies }
  });
  console.log('skuRes body preview:', skuRes.body.substring(0, 300));

  // Test 4: Bill details for return (IMP26-01545)
  console.log('\n--- Testing get_bill_details_for_return.php with IMP26-01545 ---');
  const billRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/get_bill_details_for_return.php?keyword=IMP26-01545',
    method: 'GET',
    headers: { 'Cookie': cookies }
  });
  console.log('billRes body preview:', billRes.body.substring(0, 300));

  // Test 5: Get returns list
  console.log('\n--- Testing get_returns.php ---');
  const returnsRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/get_returns.php',
    method: 'GET',
    headers: { 'Cookie': cookies }
  });
  console.log('returnsRes body preview:', returnsRes.body.substring(0, 300));

  // Test 6: Get supplier claims
  console.log('\n--- Testing get_supplier_claims.php ---');
  const claimsRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/get_supplier_claims.php',
    method: 'GET',
    headers: { 'Cookie': cookies }
  });
  console.log('claimsRes body preview:', claimsRes.body.substring(0, 300));

  // Test 7: TrueDDNS External Access
  console.log('\n--- Testing TrueDDNS External Access ---');
  try {
    const extRes = await httpRequest({
      hostname: 'ddaccessory.trueddns.com',
      port: 24330,
      path: '/dd-shop/web_return_claim.html',
      method: 'GET'
    });
    console.log(`[TrueDDNS GET] web_return_claim.html -> Status: ${extRes.statusCode} (Length: ${extRes.body.length})`);
  } catch (e) {
    console.log('TrueDDNS access notice:', e.message);
  }

  console.log('\n=== All Tests Finished ===');
}

runTests().catch(console.error);
