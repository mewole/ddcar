const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = 8899;
const WWW_ROOT = path.join(__dirname, 'www');

const server = http.createServer((req, res) => {
    const url = new URL(req.url, `http://localhost:${PORT}`);
    const pathname = url.pathname;

    // Enable CORS
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');

    if (req.method === 'OPTIONS') {
        res.writeHead(200);
        res.end();
        return;
    }

    // Mock API endpoints for local testing
    if (pathname === '/php/get_ware_shop.php') {
        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify([
            { ware_id: "1", ware_name: "โกดังหลัก (คลังใหญ่)", "2": "โกดัง" },
            { shop_id: "2", shop_name: "หน้าร้านสาขา 1", "2": "หน้าร้าน" }
        ]));
        return;
    }

    if (pathname === '/php/get_autoprod.php') {
        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify([
            { prod_id: "101", prod_name: "สเกิร์ตหน้า Civic FE สไตล์ Type R", prod_code: "FE-001", prod_price: "2500", prod_pricesend: "1800", prod_cost: "1200" },
            { prod_id: "102", prod_name: "ยางกันกระแทกประตู VIP (ชุด 4 ชิ้น)", prod_code: "VIP-88", prod_price: "350", prod_pricesend: "250", prod_cost: "100" },
            { prod_id: "103", prod_name: "ไฟเลี้ยวแก้ม LED สีส้ม Running Light", prod_code: "LED-03", prod_price: "450", prod_pricesend: "320", prod_cost: "180" }
        ]));
        return;
    }

    if (pathname === '/php/search_amount.php') {
        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify([{ amount: "20" }]));
        return;
    }

    if (pathname === '/php/search_img.php') {
        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify([{ img: "img/logo.jpg" }]));
        return;
    }

    if (pathname === '/php/get_place.php') {
        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify([
            { place: "ชั้น 2 ล็อค A1" },
            { place: "ชั้น 1 ตะกร้า B-03" }
        ]));
        return;
    }

    if (pathname === '/php/get_supplier_prices.php') {
        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify([
            { supplier: "BKK", min_cost: "100", last_cost: "100", last_date: "2026-08-15 14:30:00", order_count: 5, rank: 1, is_best: true },
            { supplier: "CNC", min_cost: "115", last_cost: "115", last_date: "2026-09-10 11:20:00", order_count: 2, rank: 2, is_best: false },
            { supplier: "TY", min_cost: "120", last_cost: "120", last_date: "2026-10-05 16:45:00", order_count: 1, rank: 3, is_best: false }
        ]));
        return;
    }

    if (pathname === '/php/get_prod.php') {
        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify([
            { prod_id: "102", prod_name: "ยางกันกระแทกประตู VIP (ชุด 4 ชิ้น)", prod_code: "VIP-88", prod_cost: "100", prod_price: "350", prod_pricesend: "250", img: "img/logo.jpg", min_amount: "5" }
        ]));
        return;
    }

    if (pathname === '/php/stock_in.php' || pathname === '/php/delete_place.php' || pathname === '/php/update_prod.php') {
        res.writeHead(200, { 'Content-Type': 'text/plain; charset=utf-8' });
        res.end("success");
        return;
    }

    // Static file serving
    let filePath = path.join(WWW_ROOT, pathname === '/' ? 'web_import.html' : pathname);

    // Fallback if not found
    if (!fs.existsSync(filePath)) {
        res.writeHead(404);
        res.end('Not Found: ' + pathname);
        return;
    }

    // Simple MIME types
    const ext = path.extname(filePath).toLowerCase();
    const mimeTypes = {
        '.html': 'text/html; charset=utf-8',
        '.js': 'application/javascript; charset=utf-8',
        '.css': 'text/css; charset=utf-8',
        '.json': 'application/json; charset=utf-8',
        '.png': 'image/png',
        '.jpg': 'image/jpeg',
        '.gif': 'image/gif',
        '.svg': 'image/svg+xml'
    };
    const contentType = mimeTypes[ext] || 'application/octet-stream';

    // Inject localStorage mock if HTML page
    if (ext === '.html') {
        let content = fs.readFileSync(filePath, 'utf8');
        const injectScript = `
        <script>
            if (!localStorage.user_id) {
                localStorage.user_id = '1';
                localStorage.user_name = 'เจ้าของร้าน (ผู้จัดการ)';
                localStorage.user_role = 'เจ้าของ';
                localStorage.ware_id_im = '1';
                localStorage.ware_name_im = 'โกดังหลัก (คลังใหญ่)';
                localStorage.type_im = 'โกดัง';
            }
        </script>
        `;
        content = content.replace('<head>', '<head>' + injectScript);
        res.writeHead(200, { 'Content-Type': contentType });
        res.end(content);
        return;
    }

    res.writeHead(200, { 'Content-Type': contentType });
    fs.createReadStream(filePath).pipe(res);
});

server.listen(PORT, () => {
    console.log(`Test dev server running on http://localhost:${PORT}`);
});
