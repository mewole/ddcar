const fs = require('fs');
const html = fs.readFileSync('c:/ddcar/www/web_stock.html', 'utf8');

// Match all $.ajax calls and URLs in web_stock.html
const regex = /url\s*:\s*["']([^"']+)["']/gi;
let m;
const urls = new Set();
while ((m = regex.exec(html)) !== null) {
    urls.add(m[1]);
}

console.log('Total URLs found in web_stock.html:', urls.size);
urls.forEach(u => console.log(' - ' + u));
