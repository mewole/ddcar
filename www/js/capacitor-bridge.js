/**
 * DD ACCESSORIES (DDCAR) - Unified Capacitor Cross-Platform Bridge
 * Bridges Capacitor 8 native plugins with web and legacy Cordova APIs.
 * Supports: Camera, Gallery, App Lifecycle (Back Button & Exit), Screen Orientation, Status Bar, Browser/APK Download, and Printing.
 */

(function (window) {
    'use strict';

    var DDBridge = {};

    // Global AJAX safety: set default 15s timeout if jQuery is present
    if (typeof window.$ !== 'undefined' && typeof window.$.ajaxSetup === 'function') {
        window.$.ajaxSetup({ timeout: 15000 });
    }

    // 1. Helper: Check if running inside Capacitor native shell
    DDBridge.isNative = function () {
        return !!(window.Capacitor && typeof window.Capacitor.isNativePlatform === 'function' && window.Capacitor.isNativePlatform());
    };

    DDBridge.getPlatform = function () {
        if (window.Capacitor && typeof window.Capacitor.getPlatform === 'function') {
            return window.Capacitor.getPlatform();
        }
        if (typeof device !== 'undefined' && device.platform) {
            return device.platform.toLowerCase();
        }
        return 'web';
    };

    // 2. CAMERA & PHOTO GALLERY (Capacitor Camera)
    DDBridge.takePhoto = async function (onSuccess, onFail) {
        var CameraPlugin = window.Capacitor?.Plugins?.Camera;
        if (CameraPlugin) {
            try {
                var photo = await CameraPlugin.getPhoto({
                    quality: 75,
                    allowEditing: false,
                    resultType: 'base64',
                    source: 'CAMERA',
                    width: 960,
                    height: 960,
                    correctOrientation: true
                });
                if (photo && photo.base64String) {
                    if (typeof onSuccess === 'function') {
                        onSuccess(photo.base64String);
                    }
                }
            } catch (err) {
                console.warn('[DDBridge] Camera error:', err);
                var msg = err && err.message ? err.message : String(err);
                if (!msg.toLowerCase().includes('cancel')) {
                    if (typeof onFail === 'function') {
                        onFail(msg);
                    }
                }
            }
            return;
        }

        // Fallback: Cordova Camera
        if (navigator.camera && typeof navigator.camera.getPicture === 'function') {
            navigator.camera.getPicture(
                function (imageData) {
                    if (typeof onSuccess === 'function') onSuccess(imageData);
                },
                function (message) {
                    if (typeof onFail === 'function') onFail(message);
                },
                {
                    quality: 75,
                    destinationType: (typeof Camera !== 'undefined') ? Camera.DestinationType.DATA_URL : 0,
                    sourceType: (typeof Camera !== 'undefined') ? Camera.PictureSourceType.CAMERA : 1,
                    targetWidth: 960,
                    targetHeight: 960,
                    correctOrientation: true
                }
            );
            return;
        }

        // Web Browser Fallback: Hidden file input
        DDBridge._webPickFile('camera', onSuccess, onFail);
    };

    DDBridge.uploadPhoto = async function (onSuccess, onFail) {
        var CameraPlugin = window.Capacitor?.Plugins?.Camera;
        if (CameraPlugin) {
            try {
                var photo = await CameraPlugin.getPhoto({
                    quality: 75,
                    allowEditing: false,
                    resultType: 'base64',
                    source: 'PHOTOS',
                    width: 960,
                    height: 960,
                    correctOrientation: true
                });
                if (photo && photo.base64String) {
                    if (typeof onSuccess === 'function') {
                        onSuccess(photo.base64String);
                    }
                }
            } catch (err) {
                console.warn('[DDBridge] Photo gallery error:', err);
                var msg = err && err.message ? err.message : String(err);
                if (!msg.toLowerCase().includes('cancel')) {
                    if (typeof onFail === 'function') {
                        onFail(msg);
                    }
                }
            }
            return;
        }

        // Fallback: Cordova Camera Photo Library
        if (navigator.camera && typeof navigator.camera.getPicture === 'function') {
            navigator.camera.getPicture(
                function (imageData) {
                    if (typeof onSuccess === 'function') onSuccess(imageData);
                },
                function (message) {
                    if (typeof onFail === 'function') onFail(message);
                },
                {
                    quality: 75,
                    destinationType: (typeof Camera !== 'undefined') ? Camera.DestinationType.DATA_URL : 0,
                    sourceType: (typeof Camera !== 'undefined') ? Camera.PictureSourceType.PHOTOLIBRARY : 0,
                    targetWidth: 960,
                    targetHeight: 960,
                    correctOrientation: true
                }
            );
            return;
        }

        // Web Browser Fallback: Hidden file input
        DDBridge._webPickFile('gallery', onSuccess, onFail);
    };

    DDBridge._webPickFile = function (captureMode, onSuccess, onFail) {
        var input = document.getElementById('ddbridge-file-picker');
        if (!input) {
            input = document.createElement('input');
            input.id = 'ddbridge-file-picker';
            input.type = 'file';
            input.accept = 'image/*';
            input.style.display = 'none';
            document.body.appendChild(input);
        }
        if (captureMode === 'camera') {
            input.setAttribute('capture', 'environment');
        } else {
            input.removeAttribute('capture');
        }
        input.onchange = function (e) {
            var file = e.target.files && e.target.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function (readerEvent) {
                var dataUrl = readerEvent.target.result || '';
                var base64 = dataUrl.split(',')[1] || '';
                if (typeof onSuccess === 'function') onSuccess(base64);
            };
            reader.onerror = function () {
                if (typeof onFail === 'function') onFail('FileReader error');
            };
            reader.readAsDataURL(file);
        };
        input.click();
    };

    // 3. APP LIFECYCLE (Back Button & Exit App)
    var _customBackHandler = null;

    DDBridge.setupBackButton = function (onBackPressed) {
        _customBackHandler = onBackPressed;
    };

    function handleDefaultBack() {
        // 1. If custom handler is set, use it
        if (typeof _customBackHandler === 'function') {
            _customBackHandler({ canGoBack: window.history.length > 1 });
            return;
        }

        // 2. Check if jquery-confirm dialog is open
        if (window.$ && window.$('.jconfirm').length > 0) {
            try {
                if (window.jconfirm && window.jconfirm.instances && window.jconfirm.instances.length > 0) {
                    var lastInstance = window.jconfirm.instances[window.jconfirm.instances.length - 1];
                    if (lastInstance && typeof lastInstance.close === 'function') {
                        lastInstance.close();
                        return;
                    }
                }
            } catch (e) {}
        }

        // 3. Check if in-page search container is active
        var searchCtn = document.getElementById('search-ctn');
        if (searchCtn && !searchCtn.hidden && searchCtn.style.display !== 'none') {
            searchCtn.hidden = true;
            var bigCtn = document.getElementById('big-ctn');
            if (bigCtn) bigCtn.hidden = false;
            return;
        }

        // 4. Current page check
        var pagePath = window.location.pathname || '';
        var currentPage = pagePath.substring(pagePath.lastIndexOf('/') + 1).toLowerCase() || 'index.html';

        if (currentPage === 'index.html' || currentPage === '' || currentPage === 'login.html') {
            // Confirm exit
            if (window.$ && typeof window.$.confirm === 'function') {
                window.$.confirm({
                    title: 'ออกจากแอพพลิเคชัน',
                    content: 'คุณต้องการออกจากแอพ DDCAR ใช่หรือไม่?',
                    backgroundDismiss: true,
                    buttons: {
                        confirm: {
                            text: 'ออก',
                            btnClass: 'btn-danger',
                            action: function () {
                                DDBridge.exitApp();
                            }
                        },
                        cancel: {
                            text: 'ยกเลิก',
                            action: function () {}
                        }
                    }
                });
            } else {
                DDBridge.exitApp();
            }
        } else {
            // Normal page back
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location = 'index.html';
            }
        }
    }

    DDBridge.exitApp = function () {
        var AppPlugin = window.Capacitor?.Plugins?.App;
        if (AppPlugin && typeof AppPlugin.exitApp === 'function') {
            AppPlugin.exitApp();
            return;
        }
        if (typeof navigator !== 'undefined' && navigator.app && typeof navigator.app.exitApp === 'function') {
            navigator.app.exitApp();
        }
    };

    // 4. STATUS BAR & FULLSCREEN
    DDBridge.setFullscreen = function () {
        var StatusBarPlugin = window.Capacitor?.Plugins?.StatusBar;
        if (StatusBarPlugin) {
            StatusBarPlugin.hide().catch(function () {});
            return;
        }
        if (typeof AndroidFullScreen !== 'undefined' && typeof AndroidFullScreen.immersiveMode === 'function') {
            AndroidFullScreen.immersiveMode();
        }
    };

    // 5. SCREEN ORIENTATION
    DDBridge.lockOrientation = function (orientation) {
        var ScreenPlugin = window.Capacitor?.Plugins?.ScreenOrientation;
        if (ScreenPlugin) {
            ScreenPlugin.lock({ orientation: orientation }).catch(function () {});
            return;
        }
        if (screen.orientation && typeof screen.orientation.lock === 'function') {
            try {
                screen.orientation.lock(orientation).catch(function () {});
            } catch (e) {}
        }
    };

    DDBridge.unlockOrientation = function () {
        var ScreenPlugin = window.Capacitor?.Plugins?.ScreenOrientation;
        if (ScreenPlugin) {
            ScreenPlugin.unlock().catch(function () {});
            return;
        }
        if (screen.orientation && typeof screen.orientation.unlock === 'function') {
            try {
                screen.orientation.unlock();
            } catch (e) {}
        }
    };

    // 6. PRINTER
    DDBridge.printHtml = function (htmlContent, options, callback) {
        if (window.cordova?.plugins?.printer?.print) {
            window.cordova.plugins.printer.print(htmlContent, options || { duplex: 'none', border: false }, function (res) {
                if (typeof callback === 'function') callback(res);
            });
            return;
        }

        var iframe = document.getElementById('ddbridge-print-iframe');
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = 'ddbridge-print-iframe';
            iframe.style.position = 'fixed';
            iframe.style.right = '0';
            iframe.style.bottom = '0';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = 'none';
            document.body.appendChild(iframe);
        }

        try {
            var doc = iframe.contentWindow.document;
            doc.open();
            doc.write(htmlContent);
            doc.close();
            setTimeout(function () {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
                if (typeof callback === 'function') callback(true);
            }, 400);
        } catch (e) {
            console.error('[DDBridge] Print error:', e);
            var win = window.open('', '_blank');
            if (win) {
                win.document.write(htmlContent);
                win.document.close();
                setTimeout(function () {
                    win.focus();
                    win.print();
                    win.close();
                    if (typeof callback === 'function') callback(true);
                }, 400);
            }
        }
    };

    // 7. DOWNLOAD / OPEN EXTERNAL URL (APK Install Support)
    DDBridge.openUrl = async function (url) {
        if (!url) return;
        var BrowserPlugin = window.Capacitor?.Plugins?.Browser;
        if (BrowserPlugin && typeof BrowserPlugin.open === 'function') {
            try {
                await BrowserPlugin.open({ url: url });
                return;
            } catch (e) {
                console.warn('[DDBridge] Browser plugin open failed:', e);
            }
        }
        window.open(url, '_system');
    };

    // 8. APP INFO & VERSION
    DDBridge.getAppInfo = async function (callback) {
        var AppPlugin = window.Capacitor?.Plugins?.App;
        var info = {
            name: 'ddcar',
            id: 'com.gitmew.ddcar',
            build: '4',
            version: '2.1.0'
        };

        if (AppPlugin && typeof AppPlugin.getInfo === 'function') {
            try {
                var nativeInfo = await AppPlugin.getInfo();
                if (nativeInfo && nativeInfo.version) {
                    info = nativeInfo;
                }
            } catch (e) {
                console.warn('[DDBridge] getAppInfo error:', e);
            }
        }

        if (typeof callback === 'function') {
            callback(info);
        }
        return info;
    };

    // Helper: Compare semantic versions (returns 1 if v1 > v2, -1 if v1 < v2, 0 if equal)
    DDBridge.compareVersions = function (v1, v2) {
        if (!v1 || !v2) return 0;
        var p1 = String(v1).replace(/^v/i, '').split('.').map(function (n) { return parseInt(n, 10) || 0; });
        var p2 = String(v2).replace(/^v/i, '').split('.').map(function (n) { return parseInt(n, 10) || 0; });
        var maxLen = Math.max(p1.length, p2.length);
        for (var i = 0; i < maxLen; i++) {
            var num1 = p1[i] || 0;
            var num2 = p2[i] || 0;
            if (num1 > num2) return 1;
            if (num1 < num2) return -1;
        }
        return 0;
    };

    // 9. IN-APP UPDATE CHECKER
    DDBridge.checkUpdate = async function (options) {
        options = options || {};
        var serverUrl = localStorage.pathServer || '';
        if (!serverUrl) return;

        var info = await DDBridge.getAppInfo();
        var currentVersion = info.version || '2.1.0';

        var checkApi = serverUrl + 'php/get_lastV.php';
        fetch(checkApi)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data[0]) return;
                var serverVersion = data[0].version;
                var downloadUrl = data[0].url || (serverUrl + 'ddcar-latest.apk');

                if (!downloadUrl || !downloadUrl.startsWith('http')) {
                    downloadUrl = serverUrl + 'ddcar-latest.apk';
                }

                // Only prompt if serverVersion is strictly newer than currentVersion
                if (serverVersion && DDBridge.compareVersions(serverVersion, currentVersion) > 0) {
                    if (typeof window.$ !== 'undefined' && typeof window.$.confirm === 'function') {
                        window.$.confirm({
                            title: '📢 มีการอัปเดตเวอร์ชันใหม่',
                            content: 'พบเวอร์ชันใหม่ (v' + serverVersion + ')<br>เวอร์ชันในเครื่อง: (v' + currentVersion + ')<br><br>ต้องการดาวน์โหลดไฟล์ติดตั้งเพื่ออัปเดตตอนนี้เลยหรือไม่?',
                            backgroundDismiss: false,
                            buttons: {
                                download: {
                                    text: 'ดาวน์โหลดเลย',
                                    btnClass: 'btn-danger',
                                    action: function () {
                                        DDBridge.openUrl(downloadUrl);
                                    }
                                },
                                cancel: {
                                    text: 'ไว้ทีหลัง',
                                    btnClass: 'btn-secondary',
                                    action: function () {}
                                }
                            }
                        });
                    }
                }
            })
            .catch(function (err) {
                console.warn('[DDBridge] checkUpdate error:', err);
            });
    };

    // 10. LIFECYCLE INITIALIZER & AUTO BACK-BUTTON BINDING
    DDBridge.ready = function (callback) {
        var onReady = function () {
            // Setup global back button listener
            var AppPlugin = window.Capacitor?.Plugins?.App;
            if (AppPlugin && typeof AppPlugin.addListener === 'function') {
                try {
                    AppPlugin.removeAllListeners();
                } catch (e) {}
                AppPlugin.addListener('backButton', function () {
                    handleDefaultBack();
                });
            } else {
                document.addEventListener('backbutton', function () {
                    handleDefaultBack();
                }, false);
            }

            if (typeof callback === 'function') {
                callback();
            }
        };

        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(onReady, 0);
        } else {
            document.addEventListener('DOMContentLoaded', onReady);
        }
    };

    // Auto-init on script load
    DDBridge.ready();

    // Expose to window
    window.DDBridge = DDBridge;

})(window);
