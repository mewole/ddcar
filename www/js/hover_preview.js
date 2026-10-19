/**
 * hover_preview.js
 * Universal lightweight floating product thumbnail preview on hover
 */

(function($) {
    'use strict';

    window.__prodImageCache = window.__prodImageCache || {};
    var currentHoverId = null;
    var $popover = null;
    var $popoverImg = null;
    var $popoverName = null;
    var $popoverCode = null;
    var popoverWidth = 176;
    var popoverHeight = 230;

    function initPopover() {
        if ($('#prod-hover-popover').length > 0) {
            $popover = $('#prod-hover-popover');
        } else {
            var html = 
                '<div id="prod-hover-popover">' +
                    '<div class="prod-hover-img-wrap">' +
                        '<img class="prod-hover-img" src="img/no_img.png" alt="Preview">' +
                        '<div class="prod-hover-spinner"><i class="fas fa-circle-notch fa-spin"></i></div>' +
                        '<div class="prod-hover-noimg-label">ไม่มีรูปสินค้า</div>' +
                    '</div>' +
                    '<div class="prod-hover-code"></div>' +
                    '<div class="prod-hover-name"></div>' +
                '</div>';
            $('body').append(html);
            $popover = $('#prod-hover-popover');
        }

        $popoverImg = $popover.find('.prod-hover-img');
        $popoverName = $popover.find('.prod-hover-name');
        $popoverCode = $popover.find('.prod-hover-code');
    }

    function positionPopover(clientX, clientY) {
        if (!$popover) return;

        var winW = $(window).width();
        var winH = $(window).height();

        // Preferred: bottom-right of cursor
        var posX = clientX + 16;
        var posY = clientY + 16;

        // If overflowing right, flip to left
        if (posX + popoverWidth > winW - 12) {
            posX = clientX - popoverWidth - 16;
        }
        if (posX < 10) {
            posX = 10;
        }

        // If overflowing bottom, flip to above cursor
        if (posY + popoverHeight > winH - 12) {
            posY = clientY - popoverHeight - 16;
        }
        if (posY < 10) {
            posY = 10;
        }

        $popover.css({
            left: posX + 'px',
            top: posY + 'px'
        });
    }

    function showImage(src, isNoImg) {
        if (!$popover) return;
        $popover.removeClass('is-loading');
        if (isNoImg) {
            $popover.addClass('has-no-img');
        } else {
            $popover.removeClass('has-no-img');
        }
        $popoverImg.attr('src', src);
    }

    function hidePopover() {
        currentHoverId = null;
        if ($popover) {
            $popover.removeClass('prod-hover-visible is-loading has-no-img');
        }
    }

    function loadProductImage(prodId, directImg) {
        if (directImg && directImg !== 'img/no_img.png' && directImg !== '') {
            showImage(directImg, false);
            return;
        }

        if (!prodId) {
            showImage('img/no_img.png', true);
            return;
        }

        // Check cache
        if (window.__prodImageCache[prodId] !== undefined) {
            var cached = window.__prodImageCache[prodId];
            showImage(cached, cached === 'img/no_img.png');
            return;
        }

        // Loading state
        $popover.addClass('is-loading');
        $popover.removeClass('has-no-img');

        var thisId = prodId;

        // Attempt 1: Preload standard .jpg path directly
        var preloader = new Image();
        var jpgUrl = 'img/products/' + thisId + '.jpg';

        preloader.onload = function() {
            window.__prodImageCache[thisId] = jpgUrl;
            if (currentHoverId === thisId) {
                showImage(jpgUrl, false);
            }
        };

        preloader.onerror = function() {
            // Attempt 2: Query backend search_img.php (checks DB or other extensions)
            $.ajax({
                type: 'POST',
                url: 'php/search_img.php',
                data: { 'prod_id': thisId },
                dataType: 'json',
                timeout: 4000
            }).done(function(data) {
                var foundImg = 'img/no_img.png';
                if (data && data.length > 0 && data[0].img && data[0].img !== 'img/no_img.png' && data[0].img !== '') {
                    foundImg = data[0].img;
                }
                window.__prodImageCache[thisId] = foundImg;
                if (currentHoverId === thisId) {
                    showImage(foundImg, foundImg === 'img/no_img.png');
                }
            }).fail(function() {
                window.__prodImageCache[thisId] = 'img/no_img.png';
                if (currentHoverId === thisId) {
                    showImage('img/no_img.png', true);
                }
            });
        };

        preloader.src = jpgUrl;
    }

    $(document).ready(function() {
        initPopover();

        // Mouseenter on hoverable product elements
        $(document).on('mouseenter', '.hover-prod-preview, [data-prod-id]', function(e) {
            var $elem = $(this);
            var prodId = $elem.attr('data-prod-id');
            var prodName = $elem.attr('data-prod-name') || $elem.text().trim();
            var prodCode = $elem.attr('data-prod-code') || '';
            var directImg = $elem.attr('data-prod-img') || '';

            if (!prodId && !directImg) return;

            initPopover();
            currentHoverId = prodId || ('img_' + directImg);

            $popoverName.text(prodName);
            $popoverCode.text(prodCode ? ('SKU: ' + prodCode) : '');

            positionPopover(e.clientX, e.clientY);
            $popover.addClass('prod-hover-visible');

            loadProductImage(prodId, directImg);
        });

        // Mousemove follows cursor smoothly
        $(document).on('mousemove', '.hover-prod-preview, [data-prod-id]', function(e) {
            if ($popover && $popover.hasClass('prod-hover-visible')) {
                positionPopover(e.clientX, e.clientY);
            }
        });

        // Mouseleave hides preview
        $(document).on('mouseleave', '.hover-prod-preview, [data-prod-id]', function(e) {
            hidePopover();
        });

        // Hide when scrolling or clicking
        $(window).on('scroll', hidePopover);
        $(document).on('click', hidePopover);
    });

})(jQuery);
