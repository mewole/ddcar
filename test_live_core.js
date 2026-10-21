const http = require('http');

const host = 'ddaccessory.trueddns.com';
const port = 24330;
const basePath = '/dd-shop/';

const corePages = [
    { name: '1. หน้านำเข้าสินค้า', file: 'web_import.html' },
    { name: '2. รายงานสินค้าเข้าโกดัง', file: 'web_mng_import_warehouse.html' },
    { name: '3. รายงานสินค้าเข้าหน้าร้าน', file: 'web_mng_import.html' },
    { name: '4. ระบบขายสินค้า', file: 'web_sale.html' },
    { name: '5. รายงานสรุปยอดขาย', file: 'web_mng_sale.html' },
    { name: '6. ระบบโอนย้ายสินค้า', file: 'web_transfer_in.html' },
    { name: '7. รายงานการโอนย้ายสินค้า', file: 'web_mng_transfer.html' },
    { name: '8. ระบบสต๊อกสินค้า', file: 'web_stock.html' },
    { name: '9. ระบบส่งออกสินค้า', file: 'web_export.html' },
    { name: '10. รายงานการส่งออก', file: 'web_mng_export.html' },
    { name: '11. ระบบปรับยอดสต๊อก', file: 'web_mng_adjust.html' },
    { name: '12. ระบบลูกหนี้/เครดิต', file: 'web_credit.html' },
    { name: '13. ระบบจัดการบิลย้อนหลัง', file: 'web_mng_bill.html' },
    { name: '14. ระบบจัดการลูกค้า', file: 'web_customer.html' },
    { name: '15. ระบบจัดการผู้ใช้/พนักงาน', file: 'web_admin_user.html' },
    { name: '16. ระบบแจ้งเตือนสินค้า', file: 'web_mng_bell.html' },
    { name: '17. ระบบพิมพ์บาร์โค้ด QR', file: 'web_qrprint.html' },
    { name: '18. โฟลเดอร์สินค้า', file: 'web_folder.html' },
    { name: '19. จัดการคลัง/หน้าร้าน', file: 'web_warehouse.html' }
];

const today = new Date().toISOString().slice(0, 10);
const coreApis = [
    { name: 'ดึงรายชื่อโกดัง/หน้าร้าน', file: 'php/get_ware_shop.php', method: 'GET' },
    { name: 'ดึงรายชื่อร้านค้า/Supplier', file: 'php/get_suppliers.php', method: 'GET' },
    { name: 'ประวัติราคา Supplier แต่ละร้าน', file: 'php/get_supplier_prices.php', method: 'POST', body: 'prod_id=1' },
    { name: 'ค้นหาสินค้าตาม ID/บาร์โค้ด', file: 'php/get_prod.php', method: 'POST', body: 'prod_id=1&ware_id=1&type=ware' },
    { name: 'แคตตาล็อกสินค้า Autocomplete', file: 'php/get_autoprod.php', method: 'POST', body: '' },
    { name: 'ดึงประวัติบิลนำเข้า', file: 'php/get_import.php', method: 'POST', body: 'start=0' },
    { name: 'ดึงประวัติการโอนย้าย', file: 'php/get_transfer.php', method: 'POST', body: 'start=0' },
    { name: 'ดึงรายการโอนย้ายไปสาขา', file: 'php/get_transfer_to.php', method: 'POST', body: 'start=0' },
    { name: 'สรุปยอดขายรายวัน/เดือน/ปี', file: 'php/get_sales_summary.php', method: 'POST', body: 'mode=day&date=' + today },
    { name: 'สรุปกะยอดขาย', file: 'php/get_shift_bill.php', method: 'POST', body: 'date=' + today },
    { name: 'ดึงรายการบิลขาย', file: 'php/get_bill.php', method: 'POST', body: 'start=0' },
    { name: 'ดึงรายการส่งออกสินค้า', file: 'php/get_export.php', method: 'POST', body: 'start=0' },
    { name: 'ดึงข้อมูลลูกหนี้/เครดิต', file: 'php/get_credit.php', method: 'POST', body: 'val=' },
    { name: 'ดึงสินค้าใกล้หมดสต๊อก', file: 'php/get_low_stock.php', method: 'POST', body: 'mode=reorder' },
    { name: 'ดึงประวัติปรับยอดสต๊อก', file: 'php/get_stock_adjustments.php', method: 'POST', body: 'start=0' }
];

function requestUrl(item, timeoutMs = 8000) {
    return new Promise((resolve) => {
        const start = Date.now();
        const options = {
            hostname: host,
            port: port,
            path: basePath + item.file,
            method: item.method || 'GET',
            timeout: timeoutMs,
            headers: {}
        };

        if (options.method === 'POST') {
            options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            options.headers['Content-Length'] = Buffer.byteLength(item.body || '');
        }

        const req = http.request(options, (res) => {
            let data = '';
            res.on('data', c => data += c);
            res.on('end', () => {
                const duration = Date.now() - start;
                let statusText = 'ใช้ได้ปกติ';
                if (res.statusCode !== 200) {
                    statusText = 'ไม่ทำงาน (HTTP ' + res.statusCode + ')';
                } else if (data.includes('Fatal error') || data.includes('Parse error')) {
                    statusText = 'ไม่ทำงาน (PHP Error)';
                } else if (duration > 3500) {
                    statusText = 'ช้า (' + duration + 'ms)';
                }

                resolve({
                    name: item.name,
                    file: item.file,
                    httpCode: res.statusCode,
                    duration,
                    size: data.length,
                    statusText,
                    error: null
                });
            });
        });

        req.on('timeout', () => {
            req.destroy();
            resolve({
                name: item.name,
                file: item.file,
                httpCode: 'TIMEOUT',
                duration: timeoutMs,
                size: 0,
                statusText: 'ค้าง (> ' + (timeoutMs/1000) + ' วินาที)',
                error: 'Timeout'
            });
        });

        req.on('error', (err) => {
            resolve({
                name: item.name,
                file: item.file,
                httpCode: 'ERR',
                duration: Date.now() - start,
                size: 0,
                statusText: 'เชื่อมต่อไม่ได้ (' + err.message + ')',
                error: err.message
            });
        });

        if (options.method === 'POST') {
            req.write(item.body || '');
        }
        req.end();
    });
}

async function main() {
    console.log('Testing Core Web Pages (19 Pages)...');
    const pageResults = [];
    for (const p of corePages) {
        const res = await requestUrl(p, 6000);
        pageResults.push(res);
    }

    console.log('Testing Core Backend APIs (15 APIs)...');
    const apiResults = [];
    for (const a of coreApis) {
        const res = await requestUrl(a, 6000);
        apiResults.push(res);
    }

    console.log(JSON.stringify({ pageResults, apiResults }, null, 2));
}

main().catch(console.error);
