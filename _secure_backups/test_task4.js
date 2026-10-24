const http = require('http');
const qs = require('querystring');

async function run() {
  console.log('Testing Task 4: Warehouse to Warehouse transfer...');

  // 1. Login as test_admin
  const postData = qs.stringify({ name: 'test_admin', pass: 'Admin#2026@dd' });
  const loginRes = await new Promise((resolve, reject) => {
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
      resolve({ cookie: res.headers['set-cookie'][0].split(';')[0] });
    });
    req.on('error', reject);
    req.write(postData);
    req.end();
  });

  // 2. Test create_transfer with same source & destination (should fail)
  const invalidTfPost = qs.stringify({
    JSON: JSON.stringify([{ prod_id: 11382, amt: 1, price: 100 }]),
    direction: 'ware_to_ware',
    from_id: 1,
    to_id: 1,
    comment: 'Test same ware'
  });

  const failRes = await new Promise((resolve, reject) => {
    const req = http.request({
      hostname: '100.77.57.16',
      port: 8083,
      path: '/dd-shop/php/create_transfer.php',
      method: 'POST',
      headers: {
        'Cookie': loginRes.cookie,
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(invalidTfPost)
      }
    }, res => {
      let b = '';
      res.on('data', c => b += c);
      res.on('end', () => resolve(JSON.parse(b)));
    });
    req.on('error', reject);
    req.write(invalidTfPost);
    req.end();
  });

  console.log('Attempted ware_to_ware with same from_id and to_id:', failRes);
  const passValidation = (failRes.status === 'error' && failRes.message.includes('เดียวกัน'));
  console.log(`Validation same source/dest: ${passValidation ? '✅ PASS' : '❌ FAIL'}`);
}

run().catch(console.error);
