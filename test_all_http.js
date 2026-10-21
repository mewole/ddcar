const http = require('http');
const fs = require('fs');
const path = require('path');

const baseUrl = 'http://ddaccessory.trueddns.com:24330/dd-shop';
const host = 'ddaccessory.trueddns.com';
const port = 24330;

const wwwDir = 'c:/ddcar/www';
const htmlFiles = fs.readdirSync(wwwDir).filter(f => f.endsWith('.html'));
const phpFiles = fs.readdirSync(path.join(wwwDir, 'php')).filter(f => f.endsWith('.php'));

function testUrl(urlPath, method = 'GET', postData = null, timeoutMs = 8000) {
    return new Promise((resolve) => {
        const start = Date.now();
        const options = {
            hostname: host,
            port: port,
            path: encodeURI('/dd-shop/' + urlPath),
            method: method,
            timeout: timeoutMs,
            headers: {}
        };

        if (method === 'POST' && postData) {
            options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            options.headers['Content-Length'] = Buffer.byteLength(postData);
        }

        const req = http.request(options, (res) => {
            let data = '';
            res.on('data', chunk => { data += chunk; });
            res.on('end', () => {
                const duration = Date.now() - start;
                let hasFatal = false;
                let errorMsg = '';
                if (data.includes('Fatal error') || data.includes('Parse error') || data.includes('Uncaught Error')) {
                    hasFatal = true;
                    const match = data.match(/(Fatal error|Parse error|Uncaught [^:]+):([^\n<]+)/i);
                    errorMsg = match ? match[0] : 'PHP Fatal Error';
                }

                resolve({
                    target: urlPath,
                    status: res.statusCode,
                    duration,
                    length: data.length,
                    hasFatal,
                    errorMsg,
                    sample: data.slice(0, 150).replace(/[\r\n\t]+/g, ' ')
                });
            });
        });

        req.on('timeout', () => {
            req.destroy();
            resolve({
                target: urlPath,
                status: 'TIMEOUT',
                duration: timeoutMs,
                length: 0,
                hasFatal: true,
                errorMsg: `Timeout after ${timeoutMs}ms (ค้าง)`,
                sample: ''
            });
        });

        req.on('error', (err) => {
            resolve({
                target: urlPath,
                status: 'ERROR',
                duration: Date.now() - start,
                length: 0,
                hasFatal: true,
                errorMsg: err.message,
                sample: ''
            });
        });

        if (method === 'POST' && postData) {
            req.write(postData);
        }
        req.end();
    });
}

// Common POST payloads for stateful or required-param PHP scripts
const commonParams = {
    'get_prod.php': 'prod_id=1&ware_id=1&type=ware',
    'get_place.php': 'prod_id=1&ware_id=1',
    'search_amount.php': 'prod_id=1&ware_id=1&type=ware',
    'search_img.php': 'prod_id=1',
    'get_supplier_prices.php': 'prod_id=1',
    'get_bill.php': 'start=0',
    'get_credit.php': 'val=',
    'get_sales_summary.php': 'mode=day&date=' + new Date().toISOString().slice(0, 10),
    'get_sales_analytics.php': 'days=30',
    'get_stock_adjustments.php': 'start=0',
    'get_import.php': 'start=0',
    'get_transfer.php': 'start=0',
    'get_transfer_to.php': 'start=0',
    'get_export.php': 'start=0',
    'get_low_stock.php': 'mode=reorder',
    'get_shift_bill.php': 'date=' + new Date().toISOString().slice(0, 10),
    'get_ware_shop.php': '',
    'get_suppliers.php': '',
    'get_autoprod.php': '',
    'login.php': 'name=admin&pass=dummy'
};

async function runAll() {
    console.log(`Starting HTTP Health Check on ${baseUrl}`);
    console.log(`Testing ${htmlFiles.length} HTML files...`);
    
    const htmlResults = [];
    // Batch HTML tests in chunks of 10
    for (let i = 0; i < htmlFiles.length; i += 10) {
        const batch = htmlFiles.slice(i, i + 10);
        const res = await Promise.all(batch.map(f => testUrl(f, 'GET', null, 8000)));
        htmlResults.push(...res);
    }

    console.log(`Testing ${phpFiles.length} PHP files...`);
    const phpResults = [];
    // Batch PHP tests in chunks of 8
    for (let i = 0; i < phpFiles.length; i += 8) {
        const batch = phpFiles.slice(i, i + 8);
        const res = await Promise.all(batch.map(f => {
            const relPath = 'php/' + f;
            const param = commonParams[f];
            if (param !== undefined) {
                return testUrl(relPath, 'POST', param, 8000);
            } else {
                return testUrl(relPath, 'POST', '', 8000);
            }
        }));
        phpResults.push(...res);
    }

    fs.writeFileSync('c:/ddcar/http_test_results.json', JSON.stringify({ htmlResults, phpResults }, null, 2));

    console.log('\n======================================================');
    console.log('              SUMMARY OF HTTP HEALTH CHECK            ');
    console.log('======================================================\n');

    const htmlOk = htmlResults.filter(r => r.status === 200);
    const htmlFail = htmlResults.filter(r => r.status !== 200 || r.hasFatal);
    const htmlTimeout = htmlResults.filter(r => r.status === 'TIMEOUT');

    console.log(`--- HTML Pages (${htmlResults.length} total) ---`);
    console.log(`✅ OK: ${htmlOk.length}`);
    console.log(`⚠️ Slow (>2s): ${htmlResults.filter(r => r.status === 200 && r.duration > 2000).length}`);
    console.log(`❌ Failed / Error: ${htmlFail.length}`);
    console.log(`⏳ Hang / Timeout: ${htmlTimeout.length}`);

    if (htmlFail.length > 0) {
        console.log('\n❌ Failed HTML Pages:');
        htmlFail.forEach(f => {
            console.log(`  - [${f.status}] ${f.target} (${f.duration}ms): ${f.errorMsg || f.sample}`);
        });
    }

    const phpOk = phpResults.filter(r => r.status === 200 && !r.hasFatal);
    const phpFatal = phpResults.filter(r => r.hasFatal);
    const phpTimeout = phpResults.filter(r => r.status === 'TIMEOUT');
    const phpNon200 = phpResults.filter(r => r.status !== 200 && r.status !== 'TIMEOUT');
    const phpSlow = phpResults.filter(r => r.status === 200 && !r.hasFatal && r.duration > 2000);

    console.log(`\n--- PHP Endpoints (${phpResults.length} total) ---`);
    console.log(`✅ OK: ${phpOk.length}`);
    console.log(`⚠️ Slow (>2s): ${phpSlow.length}`);
    console.log(`❌ PHP Fatal/Parse Error: ${phpFatal.length}`);
    console.log(`❌ HTTP Error (non-200): ${phpNon200.length}`);
    console.log(`⏳ Hang / Timeout: ${phpTimeout.length}`);

    if (phpFatal.length > 0) {
        console.log('\n❌ PHP Fatal / Error Files:');
        phpFatal.forEach(f => {
            console.log(`  - [${f.status}] ${f.target} (${f.duration}ms): ${f.errorMsg}`);
        });
    }

    if (phpNon200.length > 0) {
        console.log('\n❌ PHP Non-200 Status:');
        phpNon200.forEach(f => {
            console.log(`  - [${f.status}] ${f.target} (${f.duration}ms)`);
        });
    }

    if (phpSlow.length > 0) {
        console.log('\n⚠️ Slow PHP Files (>2000ms):');
        phpSlow.forEach(f => {
            console.log(`  - [${f.duration}ms] ${f.target} (size: ${f.length} bytes)`);
        });
    }
}

runAll().catch(console.error);
