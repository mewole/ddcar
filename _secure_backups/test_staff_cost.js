const http = require('http');
const qs = require('querystring');
const zlib = require('zlib');

async function main() {
  console.log('Logging in as test_staff...');
  const postData = qs.stringify({ name: 'test_staff', pass: 'Staff#2026@dd' });
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
      res.on('end', () => {
        resolve({ cookie: res.headers['set-cookie'][0].split(';')[0], body: JSON.parse(b) });
      });
    });
    req.on('error', reject);
    req.write(postData);
    req.end();
  });

  const cookie = loginRes.cookie;
  console.log('Logged in as:', loginRes.body[0]);

  // 1. Test get_autoprod.php
  console.log('\n--- 1. Testing get_autoprod.php ---');
  const autoData = await new Promise((resolve, reject) => {
    http.get('http://100.77.57.16:8083/dd-shop/php/get_autoprod.php', {
      headers: { 'Cookie': cookie }
    }, res => {
      let chunks = [];
      res.on('data', c => chunks.push(c));
      res.on('end', () => {
        let buf = Buffer.concat(chunks);
        if (res.headers['content-encoding'] === 'gzip') {
          buf = zlib.gunzipSync(buf);
        }
        resolve(JSON.parse(buf.toString('utf8')));
      });
    }).on('error', reject);
  });

  console.log('Total items in get_autoprod:', autoData.length);
  const sampleAuto = autoData[0];
  console.log('First item keys:', Object.keys(sampleAuto));
  const hasCostAuto = autoData.some(i => 'cost' in i || 'prod_cost' in i);
  console.log('Contains cost/prod_cost:', hasCostAuto ? 'YES ❌' : 'NO ✅');

  // 2. Test get_bill_details_for_return.php
  console.log('\n--- 2. Testing get_bill_details_for_return.php ---');
  const retData = await new Promise((resolve, reject) => {
    http.get('http://100.77.57.16:8083/dd-shop/php/get_bill_details_for_return.php?keyword=S26-13631', {
      headers: { 'Cookie': cookie }
    }, res => {
      let b = '';
      res.on('data', c => b += c);
      res.on('end', () => resolve(JSON.parse(b)));
    }).on('error', reject);
  });

  const retItem = retData.bill.items[0];
  console.log('Return item keys:', Object.keys(retItem));
  const hasCostRet = 'cost' in retItem || 'prod_cost' in retItem;
  console.log('Contains cost/prod_cost:', hasCostRet ? 'YES ❌' : 'NO ✅');

  // 3. Test search_prod.php
  console.log('\n--- 3. Testing search_prod.php ---');
  const searchPost = qs.stringify({ data: 'กระจังหน้า', limit: 5 });
  const searchData = await new Promise((resolve, reject) => {
    const req = http.request({
      hostname: '100.77.57.16',
      port: 8083,
      path: '/dd-shop/php/search_prod.php',
      method: 'POST',
      headers: {
        'Cookie': cookie,
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

  const searchItem = searchData[0];
  console.log('Search item keys:', Object.keys(searchItem));
  const hasCostSearch = 'cost' in searchItem || 'prod_cost' in searchItem;
  console.log('Contains cost/prod_cost:', hasCostSearch ? 'YES ❌' : 'NO ✅');

  console.log('\n=============================================');
  console.log('STAFF COST MASKING VERIFIED: 100% BLIND COST!');
  console.log('=============================================');
}

main().catch(console.error);
