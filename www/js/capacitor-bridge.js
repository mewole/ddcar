/**
 * DD ACCESSORIES (DDCAR) - Unified Capacitor Cross-Platform Bridge
 * Bridges Capacitor 8 native plugins with web and legacy Cordova APIs.
 * Supports: Camera, Gallery, App Lifecycle (Back Button & Exit), Screen Orientation, Status Bar, and Printing.
 */

(function (window) {
    'use strict';

    var DDBridge = {};

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
            reader.onerror = function (err) {
                if (typeof onFail === 'function') onFail('FileReader error');
            };
            reader.readAsDataURL(file);
        };
        input.click();
    };

    // 3. APP LIFECYCLE (Back Button & Exit App)
    DDBridge.setupBackButton = function (onBackPressed) {
        var AppPlugin = window.Capacitor?.Plugins?.App;
        if (AppPlugin) {
            AppPlugin.addListener('backButton', function (status) {
                if (typeof onBackPressed === 'function') {
                    onBackPressed(status);
                } else if (!status.canGoBack) {
                    AppPlugin.exitApp();
                } else {
                    window.history.back();
                }
            });
            return;
        }

        // Fallback: Cordova backbutton event
        document.addEventListener('backbutton', function (e) {
            if (typeof onBackPressed === 'function') {
                onBackPressed({ canGoBack: false });
            } else if (typeof navigator !== 'undefined' && navigator.app && typeof navigator.app.exitApp === 'function') {
                navigator.app.exitApp();
            } else {
                window.history.back();
            }
        }, false);
    };

    DDBridge.exitApp = function () {
        var AppPlugin = window.Capacitor?.Plugins?.App;
        if (AppPlugin) {
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
        // Try Cordova printer plugin first if available
        if (window.cordova?.plugins?.printer?.print) {
            window.cordova.plugins.printer.print(htmlContent, options || { duplex: 'none', border: false }, function (res) {
                if (typeof callback === 'function') callback(res);
            });
            return;
        }

        // Web / Android WebView Print via Hidden iframe
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
            // Fallback popup window
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

    // 7. LIFECYCLE HELPER: DDBridge.ready
    DDBridge.ready = function (callback) {
        if (typeof callback !== 'function') return;
        if (window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) {
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                setTimeout(callback, 0);
            } else {
                document.addEventListener('DOMContentLoaded', callback);
            }
        } else if (window.cordova) {
            document.addEventListener('deviceready', callback, false);
        } else {
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                setTimeout(callback, 0);
            } else {
                document.addEventListener('DOMContentLoaded', callback);
            }
        }
    };

    // Expose to window
    window.DDBridge = DDBridge;

})(window);
