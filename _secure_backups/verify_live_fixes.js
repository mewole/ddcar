const http = require('http');
const fs = require('fs');

const BASE_URL = 'http://100.77.57.16:8083/dd-shop';

function httpRequest(options, postData = null) {
    return new Promise((resolve, reject) => {
        const req = http.request(options, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                resolve({
                    statusCode: res.statusCode,
                    headers: res.headers,
                    body: data
                });
            });
        });
        req.on('error', reject);
        if (postData) {
            req.write(postData);
        }
        req.end();
    });
}

async function runVerification() {
    console.log('====================================================');
    console.log('  LIVE SYSTEM COMPREHENSIVE VERIFICATION SUITE');
    console.log('====================================================\n');

    let allPassed = true;

    // Test 1: web_mng_bill.html live HTTP and syntax
    console.log('[TEST 1] Checking web_mng_bill.html...');
    try {
        const res = await httpRequest({
            hostname: '100.77.57.16',
            port: 8083,
            path: '/dd-shop/web_mng_bill.html',
            method: 'GET'
        });
        if (res.statusCode === 200) {
            console.log('  -> HTTP Status: 200 OK');
            // Check script syntax
            const scriptMatches = res.body.match(/<script[\s\S]*?<\/script>/gi) || [];
            let syntaxErrors = 0;
            scriptMatches.forEach((m, idx) => {
                const tag = m.substring(0, m.indexOf('>') + 1);
                const code = m.replace(/<script[\s\S]*?>/i, '').replace(/<\/script>/i, '');
                if (code.trim() && !tag.includes('src=')) {
                    try {
                        new Function(code);
                    } catch (e) {
                        console.error('  -> Script syntax error in block ' + (idx + 1) + ':', e.message);
                        syntaxErrors++;
                    }
                }
            });
            if (syntaxErrors === 0) {
                console.log('  -> JS Syntax: 100% VALID across all inline scripts');
            } else {
                console.error('  -> JS Syntax FAILED with ' + syntaxErrors + ' errors');
                allPassed = false;
            }

            // Check if leftover braces are gone
            if (res.body.includes('window.location.href = "web_return_claim.html?bill_id=" + bill_id;')) {
                console.log('  -> New restore routing verified');
            }
        } else {
            console.error('  -> Failed HTTP status:', res.statusCode);
            allPassed = false;
        }
    } catch (e) {
        console.error('  -> Test 1 error:', e.message);
        allPassed = false;
    }

    // Test 2: web_return_claim.html CSS and Logo guards
    console.log('\n[TEST 2] Checking web_return_claim.html CSS & Logo styling...');
    try {
        const res = await httpRequest({
            hostname: '100.77.57.16',
            port: 8083,
            path: '/dd-shop/web_return_claim.html',
            method: 'GET'
        });
        if (res.statusCode === 200) {
            console.log('  -> HTTP Status: 200 OK');
            const hasUntitledCss = res.body.includes('css/untitled.css?v=3.6');
            console.log('  -> Contains untitled.css?v=3.6:', hasUntitledCss);
            if (!hasUntitledCss) allPassed = false;
        } else {
            console.error('  -> Failed HTTP status:', res.statusCode);
            allPassed = false;
        }

        // Check navbar.js for logo inline constraints
        const navRes = await httpRequest({
            hostname: '100.77.57.16',
            port: 8083,
            path: '/dd-shop/js/navbar.js?v=3.6',
            method: 'GET'
        });
        const hasBadgeConstraint = navRes.body.includes('max-width:40px;max-height:40px');
        const has401Handler = navRes.body.includes('AUTH_REQUIRED') && navRes.body.includes('web_login.html');
        console.log('  -> navbar.js inline logo constraints:', hasBadgeConstraint);
        console.log('  -> navbar.js global 401 redirector:', has401Handler);
        if (!hasBadgeConstraint || !has401Handler) allPassed = false;
    } catch (e) {
        console.error('  -> Test 2 error:', e.message);
        allPassed = false;
    }

    // Test 3: Unauthenticated access to get_ware_shop.php
    console.log('\n[TEST 3] Checking get_ware_shop.php security against unauthenticated access...');
    try {
        const res = await httpRequest({
            hostname: '100.77.57.16',
            port: 8083,
            path: '/dd-shop/php/get_ware_shop.php',
            method: 'GET'
        });
        console.log('  -> Status code:', res.statusCode);
        console.log('  -> Response body:', res.body);
        if (res.statusCode === 401 && res.body.includes('AUTH_REQUIRED')) {
            console.log('  -> SUCCESS: Endpoint is securely blocked with 401 Unauthorized!');
        } else {
            console.error('  -> FAILED: Endpoint should have returned 401 Unauthorized');
            allPassed = false;
        }
    } catch (e) {
        console.error('  -> Test 3 error:', e.message);
        allPassed = false;
    }

    // Test 4: Authenticating with test_admin and testing protected APIs
    console.log('\n[TEST 4] Authenticating with test_admin account...');
    let sessionCookie = '';
    try {
        const loginData = 'name=test_admin&pass=' + encodeURIComponent('Admin#2026@dd');
        const res = await httpRequest({
            hostname: '100.77.57.16',
            port: 8083,
            path: '/dd-shop/php/login.php',
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Content-Length': Buffer.byteLength(loginData)
            }
        }, loginData);

        console.log('  -> Login HTTP Status:', res.statusCode);
        console.log('  -> Login Response:', res.body);
        const setCookie = res.headers['set-cookie'];
        if (setCookie && setCookie.length > 0) {
            sessionCookie = setCookie[0].split(';')[0];
            console.log('  -> Acquired Session Cookie:', sessionCookie.substring(0, 20) + '...');
        }

        const loginObj = JSON.parse(res.body);
        if (Array.isArray(loginObj) && loginObj[0].user_name === 'test_admin' && loginObj[0].user_role === 'แอดมิน') {
            console.log('  -> SUCCESS: test_admin authenticated with role แอดมิน');
        } else {
            console.error('  -> FAILED: unexpected login response');
            allPassed = false;
        }

        // Test protected get_ware_shop.php WITH session
        if (sessionCookie) {
            console.log('\n  -> Testing get_ware_shop.php WITH test_admin session...');
            const wareRes = await httpRequest({
                hostname: '100.77.57.16',
                port: 8083,
                path: '/dd-shop/php/get_ware_shop.php',
                method: 'GET',
                headers: {
                    'Cookie': sessionCookie
                }
            });
            console.log('  -> Status:', wareRes.statusCode);
            const wareData = JSON.parse(wareRes.body);
            console.log('  -> Returned', wareData.length, 'warehouses & shops');
            if (wareRes.statusCode === 200 && Array.isArray(wareData) && wareData.length > 0) {
                console.log('  -> SUCCESS: get_ware_shop.php works with authenticated session!');
            } else {
                console.error('  -> FAILED: get_ware_shop.php failed with session');
                allPassed = false;
            }

            // Test get_invalid_skus.php WITH test_admin session
            console.log('\n  -> Testing get_invalid_skus.php (Admin SKU card) WITH test_admin session...');
            const skuRes = await httpRequest({
                hostname: '100.77.57.16',
                port: 8083,
                path: '/dd-shop/php/get_invalid_skus.php',
                method: 'GET',
                headers: {
                    'Cookie': sessionCookie
                }
            });
            console.log('  -> Status:', skuRes.statusCode);
            const skuData = JSON.parse(skuRes.body);
            console.log('  -> Response success:', skuData.success, 'Invalid SKUs count:', (skuData.data ? skuData.data.length : 0));
            if (skuRes.statusCode === 200 && skuData.success === true) {
                console.log('  -> SUCCESS: get_invalid_skus.php works and Admin Card will display correctly!');
            } else {
                console.error('  -> FAILED: get_invalid_skus.php failed');
                allPassed = false;
            }
        }
    } catch (e) {
        console.error('  -> Test 4 error:', e.message);
        allPassed = false;
    }

    // Test 5: Verify test_staff and test_viewer accounts
    console.log('\n[TEST 5] Testing test_staff and test_viewer logins...');
    try {
        const staffData = 'name=test_staff&pass=' + encodeURIComponent('Staff#2026@dd');
        const staffRes = await httpRequest({
            hostname: '100.77.57.16',
            port: 8083,
            path: '/dd-shop/php/login.php',
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Content-Length': Buffer.byteLength(staffData)
            }
        }, staffData);
        const staffObj = JSON.parse(staffRes.body);
        if (staffObj[0].user_name === 'test_staff' && staffObj[0].user_role === 'พนักงาน') {
            console.log('  -> SUCCESS: test_staff login verified (Role: พนักงาน)');
        } else {
            console.error('  -> FAILED: test_staff login');
            allPassed = false;
        }

        const viewerData = 'name=test_viewer&pass=' + encodeURIComponent('Viewer#2026@dd');
        const viewerRes = await httpRequest({
            hostname: '100.77.57.16',
            port: 8083,
            path: '/dd-shop/php/login.php',
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Content-Length': Buffer.byteLength(viewerData)
            }
        }, viewerData);
        const viewerObj = JSON.parse(viewerRes.body);
        if (viewerObj[0].user_name === 'test_viewer' && viewerObj[0].user_role === 'ดูคลังสินค้า') {
            console.log('  -> SUCCESS: test_viewer login verified (Role: ดูคลังสินค้า)');
        } else {
            console.error('  -> FAILED: test_viewer login');
            allPassed = false;
        }
    } catch (e) {
        console.error('  -> Test 5 error:', e.message);
        allPassed = false;
    }

    console.log('\n====================================================');
    console.log(allPassed ? '>>> ALL VERIFICATION TESTS PASSED (100% OK) <<<' : '>>> SOME TESTS FAILED <<<');
    console.log('====================================================');
}

runVerification();
