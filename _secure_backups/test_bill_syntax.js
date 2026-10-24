const fs = require('fs');

const localBill = fs.readFileSync('c:/ddcar/www/web_mng_bill.html', 'utf8');
const matches = localBill.match(/<script[\s\S]*?<\/script>/gi);
console.log('Found script tags:', matches ? matches.length : 0);
if (matches) {
    matches.forEach((m, idx) => {
        const tag = m.substring(0, m.indexOf('>') + 1);
        const code = m.replace(/<script[\s\S]*?>/i, '').replace(/<\/script>/i, '');
        console.log('Script ' + (idx+1) + ': ' + tag + ' (Length: ' + code.length + ')');
        if (code.trim() && !tag.includes('src=')) {
            try {
                new Function(code);
                console.log('  -> Syntax VALID!');
            } catch (e) {
                console.error('  -> Syntax ERROR:', e.message);
            }
        }
    });
}
