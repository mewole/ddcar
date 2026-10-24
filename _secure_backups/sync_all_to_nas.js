const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

function md5(filePath) {
    const data = fs.readFileSync(filePath);
    return crypto.createHash('md5').update(data).digest('hex');
}

const localWww = 'c:/ddcar/www';
const nasWww = '//100.77.57.16/Web/dd-shop';

console.log('--- Starting Sync from ' + localWww + ' to ' + nasWww + ' ---');

// Specific core files first
const coreFiles = [
    'js/navbar.js',
    'php/get_ware_shop.php',
    'php/test_active_bell.php',
    'web_return_claim.html',
    'web_mng_bill.html'
];

let synced = 0;
let errors = 0;

coreFiles.forEach(rel => {
    const local = path.join(localWww, rel);
    const nas = path.join(nasWww, rel);
    try {
        fs.copyFileSync(local, nas);
        const lHash = md5(local);
        const nHash = md5(nas);
        if (lHash === nHash) {
            console.log('SYNC OK (Match): ' + rel);
            synced++;
        } else {
            console.error('SYNC MISMATCH: ' + rel);
            errors++;
        }
    } catch (e) {
        console.error('SYNC ERROR for ' + rel + ':', e.message);
        errors++;
    }
});

// Sync all updated HTML files
const htmlFiles = fs.readdirSync(localWww).filter(f => f.endsWith('.html'));
htmlFiles.forEach(f => {
    const local = path.join(localWww, f);
    const nas = path.join(nasWww, f);
    try {
        const lHash = md5(local);
        let nHash = '';
        if (fs.existsSync(nas)) {
            nHash = md5(nas);
        }
        if (lHash !== nHash) {
            fs.copyFileSync(local, nas);
            const nHashAfter = md5(nas);
            if (lHash === nHashAfter) {
                console.log('SYNC OK: ' + f);
                synced++;
            } else {
                console.error('SYNC HASH MISMATCH: ' + f);
                errors++;
            }
        }
    } catch (e) {
        console.error('SYNC ERROR for ' + f + ':', e.message);
        errors++;
    }
});

console.log(`\nSync Completed! Successfully synced: ${synced} files, Errors: ${errors}`);
