const http = require('http');
const qs = require('querystring');

async function testUser(username, password, expectCost) {
  const postData = qs.stringify({ name: username, pass: password });
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
      let b = '';
      res.on('data', c => b += c);
      res.on('end', () => resolve({ cookie: res.headers['set-cookie'][0].split(';')[0] }));
    });
    req.on('error', reject);
    req.write(postData);
    req.end();
  });

  const resBody = await new Promise((resolve, reject) => {
    http.get('http://100.77.57.16:8083/dd-shop/php/get_bill_details_for_return.php?keyword=S26-13631', {
      headers: { 'Cookie': loginRes.cookie }
    }, res => {
      let b = '';
      res.on('data', c => b += c);
      res.on('end', () => resolve(b));
    }).on('error', reject);
  });

  const data = JSON.parse(resBody);
  const item = data.bill.items[0];
  const hasCost = ('cost' in item || 'prod_cost' in item);
  console.log(`User [${username}] -> Has Cost: ${hasCost ? 'YES' : 'NO'} | Expected: ${expectCost ? 'YES' : 'NO'} | Result: ${hasCost === expectCost ? '✅ PASS' : '❌ FAIL'}`);
  if (hasCost) {
    console.log(`   Cost value: ฿${item.cost}`);
  }
}

async function run() {
  console.log('Testing get_bill_details_for_return.php for both roles:');
  await testUser('test_staff', 'Staff#2026@dd', false);
  await testUser('test_admin', 'Admin#2026@dd', true);
}

run().catch(console.error);
