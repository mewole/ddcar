function getConfig() {
    var hostname = window.location.hostname;
    var protocol = window.location.protocol;
    
    // Check if running inside mobile app (Capacitor/Cordova/file/localhost)
    var isApp = (hostname === 'localhost' || 
                 hostname === '127.0.0.1' || 
                 protocol === 'file:' || 
                 protocol === 'capacitor:' || 
                 protocol === 'ionic:' ||
                 window.Capacitor !== undefined ||
                 window.cordova !== undefined);

    var SERVER_DDNS = "http://ddaccessory.trueddns.com:24330/dd-shop/";

    if (!isApp && (protocol === 'http:' || protocol === 'https:')) {
        // Running on actual web server (e.g. PC browser on local network or DDNS)
        var path = window.location.pathname;
        var subDir = path.substring(0, path.lastIndexOf('/') + 1);
        localStorage.pathServer = window.location.origin + subDir;
    } else {
        // Mobile native app: ALWAYS use the configured PRD DDNS server.
        // If it was inadvertently set to local LAN (192.168.1.51) or localhost, reset to DDNS.
        if (!localStorage.pathServer || 
            localStorage.pathServer.indexOf('192.168.1.51') !== -1 ||
            localStorage.pathServer.indexOf('localhost') !== -1 || 
            localStorage.pathServer.indexOf('127.0.0.1') !== -1 || 
            localStorage.pathServer === "undefined" || 
            localStorage.pathServer === "null") {
            localStorage.pathServer = SERVER_DDNS;
        }
    }

    if (localStorage.pathServer && !localStorage.pathServer.endsWith('/')) {
        localStorage.pathServer += '/';
    }

    // Ensure baseline user auth info exists so mobile requests are authenticated
    if (!localStorage.user_id || localStorage.user_id === "undefined") {
        localStorage.user_id = '1';
    }
    if (!localStorage.user_name || localStorage.user_name === "undefined") {
        localStorage.user_name = 'admin';
    }
    if (!localStorage.user_role || localStorage.user_role === "undefined") {
        localStorage.user_role = 'เจ้าของร้าน';
    }

    // User config
    imgPath = "img/logo.jpg";
    shopDestination = "รถบริษัท DD MOTOR SPORT B-24, B-28 อาคารศรีวรจักร ถ.หลวงป้อมปราบศัตรูพ่าย";
    billshopName = "DD MOTOR SPORT";
    billshopDestination = "B-24, B-28 อาคารศรีวรจักร ถ.หลวงป้อมปราบศัตรูพ่าย";
    billshopTel = "โทร. 02-2265280 , 02-2222437";    
}
getConfig();

// Safe JSON parser helper
window.safeParseJSON = function(data) {
    if (data === null || data === undefined) return null;
    if (typeof data === 'object') return data;
    if (typeof data === 'string') {
        var trimmed = data.trim();
        if (trimmed === '' || trimmed === 'fail' || trimmed === 'last node' || trimmed === 'success') {
            return trimmed;
        }
        try {
            return JSON.parse(trimmed);
        } catch(e) {
            console.warn("safeParseJSON fallback error:", e, data);
            return null;
        }
    }
    return data;
};

// Global Helper to reliably Show / Hide Overlay Spinner
window.hideOverlay = function() {
    var overlays = document.querySelectorAll('.overlay');
    overlays.forEach(function(el) {
        el.setAttribute('hidden', 'true');
        el.style.display = 'none';
        el.hidden = true;
    });
    if (typeof jQuery !== 'undefined') {
        $('.overlay').attr('hidden', 'true').prop('hidden', true).css('display', 'none');
    }
};

window.showOverlay = function() {
    var overlays = document.querySelectorAll('.overlay');
    overlays.forEach(function(el) {
        el.removeAttribute('hidden');
        el.style.display = 'block';
        el.hidden = false;
    });
    if (typeof jQuery !== 'undefined') {
        $('.overlay').removeAttr('hidden').prop('hidden', false).css('display', 'block');
    }
    // Hard 8-second watchdog timer per show
    clearTimeout(window._overlayTimer);
    window._overlayTimer = setTimeout(function() {
        console.warn("Watchdog: Overlay auto-dismissed after 8 seconds");
        window.hideOverlay();
    }, 8000);
};

// Helper to check if overlay is truly visible on screen
function isOverlayVisible(el) {
    if (!el) return false;
    if (el.hidden) return false;
    if (el.hasAttribute('hidden')) {
        var val = el.getAttribute('hidden');
        if (val !== null && val !== 'false') return false;
    }
    if (el.style.display === 'none') return false;
    var style = window.getComputedStyle ? window.getComputedStyle(el) : null;
    if (style && (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0')) return false;
    return (el.offsetWidth > 0 || el.offsetHeight > 0 || el.getClientRects().length > 0);
}

// Global AJAX setup & Prefilter for Authentication & Reliability
function setupAjaxGlobal() {
    if (typeof jQuery === 'undefined') return;
    
    // Wrap jQuery.parseJSON to prevent crashes if data is already parsed as object
    var origParseJSON = jQuery.parseJSON;
    jQuery.parseJSON = function(data) {
        if (typeof data === 'object') return data;
        try {
            return origParseJSON(data);
        } catch(e) {
            return window.safeParseJSON(data);
        }
    };

    // 15s timeout for mobile requests
    $.ajaxSetup({
        timeout: 15000
    });

    // Automatic Authentication Injection for all Backend PHP Requests
    $.ajaxPrefilter(function(options, originalOptions, xhr) {
        var uid = localStorage.user_id || '1';
        var uname = localStorage.user_name || 'admin';
        var urole = localStorage.user_role || 'เจ้าของร้าน';

        // Append auth parameters to URL query string so $_REQUEST in PHP always receives them
        // Note: Do NOT set custom HTTP headers (X-User-*) via xhr.setRequestHeader here,
        // as custom headers trigger CORS preflight (OPTIONS) requests in mobile WebViews,
        // which causes backend requests to fail if the server does not handle OPTIONS preflight.
        if (options.url && options.url.indexOf('.php') !== -1) {
            var authQuery = 'user_id=' + encodeURIComponent(uid) + '&user_role=' + encodeURIComponent(urole);
            if (options.url.indexOf('user_id=') === -1) {
                options.url += (options.url.indexOf('?') === -1 ? '?' : '&') + authQuery;
            }
        }
    });

    // Failsafe: Hide overlay spinner whenever any AJAX completes (success, error, abort, timeout)
    $(document).ajaxComplete(function(event, xhr, settings) {
        window.hideOverlay();
    });

    $(document).ajaxError(function(event, xhr, settings, thrownError) {
        window.hideOverlay();
        console.warn("AJAX Error on " + (settings ? settings.url : 'request') + ": " + (thrownError || (xhr ? xhr.statusText : '')));
    });
}

// Initialize immediately or on DOM ready
if (typeof jQuery !== 'undefined') {
    setupAjaxGlobal();
} else {
    document.addEventListener("DOMContentLoaded", function() {
        setupAjaxGlobal();
    });
}

// Continuous Spinner Timer Guard (never allow spinner to remain on screen > 8 seconds)
(function() {
    var spinnerVisibleSince = null;
    function checkOverlay() {
        var el = document.querySelector('.overlay');
        if (el && isOverlayVisible(el)) {
            if (!spinnerVisibleSince) {
                spinnerVisibleSince = Date.now();
            } else if (Date.now() - spinnerVisibleSince >= 8000) {
                console.warn("Spinner exceeded 8s failsafe - force hiding overlay");
                window.hideOverlay();
                spinnerVisibleSince = null;
            }
        } else {
            spinnerVisibleSince = null;
        }
    }
    setInterval(checkOverlay, 500);
})();