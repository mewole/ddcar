const http = require('http');
const qs = require('querystring');

async function testSearchUser(username, password, expectCost) {
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

  const searchPost = qs.stringify({ phrase: 'ก', limit: 5 });
  const searchData = await new Promise((resolve, reject) => {
    const req = http.request({
      hostname: '100.77.57.16',
      port: 8083,
      path: '/dd-shop/php/search_prod.php',
      method: 'POST',
      headers: {
        'Cookie': loginRes.cookie,
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(searchPost)
      }
    }, res => {
      let b = '';
      res.on('data', c => b += c);
      res.on('end', () => resolve(JSON.parse(b)));
    });
    req.on('error', reject);
    req.write(searchPost);
    req.end();
  });

  const item = Array.isArray(searchData) ? searchData[0] : (searchData && searchData.data ? searchData.data[0] : null);
  if (!item) {
    console.log(`User [${username}] -> searchData:`, searchData);
    return;
  }
  const hasCost = ('cost' in item || 'prod_cost' in item);
  console.log(`User [${username}] -> Has Cost in search_prod: ${hasCost ? 'YES' : 'NO'} | Expected: ${expectCost ? 'YES' : 'NO'} | Result: ${hasCost === expectCost ? '✅ PASS' : '❌ FAIL'}`);
  if (hasCost) {
    console.log(`   prod_cost = ฿${item.prod_cost}`);
  }
}

async function run() {
  console.log('Testing search_prod.php for both roles:');
  await testSearchUser('test_staff', 'Staff#2026@dd', false);
  await testSearchUser('test_admin', 'Admin#2026@dd', true);
}

run().catch(console.error);
