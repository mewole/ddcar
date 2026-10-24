const fs = require('fs');

let nav = fs.readFileSync('c:/ddcar/www/js/navbar.js', 'utf8');

// 1. Update window.ddcarLogout to accept silent param
const oldLogoutStart = 'window.ddcarLogout = function () {';
const newLogout = `window.ddcarLogout = function (silent) {
        var proceed = silent ? true : (window.confirm ? confirm('คุณต้องการออกจากระบบหรือไม่?') : true);
        if (proceed) {
            try {
                fetch('php/logout.php', { method: 'POST', credentials: 'same-origin' }).finally(function() {
                    localStorage.clear();
                    sessionStorage.clear();
                    window.location = silent ? 'web_login.html?reason=idle_timeout' : 'web_login.html';
                });
            } catch(e) {
                localStorage.clear();
                sessionStorage.clear();
                window.location = silent ? 'web_login.html?reason=idle_timeout' : 'web_login.html';
            }
        }
    };

    // ==========================================
    // Phase 0: Client-side Inactivity Idle Timeout (30 นาที)
    // ==========================================
    var IDLE_TIMEOUT_MS = 30 * 60 * 1000; // 30 นาที
    var idleTimer = null;

    function resetIdleTimer() {
        if (idleTimer) clearTimeout(idleTimer);
        var isLoginPage = window.location.pathname.includes('login');
        if (localStorage.user_id && !isLoginPage) {
            idleTimer = setTimeout(function () {
                if (window.ddcarLogout) {
                    alert('คุณไม่ได้ทำรายการต่อเนื่องเกิน 30 นาที ระบบได้นำคุณออกจากระบบอัตโนมัติเพื่อความปลอดภัยของข้อมูล');
                    window.ddcarLogout(true);
                }
            }, IDLE_TIMEOUT_MS);
        }
    }

    ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click'].forEach(function (evt) {
        window.addEventListener(evt, resetIdleTimer, { passive: true });
    });
    resetIdleTimer();`;

// Find whole old ddcarLogout
const idx = nav.indexOf(oldLogoutStart);
if (idx !== -1) {
    const endLogoutIdx = nav.indexOf('};', idx) + 2;
    nav = nav.substring(0, idx) + newLogout + nav.substring(endLogoutIdx);
    console.log('ddcarLogout and Idle Timeout added successfully!');
} else {
    console.error('Could not find window.ddcarLogout in navbar.js!');
}

fs.writeFileSync('c:/ddcar/www/js/navbar.js', nav, 'utf8');

try {
    new Function(nav);
    console.log('navbar.js syntax is 100% VALID!');
} catch (e) {
    console.error('navbar.js syntax ERROR:', e.message);
}
