const http = require('http');

function testUrl(url, method = 'GET', postData = null, cookie = null) {
  return new Promise((resolve) => {
    const u = new URL(url);
    const opts = {
      hostname: u.hostname,
      port: u.port,
      path: u.pathname + u.search,
      method: method,
      headers: {}
    };
    if (cookie) opts.headers['Cookie'] = cookie;
    if (postData) {
      opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
      opts.headers['Content-Length'] = Buffer.byteLength(postData);
    }
    const req = http.request(opts, (res) => {
      let b = '';
      res.on('data', c => b += c);
      res.on('end', () => {
        resolve({
          status: res.statusCode,
          length: b.length,
          body: b,
          cookies: res.headers['set-cookie']
        });
      });
    });
    req.on('error', (err) => resolve({ error: err.message }));
    if (postData) req.write(postData);
    req.end();
  });
}

async function verifyCashierWorkflows() {
  console.log('=== VERIFYING PRODUCTION CASHIER & STORE WORKFLOWS ===\n');

  // 1. Login as cashier staff
  const login = await testUrl('http://100.77.57.16:8083/dd-shop/php/login.php', 'POST', 'name=Song&pass=1111');
  console.log('1. Cashier Login Status:', login.status, 'Body:', login.body.trim());
  const cookie = (login.cookies && login.cookies.length > 0) ? login.cookies[0].split(';')[0] : null;

  // 2. Test Core Cashier Pages
  const pages = [
    'http://100.77.57.16:8083/dd-shop/web_sale.html',
    'http://100.77.57.16:8083/dd-shop/web_stock.html',
    'http://100.77.57.16:8083/dd-shop/web_mng_bill.html',
    'http://100.77.57.16:8083/dd-shop/web_return_claim.html',
    'http://100.77.57.16:8083/dd-shop/js/navbar.js?v=3.5'
  ];

  for (const p of pages) {
    const res = await testUrl(p);
    const pageName = p.split('/').pop();
    console.log(`[PAGE] ${pageName} -> Status: ${res.status}, Length: ${res.length}`);
  }

  // 3. Test POS Product Search (Used during cashier typing product code or name)
  const searchProd = await testUrl('http://100.77.57.16:8083/dd-shop/php/search_prod.php', 'POST', 'value=กระจังหน้า&ware_id=1&type=หน้าร้าน', cookie);
  console.log('\n3. POS Product Search Status:', searchProd.status, 'Response length:', searchProd.length);

  // 4. Test Bill Search & Management (Used in web_mng_bill.html)
  const getBill = await testUrl('http://100.77.57.16:8083/dd-shop/php/get_bill.php', 'POST', 'start=0', cookie);
  let billSample = null;
  try {
    const bills = JSON.parse(getBill.body);
    billSample = bills[1] || bills[0];
    console.log('4. Bill List Status:', getBill.status, 'Total returned:', bills.length, 'Sample Order:', billSample ? billSample.order_number : 'none');
  } catch(e) {
    console.log('4. Bill List Error:', e.message);
  }

  // 5. Test Bill Details (Used when cashier clicks to expand bill or print receipt)
  if (billSample && billSample.order_id) {
    const billInfo = await testUrl('http://100.77.57.16:8083/dd-shop/php/get_bill_info.php', 'POST', `bill_id=${billSample.order_id}`, cookie);
    console.log('5. Bill Info/Print Status:', billInfo.status, 'Response length:', billInfo.length);
  }

  // 6. Test External TrueDDNS Access (Store cashier internet link)
  console.log('\n6. Testing External TrueDDNS Link...');
  const ext = await testUrl('http://ddaccessory.trueddns.com:24330/dd-shop/web_return_claim.html');
  console.log('   External TrueDDNS Status:', ext.status, 'Length:', ext.length);

  console.log('\n=== PRE-FLIGHT VERIFICATION COMPLETE ===');
}

verifyCashierWorkflows().catch(console.error);
