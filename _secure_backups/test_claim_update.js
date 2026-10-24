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

  const claimsRes = await httpRequest({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/get_supplier_claims.php?status=sent_pending',
    method: 'GET',
    headers: { 'Cookie': cookie }
  });
  console.log('claimsRes body:', claimsRes.body);
  const data = JSON.parse(claimsRes.body);
  if (data.data && data.data.length > 0) {
    const claim = data.data[0];
    const claimId = claim.id || claim.claim_id;
    console.log('Testing claim status update on id:', claimId, 'claim_code:', claim.claim_code);

    // 1. Mark sent
    const sendRes = await httpRequest({
      hostname: '100.77.57.16',
      port: 8083,
      path: '/dd-shop/php/update_claim_status.php',
      method: 'POST',
      headers: { 'Cookie': cookie }
    }, {
      claim_id: claimId,
      action: 'mark_sent',
      supplier_ref_no: 'TEST-REF-001',
      notes: 'ส่งให้ร้านค้าแล้ว'
    });
    console.log('Mark Sent Result:', sendRes.body);

    // 2. Resolve: received goods
    const resGoods = await httpRequest({
      hostname: '100.77.57.16',
      port: 8083,
      path: '/dd-shop/php/update_claim_status.php',
      method: 'POST',
      headers: { 'Cookie': cookie }
    }, {
      claim_id: claimId,
      action: 'resolve_received_goods',
      notes: 'ได้ของเคลมใหม่ คืนเข้าสต๊อกขายเรียบร้อย'
    });
    console.log('Resolve Goods Result:', resGoods.body);
  }
}

run().catch(console.error);
