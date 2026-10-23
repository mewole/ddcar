const http = require('http');

function postReq(path, postData) {
  return new Promise((resolve) => {
    const postBody = new URLSearchParams(postData).toString();
    const req = http.request({
      hostname: '100.77.57.16',
      port: 8083,
      path: '/dd-shop/php/' + path,
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(postBody),
        'Referer': 'http://100.77.57.16:8083/dd-shop/web_sale.html',
        'X-User-Id': '33'
      }
    }, (res) => {
      let data = '';
      res.on('data', c => data += c);
      res.on('end', () => resolve({ status: res.statusCode, data }));
    });
    req.on('error', err => resolve({ error: err.message }));
    req.write(postBody);
    req.end();
  });
}

function getReq(path) {
  return new Promise((resolve) => {
    const t0 = Date.now();
    http.get({
      hostname: '100.77.57.16',
      port: 8083,
      path: '/dd-shop/php/' + path,
      headers: {
        'Referer': 'http://100.77.57.16:8083/dd-shop/web_select.html',
        'X-User-Id': '33'
      }
    }, (res) => {
      let data = '';
      res.on('data', c => data += c);
      res.on('end', () => resolve({ status: res.statusCode, timeMs: Date.now() - t0, data }));
    }).on('error', err => resolve({ error: err.message }));
  });
}

async function runTests() {
  console.log('=== RUNNING LIVE NAS VERIFICATION SUITE ===\n');

  // Test 1: get_platform_mappings.php Speed & Images
  console.log('--- Test 1: get_platform_mappings.php Speed & Fallback Images ---');
  const t1 = await getReq('get_platform_mappings.php?limit=50&page=1');
  console.log(`HTTP Status: ${t1.status} | Response Time: ${t1.timeMs}ms (was 12,481ms!)`);
  try {
    const j1 = JSON.parse(t1.data);
    console.log(`Success: ${j1.success} | Total items: ${j1.total} | Returned: ${j1.items.length}`);
    const sample = j1.items.find(i => i.matched_prod_id > 0) || j1.items[0];
    console.log('Sample matched item:', {
      id: sample.id,
      platform_sku: sample.platform_sku,
      image_url: sample.image_url,
      product_url: sample.product_url,
      matched_prod_name: sample.matched_prod_name,
      matched_img: sample.matched_img,
      total_stock: sample.total_stock
    });
  } catch(e) {
    console.log('Parse error:', e.message, t1.data.slice(0, 150));
  }

  // Test 2: get_status_prod.php Active Filter
  console.log('\n--- Test 2: get_status_prod.php Filter for Active Products ---');
  const t2_active = await postReq('get_status_prod.php', {
    rich: '1',
    filter: 'all',
    view_scope: 'active_90',
    start: '0',
    limit: '25'
  });
  console.log(`HTTP Status: ${t2_active.status}`);
  try {
    const j2 = JSON.parse(t2_active.data);
    console.log('Active 90 days KPI stats:', j2.stats);
    console.log(`Items returned: ${j2.items.length} | First item: ${j2.items[0]?.prod_name}`);
  } catch(e) {
    console.log('Parse error:', e.message, t2_active.data.slice(0, 150));
  }

  // Test 3: get_daily_reconciliation.php channel=all
  console.log('\n--- Test 3: get_daily_reconciliation.php with channel=all ---');
  const t3 = await getReq('get_daily_reconciliation.php?channel=all&date=2026-10-23');
  console.log(`HTTP Status: ${t3.status}`);
  try {
    const j3 = JSON.parse(t3.data);
    console.log('Consolidated channel info:', j3.channel);
    console.log('DDCar Sales Summary (all 4 platforms):', j3.ddcar_sales);
  } catch(e) {
    console.log('Parse error:', e.message, t3.data.slice(0, 150));
  }

  // Test 4: save_order.php Invalid Item Rejection
  console.log('\n--- Test 4: save_order.php Negative / Zero Amount Rejection ---');
  const t4 = await postReq('save_order.php', {
    JSON: JSON.stringify([{ prod_id: 1, amt: 0, price: 100 }]), // Invalid 0 amount
    JSOND: JSON.stringify([]),
    cus_id: '18',
    ware_id: '1',
    ware_id_to: '0',
    date: '2026-10-23 15:00:00',
    net: '0',
    pay: 'เงินสด',
    dis: '0',
    type: 'ขายสินค้า',
    get_price: '0',
    chng: '0',
    total: '0',
    comment: 'test reject 0 amt',
    user_id: '33',
    online: ''
  });
  console.log(`save_order response for amt=0: "${t4.data.trim()}" (Expected rejection)`);

  console.log('\n=== ALL LIVE VERIFICATION TESTS FINISHED ===');
}

runTests();
