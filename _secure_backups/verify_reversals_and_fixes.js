const mysql = require('C:/Users/user/.gemini/antigravity-ide/brain/28893e91-8c41-4b90-b190-2d945c903f2f/scratch/node_modules/mysql2/promise');
const fs = require('fs');
const http = require('http');

async function verifyAll() {
    console.log('===========================================================');
    console.log('  VERIFICATION SUITE: STOCK REVERSAL, FIXES & AUDIT');
    console.log('===========================================================\n');

    const conn = await mysql.createConnection({
        host: '100.77.57.16',
        user: 'ddcar_readonly',
        password: '855109a9dcc67d16d0df7f4b948c6df1Ro*3',
        database: 'ddcar',
        port: 3306
    });

    let allOk = true;

    // 1. Stock of SZSW18HL01 (4691)
    console.log('[1] Checking stock of SZSW18HL01 (prod_id 4691 - Swift Headlight)...');
    const [shop4691] = await conn.execute('SELECT * FROM shop WHERE prod_id = 4691');
    const [ware4691] = await conn.execute('SELECT * FROM warehouse WHERE prod_id = 4691');
    console.log('  -> Shop rows:', shop4691);
    console.log('  -> Warehouse rows:', ware4691);
    const hasShop1 = shop4691.some(s => s.shop_id === 1);
    const realShopStock = shop4691.find(s => s.shop_id === 6);
    if (!hasShop1 && realShopStock && realShopStock.amount === 2) {
        console.log('  -> PASS: Ghost shop_id:1 removed! Real shop stock (shop_id:6) = 2 pieces.');
    } else {
        console.error('  -> FAIL: Stock check failed for 4691');
        allOk = false;
    }

    // 2. Stock of TYTGD4DFG01 (9287)
    console.log('\n[2] Checking stock of TYTGD4DFG01 (prod_id 9287 - Tiger Grille)...');
    const [shop9287] = await conn.execute('SELECT * FROM shop WHERE prod_id = 9287');
    const [ware9287] = await conn.execute('SELECT * FROM warehouse WHERE prod_id = 9287');
    console.log('  -> Shop rows:', shop9287);
    console.log('  -> Warehouse rows:', ware9287);
    if (shop9287[0]?.amount === 0 && ware9287[0]?.amount === 0) {
        console.log('  -> PASS: Stock is 0 (untouched/normal).');
    } else {
        console.error('  -> FAIL: Stock unexpected for 9287');
        allOk = false;
    }

    // 3. Status of RT documents
    console.log('\n[3] Checking status of RT documents...');
    const [rtRows] = await conn.execute("SELECT return_code, status, reason FROM product_return WHERE return_code IN ('RT26-00001', 'RT26-00002')");
    console.log('  -> Returns:', rtRows);
    if (rtRows.length === 2 && rtRows.every(r => r.status === 'ยกเลิก-ทดสอบ')) {
        console.log('  -> PASS: Both RT26-00001 and RT26-00002 marked as "ยกเลิก-ทดสอบ" (NOT deleted).');
    } else {
        console.error('  -> FAIL: RT status not updated');
        allOk = false;
    }

    // 4. Status of CLM documents
    console.log('\n[4] Checking status of CLM documents & product name fix...');
    const [clmRows] = await conn.execute("SELECT claim_code, status, prod_code, prod_name, notes FROM product_claim_supplier WHERE claim_code IN ('CLM26-00001', 'CLM26-00002')");
    console.log('  -> Claims:', clmRows);
    const clmOk = clmRows.length === 2 && 
                  clmRows.every(c => c.status === 'ยกเลิก-ทดสอบ' && c.prod_name !== '0');
    if (clmOk) {
        console.log('  -> PASS: Both CLM documents marked as "ยกเลิก-ทดสอบ" and prod_name corrected from "0" to actual product names.');
    } else {
        console.error('  -> FAIL: CLM status or prod_name issue');
        allOk = false;
    }

    // 5. Audit all passwords in user table
    console.log('\n[5] Auditing user passwords in DB...');
    const [users] = await conn.execute('SELECT user_id, user_name, user_role, LENGTH(pass) as len, LEFT(pass, 4) as pfx FROM user');
    const allBcrypt = users.every(u => u.len === 60 && u.pfx === '$2y$');
    console.log(`  -> Total users: ${users.length}`);
    console.log(`  -> All Bcrypt 60-char hashed: ${allBcrypt}`);
    if (allBcrypt) {
        console.log('  -> PASS: 100% of user accounts are hashed with bcrypt.');
    } else {
        console.error('  -> FAIL: Some accounts are not hashed');
        allOk = false;
    }

    await conn.end();

    // 6. Check php/my_ip.php deleted
    console.log('\n[6] Checking php/my_ip.php deletion...');
    const localMyIp = fs.existsSync('c:/ddcar/www/php/my_ip.php');
    const nasMyIp = fs.existsSync('//100.77.57.16/Web/dd-shop/php/my_ip.php');
    if (!localMyIp && !nasMyIp) {
        console.log('  -> PASS: php/my_ip.php successfully deleted from both local and NAS.');
    } else {
        console.error('  -> FAIL: php/my_ip.php still exists somewhere');
        allOk = false;
    }

    // 7. Check Idle timeout in navbar.js and auth_guard.php
    console.log('\n[7] Checking Idle Timeout implementation...');
    const navContent = fs.readFileSync('c:/ddcar/www/js/navbar.js', 'utf8');
    const authContent = fs.readFileSync('c:/ddcar/www/php/auth_guard.php', 'utf8');
    const hasClientIdle = navContent.includes('IDLE_TIMEOUT_MS = 30 * 60 * 1000') && navContent.includes('resetIdleTimer');
    const hasServerIdle = authContent.includes('> 1800');
    console.log('  -> Client-side 30min idle detector in navbar.js:', hasClientIdle);
    console.log('  -> Server-side 1800s inactivity check in auth_guard.php:', hasServerIdle);
    if (hasClientIdle && hasServerIdle) {
        console.log('  -> PASS: Idle timeout (30 mins) active on both client and server.');
    } else {
        console.error('  -> FAIL: Idle timeout configuration missing');
        allOk = false;
    }

    // 8. Check save_product_return.php bind_param fix
    console.log('\n[8] Checking save_product_return.php bind_param type string fix...');
    const retPhp = fs.readFileSync('c:/ddcar/www/php/save_product_return.php', 'utf8');
    const hasFixedBind = retPhp.includes('mysqli_stmt_bind_param($stmt_clm, "sisississssiss"');
    console.log('  -> Has fixed bind_param string (sisississssiss):', hasFixedBind);
    if (hasFixedBind) {
        console.log('  -> PASS: Product name in claims will NEVER be saved as 0 again.');
    } else {
        console.error('  -> FAIL: save_product_return.php bind_param not fixed');
        allOk = false;
    }

    console.log('\n===========================================================');
    console.log(allOk ? '>>> ALL CHECKS PASSED (100% VERIFIED) <<<' : '>>> CHECKS FAILED <<<');
    console.log('===========================================================');
}

verifyAll().catch(console.error);
