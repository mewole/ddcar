const http = require('http');

const host = 'ddaccessory.trueddns.com';
const port = 24330;
const basePath = '/dd-shop/';

const tests = [
    {
        name: '1. หน้าเว็บหลัก (web_stock.html)',
        url: 'web_stock.html',
        method: 'GET'
    },
    {
        name: '2. ค้นหาสินค้าพร้อมสต๊อก (search_prod.php)',
        url: 'php/search_prod.php',
        method: 'POST',
        body: 'phrase=ไฟ&with_stock=1'
    },
    {
        name: '3. ดึงรูปภาพสินค้า (search_img.php)',
        url: 'php/search_img.php',
        method: 'POST',
        body: 'prod_id=1'
    },
    {
        name: '4. เช็คจำนวนสต๊อกแยกสาขา (check_amt_prod.php)',
        url: 'php/check_amt_prod.php',
        method: 'POST',
        body: 'prod_id=1'
    },
    {
        name: '5. ประวัติราคา Supplier (get_supplier_prices.php)',
        url: 'php/get_supplier_prices.php',
        method: 'POST',
        body: 'prod_id=1'
    },
    {
        name: '6. สินค้าใกล้หมด (get_low_stock.php?mode=reorder)',
        url: 'php/get_low_stock.php?mode=reorder',
        method: 'GET'
    },
    {
        name: '7. สินค้าสต๊อกเป็น 0 (get_low_stock.php?mode=zero)',
        url: 'php/get_low_stock.php?mode=zero',
        method: 'GET'
    },
    {
        name: '8. ดึงสต๊อกสำหรับปรับยอด (check_amt_prod.php)',
        url: 'php/check_amt_prod.php',
        method: 'POST',
        body: 'prod_id=1'
    }
];

function runTest(item, timeoutMs = 8000) {
    return new Promise((resolve) => {
        const start = Date.now();
        const options = {
            hostname: host,
            port: port,
            path: encodeURI(basePath + item.url),
            method: item.method,
            timeout: timeoutMs,
            headers: {}
        };

        if (item.method === 'POST') {
            options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            options.headers['Content-Length'] = Buffer.byteLength(item.body || '');
        }

        const req = http.request(options, (res) => {
            let data = '';
            res.on('data', c => data += c);
            res.on('end', () => {
                const duration = Date.now() - start;
                let status = 'ใช้งานได้ปกติ';
                let isJson = false;
                let jsonCount = null;

                try {
                    const parsed = JSON.parse(data);
                    isJson = true;
                    if (Array.isArray(parsed)) jsonCount = parsed.length;
                    else if (parsed.items && Array.isArray(parsed.items)) jsonCount = parsed.items.length;
                } catch(e) {}

                if (res.statusCode !== 200) {
                    status = 'ไม่ทำงาน (HTTP ' + res.statusCode + ')';
                } else if (data.includes('Fatal error') || data.includes('Parse error')) {
                    status = 'ไม่ทำงาน (PHP Error)';
                } else if (duration > 3500) {
                    status = 'ช้า (' + duration + ' ms)';
                }

                resolve({
                    name: item.name,
                    httpCode: res.statusCode,
                    duration,
                    size: data.length,
                    status,
                    isJson,
                    jsonCount,
                    preview: data.slice(0, 100).replace(/[\r\n\t]+/g, ' ')
                });
            });
        });

        req.on('timeout', () => {
            req.destroy();
            resolve({
                name: item.name,
                httpCode: 'TIMEOUT',
                duration: timeoutMs,
                size: 0,
                status: 'ค้าง (> ' + (timeoutMs/1000) + ' วินาที)',
                preview: ''
            });
        });

        req.on('error', (err) => {
            resolve({
                name: item.name,
                httpCode: 'ERR',
                duration: Date.now() - start,
                size: 0,
                status: 'เชื่อมต่อไม่ได้ (' + err.message + ')',
                preview: ''
            });
        });

        if (item.method === 'POST') {
            req.write(item.body || '');
        }
        req.end();
    });
}

async function start() {
    console.log('Testing all endpoints used by web_stock.html...\n');
    for (const test of tests) {
        const result = await runTest(test);
        const icon = result.status.includes('ปกติ') ? '✅' : (result.status.includes('ช้า') ? '⚠️' : '❌');
        console.log(`${icon} ${result.name}`);
        console.log(`   - สถานะ: ${result.status} [HTTP ${result.httpCode}]`);
        console.log(`   - ความเร็ว: ${result.duration} ms | ขนาด: ${result.size} bytes` + (result.jsonCount !== null ? ` | ข้อมูล: ${result.jsonCount} รายการ` : ''));
        console.log(`   - ตัวอย่าง Response: ${result.preview}\n`);
    }
}

start().catch(console.error);
