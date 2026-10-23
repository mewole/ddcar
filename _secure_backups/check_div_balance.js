const fs = require('fs');
const html = fs.readFileSync('c:/ddcar/www/web_stock.html', 'utf8');
const p1 = html.indexOf('id="EditModal"');
const p2 = html.indexOf('id="AdjustStockModal"');
const slice = html.substring(p1, p2);
const opens = (slice.match(/<div[\s>]/gi) || []).length;
const closes = (slice.match(/<\/div>/gi) || []).length;
console.log('p1:', p1, 'p2:', p2);
console.log('EditModal open divs:', opens, 'close divs:', closes, 'Diff (unclosed):', opens - closes);
