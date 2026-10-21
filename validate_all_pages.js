const fs = require('fs');
const path = require('path');

const dir = 'c:/ddcar/www';
const files = fs.readdirSync(dir).filter(f => f.endsWith('.html') && !f.includes('_bk') && !f.includes('.bak'));

let totalFiles = 0;
let filesWithErrors = 0;
let totalScripts = 0;
let scriptErrors = 0;

for (const file of files) {
    totalFiles++;
    const filePath = path.join(dir, file);
    const html = fs.readFileSync(filePath, 'utf8');
    const regex = /<script\b[^>]*>([\s\S]*?)<\/script>/gi;
    let match;
    let scriptIdx = 0;
    let hasErr = false;

    while ((match = regex.exec(html)) !== null) {
        scriptIdx++;
        totalScripts++;
        const code = match[1];
        if (code.trim().length > 0) {
            try {
                new Function(code);
            } catch (e) {
                if (!hasErr) {
                    console.log('FILE WITH ERROR:', file);
                    hasErr = true;
                    filesWithErrors++;
                }
                console.log('  -> Script #' + scriptIdx + ' ERROR:', e.message);
                scriptErrors++;
            }
        }
    }
}

console.log('\n=== SUMMARY ===');
console.log('Total HTML files checked:', totalFiles);
console.log('Files with errors:', filesWithErrors);
console.log('Total scripts checked:', totalScripts);
console.log('Total script errors:', scriptErrors);
