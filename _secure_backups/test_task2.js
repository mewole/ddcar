const http = require('http');
const qs = require('querystring');

async function run() {
  console.log('Testing Task 2: Junk Filter & Hidden Corrupt Items...');

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

  // 2. Query get_platform_mappings.php (should not contain corrupt items)
  const mapData = await new Promise((resolve, reject) => {
    http.get({
      hostname: '100.77.57.16',
      port: 8083,
      path: '/dd-shop/php/get_platform_mappings.php?limit=200',
      headers: { 'Cookie': loginRes.cookie }
    }, res => {
      let b = '';
      res.on('data', c => b += c);
      res.on('end', () => resolve(JSON.parse(b)));
    }).on('error', reject);
  });

  const corruptFound = (mapData.items || []).filter(item => {
    const sku = item.platform_sku || '';
    const name = item.platform_name || '';
    return sku.startsWith('{') || name.startsWith('{') || sku.includes('????') || name.includes('????');
  });

  console.log(`Corrupt items in get_platform_mappings (expected 0): ${corruptFound.length} | Result: ${corruptFound.length === 0 ? '✅ PASS' : '❌ FAIL'}`);

  // 3. Test sync_platform_catalog.php junk filter
  const testPayload = JSON.stringify({
    channel_code: 'tiktok_main',
    source: 'chrome_extension',
    products: [
      {
        platform_sku: 'TEST_JUNK_JSON',
        platform_name: '{"code":0,"msg":"malformed json"}'
      },
      {
        platform_sku: 'TEST_JUNK_CHARS',
        platform_name: 'Wee????Corrupt????'
      }
    ]
  });

  const syncRes = await new Promise((resolve, reject) => {
    const req = http.request({
      hostname: '100.77.57.16',
      port: 8083,
      path: '/dd-shop/php/sync_platform_catalog.php',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(testPayload),
        'X-Extension-Request': '1',
        'Cookie': loginRes.cookie
      }
    }, res => {
      let b = '';
      res.on('data', c => b += c);
      res.on('end', () => resolve(JSON.parse(b)));
    });
    req.on('error', reject);
    req.write(testPayload);
    req.end();
  });

  console.log('Sync response with junk items:', syncRes);
  const passFilter = (syncRes.inserted_count === 0 && syncRes.updated_count === 0);
  console.log(`Junk Filter Rejection: ${passFilter ? '✅ PASS (0 inserted, 0 updated)' : '❌ FAIL'}`);
}

run().catch(console.error);
