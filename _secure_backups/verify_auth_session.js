const http = require('http');

console.log('=== VERIFYING LIVE SYSTEM WITH REAL LOGIN (Song / 1111) ===\n');

function login() {
  return new Promise((resolve, reject) => {
    const postData = 'name=Song&pass=1111';
    const req = http.request({
      hostname: 'ddaccessory.trueddns.com',
      port: 24330,
      path: '/dd-shop/php/login.php',
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(postData)
      }
    }, (res) => {
      const cookies = res.headers['set-cookie'];
      let rawCookie = '';
      if (cookies) {
        rawCookie = cookies.map(c => c.split(';')[0]).join('; ');
      }
      let d = '';
      res.on('data', c => d += c);
      res.on('end', () => {
        console.log(`[LOGIN] User 'Song' login response: ${d.slice(0, 100)}, Cookie: ${rawCookie.slice(0, 30)}...`);
        resolve(rawCookie);
      });
    });
    req.on('error', reject);
    req.write(postData);
    req.end();
  });
}

async function runTests() {
  const sessionCookie = await login();

  const testImp = (impId, label) => new Promise((resolve) => {
    const postData = 'imp_id=' + impId;
    const req = http.request({
      hostname: 'ddaccessory.trueddns.com',
      port: 24330,
      path: '/dd-shop/php/get_import_info.php',
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(postData),
        'Cookie': sessionCookie,
        'Referer': 'http://ddaccessory.trueddns.com:24330/dd-shop/web_mng_import_warehouse.html'
      }
    }, (res) => {
      let d = '';
      res.on('data', c => d += c);
      res.on('end', () => {
        let ok = false;
        try {
          const parsed = JSON.parse(d);
          ok = Array.isArray(parsed) && parsed.length > 0 && parsed[0].supplier_name !== undefined;
          if (ok) {
            const item = parsed[0];
            console.log(`[PASS] ${label}`);
            console.log(`       -> Bill: ${item.stock_number}, Supplier: "${item.supplier_name}", Product: "${item.prod_name}", Total: ${item.total_stock}, User: "${item.user_name}"`);
          } else {
            console.log(`[FAIL] ${label} -> Response: ${d}`);
          }
        } catch(e) {
          console.log(`[FAIL] ${label} -> Parse error: ${d.slice(0, 150)}`);
        }
        resolve(ok);
      });
    });
    req.on('error', e => {
      console.log(`[ERR] ${label}: ${e.message}`);
      resolve(false);
    });
    req.write(postData);
    req.end();
  });

  await testImp(11764, 'A4: IMP26-01526 (imp_id 11764) opens cleanly on TrueDDNS');
  await testImp(11783, 'A4: IMP26-01545 (imp_id 11783) opens cleanly on TrueDDNS');

  console.log('\n=== REAL END-TO-END VERIFICATION FINISHED ===');
}

runTests().catch(console.error);
