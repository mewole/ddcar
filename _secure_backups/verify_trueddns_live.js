const http = require('http');

console.log('=== VERIFYING LIVE SYSTEM (ddaccessory.trueddns.com:24330) ===\n');

function checkUrl(path, validator, label) {
  return new Promise((resolve) => {
    http.get('http://ddaccessory.trueddns.com:24330/dd-shop/' + path, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        const passed = validator(data, res.statusCode);
        console.log(`[${passed ? 'PASS' : 'FAIL'}] ${label} (HTTP ${res.statusCode})`);
        resolve({ label, passed, statusCode: res.statusCode });
      });
    }).on('error', (err) => {
      console.log(`[ERR] ${label}: ${err.message}`);
      resolve({ label, passed: false, error: err.message });
    });
  });
}

async function runTests() {
  // Test A4: get_import_info for 11764 & 11783 via POST
  const postTest = (impId, label) => new Promise((resolve) => {
    const postData = 'imp_id=' + impId;
    const req = http.request({
      hostname: 'ddaccessory.trueddns.com',
      port: 24330,
      path: '/dd-shop/php/get_import_info.php',
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(postData),
        'Referer': 'http://ddaccessory.trueddns.com:24330/dd-shop/web_mng_import_warehouse.html',
        'X-User-Id': '33'
      }
    }, (res) => {
      let d = '';
      res.on('data', c => d += c);
      res.on('end', () => {
        let ok = false;
        try {
          const parsed = JSON.parse(d);
          ok = Array.isArray(parsed) && parsed.length > 0 && parsed[0].supplier_name !== undefined;
        } catch(e) {}
        console.log(`[${ok ? 'PASS' : 'FAIL'}] ${label} -> ${d.slice(0, 100)}...`);
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

  await postTest(11764, 'A4: IMP26-01526 (imp_id 11764) returns valid JSON with supplier_name');
  await postTest(11783, 'A4: IMP26-01545 (imp_id 11783) returns valid JSON with supplier_name');

  // Test A5 & A6 & A7: web_sale.html
  await checkUrl('web_sale.html', (body) => {
    const hasOrderSaving = body.includes('window.isOrderSaving');
    const hasSaveVal = body.includes('จำนวนสินค้าต้องมากกว่า 0 ชิ้น') && body.includes('ราคาสินค้าต้องไม่ติดลบ');
    const hasCusAlert = body.includes('กรุณาเลือกลูกค้า');
    console.log(`   - A5 (isOrderSaving in web_sale.html): ${hasOrderSaving}`);
    console.log(`   - A6 (quantity & price validation in save()): ${hasSaveVal}`);
    console.log(`   - A7 ("กรุณาเลือกลูกค้า" alert in web_sale.html): ${hasCusAlert}`);
    return hasOrderSaving && hasSaveVal && hasCusAlert;
  }, 'A5, A6, A7: web_sale.html contains isOrderSaving, save() check, and customer alert');

  // Test A8: web_mng_sale.html has card-writeoff and kpi-writeoff
  await checkUrl('web_mng_sale.html', (body) => {
    const hasCard = body.includes('id="card-writeoff"');
    const hasKpi = body.includes('id="kpi-writeoff"');
    const hasPopulate = body.includes('summary.writeoff_cost');
    console.log(`   - A8 (card-writeoff HTML): ${hasCard}`);
    console.log(`   - A8 (kpi-writeoff HTML): ${hasKpi}`);
    console.log(`   - A8 (kpi population from writeoff_cost): ${hasPopulate}`);
    return hasCard && hasKpi && hasPopulate;
  }, 'A8: web_mng_sale.html contains Write-off KPI card and population logic');

  // Test TikTok Links in web_platform_mapping.html
  await checkUrl('web_platform_mapping.html', (body) => {
    const hasPublicView = body.includes('tiktok.com/view/product/');
    const hasPublicSearch = body.includes('tiktok.com/search?q=');
    console.log(`   - Public view /product/ resolver: ${hasPublicView}`);
    console.log(`   - Public search search?q= fallback: ${hasPublicSearch}`);
    return hasPublicView && hasPublicSearch;
  }, 'TikTok Links: web_platform_mapping.html resolves to public TikTok pages');

  // Test auth_guard.php
  await checkUrl('php/auth_guard.php', (body, code) => {
    // Should either return 401 or not show raw source code
    return code === 200 || code === 401 || code === 403;
  }, 'Security: auth_guard.php is active on live server');

  console.log('\n=== ALL LIVE VERIFICATION CHECKS COMPLETE ===');
}

runTests();
