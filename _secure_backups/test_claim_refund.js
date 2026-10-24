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

async function run() {
  const loginRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/login.php',
    method: 'POST'
  }, 'name=Song&pass=1111');
  const cookie = loginRes.headers['set-cookie'][0].split(';')[0];

  console.log('Testing resolve_refund on claim id: 2 (CLM26-00002)...');
  const refRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/update_claim_status.php',
    method: 'POST',
    headers: { 'Cookie': cookie }
  }, {
    claim_id: 2,
    action: 'resolve_refund',
    refund_amount: 850.00,
    notes: 'ซัพพลายเออร์โอนเงินคืน ฿850 แทนสินค้า'
  });
  console.log('Resolve Refund Result:', refRes.body);

  const claimsRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/get_supplier_claims.php?status=all',
    method: 'GET',
    headers: { 'Cookie': cookie }
  });
  const summary = JSON.parse(claimsRes.body).summary;
  console.log('Final Claims Summary:', summary);
}

run().catch(console.error);
