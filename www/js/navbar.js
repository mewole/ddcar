/**
 * DD ACCESSORIES (DDCAR) - Centralized Modern Navbar Component
 * Supports both Desktop and Mobile Responsive layouts in Modern Black & White theme.
 * Keeps all 9 core menu items and existing workflows intact.
 */

(function () {
    // Session Guard (except login pages)
    var currentPath = window.location.pathname.split('/').pop() || 'index.html';
    if (!currentPath.includes('login') && !localStorage.user_id) {
        window.location = 'web_login.html';
        return;
    }

    var menuItems = [
        { id: 'nav-sale', label: 'ขายสินค้า', icon: 'fa fa-shopping-cart', href: 'web_sale.html', roles: ['all'] },
        { id: 'nav-import', label: 'นำสินค้าเข้า', icon: 'fa fa-arrow-down', href: 'web_import.html', roles: ['all'] },
        { id: 'nav-stock', label: 'เช็คสต๊อก', icon: 'fa fa-boxes', href: 'web_stock.html', roles: ['all'] },
        { id: 'nav-transfer', label: 'โอนย้ายสินค้า', icon: 'fa fa-exchange-alt', href: 'web_transfer_in.html', roles: ['all'] },
        { id: 'nav-bell', label: 'เช็คสถานะสินค้า', icon: 'fa fa-bell', href: 'web_mng_bell.html', roles: ['all'] },
        {
            id: 'nav-select',
            label: 'รายการย้อนหลัง',
            icon: 'fa fa-history',
            href: 'web_select.html',
            roles: ['all'],
            subItems: [
                { label: 'รายงานบิล', icon: 'fa fa-file-invoice-dollar', href: 'web_mng_bill.html' },
                { label: 'รายงานสินค้าเข้าหน้าร้าน', icon: 'fa fa-store', href: 'web_mng_import.html' },
                { label: 'รายงานการโอนย้ายสินค้า', icon: 'fa fa-exchange-alt', href: 'web_mng_transfer.html' },
                { label: 'รายงานสินค้าเข้าโกดัง', icon: 'fa fa-warehouse', href: 'web_mng_import_warehouse.html' },
                { label: 'รายงานสินค้าออกโกดัง (เดิม)', icon: 'fa fa-dolly-flatbed', href: 'web_mng_export.html' },
                { label: 'รายงานสรุปยอดขาย', icon: 'fa fa-chart-line', href: 'web_mng_sale.html', ownerOnly: true },
                { label: 'รายงานเครดิตลูกค้า', icon: 'fa fa-credit-card', href: 'web_credit.html' }
            ]
        },
        {
            id: 'nav-admin',
            label: 'จัดการระบบ',
            icon: 'fa fa-user-shield',
            href: '#',
            adminOwnerOnly: true,
            subItems: [
                { label: 'จัดการผู้ใช้', icon: 'fa fa-user-cog', href: 'web_admin_user.html' },
                { label: 'จัดการลูกค้า', icon: 'fa fa-user-edit', href: 'web_customer.html' },
                { label: 'จัดการหน้าร้าน/โกดัง', icon: 'fa fa-store-alt', href: 'web_warehouse.html' }
            ]
        },
        { id: 'nav-qrprint', label: 'พิมพ์คิวอาร์', icon: 'fa fa-qrcode', href: 'web_qrprint.html', roles: ['all'] }
    ];

    function getPageTitle(path) {
        switch (path) {
            case 'web_sale.html': return 'ขายสินค้า (POS)';
            case 'web_import.html': return 'นำสินค้าเข้าโกดัง/หน้าร้าน';
            case 'web_stock.html': return 'เช็คสต๊อกสินค้า';
            case 'web_export.html': return 'โอนย้ายสินค้า';
            case 'web_transfer_in.html': return 'โอนย้ายสินค้า (หน้าร้าน ⇄ โกดัง)';
            case 'web_mng_bell.html': return 'เช็คสถานะสินค้า';
            case 'web_select.html': return 'รายการย้อนหลังและข้อมูลระบบ';
            case 'web_customer.html': return 'จัดการข้อมูลลูกค้า';
            case 'web_warehouse.html': return 'จัดการหน้าร้านและโกดัง';
            case 'web_admin_user.html': return 'จัดการผู้ใช้งานและสิทธิ์';
            case 'web_qrprint.html': return 'พิมพ์คิวอาร์โค้ดสินค้า';
            case 'web_mng_bill.html': return 'รายงานบิล';
            case 'web_credit.html': return 'รายงานเครดิตลูกค้า';
            case 'web_mng_sale.html': return 'รายงานสรุปยอดขาย';
            case 'web_mng_import.html': return 'รายงานสินค้าเข้าหน้าร้าน';
            case 'web_mng_import_warehouse.html': return 'รายงานสินค้าเข้าโกดัง';
            case 'web_mng_transfer.html': return 'รายงานการโอนย้ายสินค้า';
            case 'web_mng_export.html': return 'รายงานสินค้าออกโกดัง';
            case 'web_folder.html': return 'หมวดหมู่สินค้า';
            default: return 'DD Accessories';
        }
    }

    function renderNavbar() {
        var userRole = localStorage.user_role || 'พนักงาน';
        var userName = localStorage.user_name || 'ผู้ใช้งาน';
        var isOwnerOrAdmin = (userRole === 'แอดมิน' || userRole === 'เจ้าของร้าน');
        var pageTitle = getPageTitle(currentPath);

        // Build navigation items HTML
        var navListHtml = '';
        menuItems.forEach(function (item) {
            // Permission check: hide if role is warehouse view and item is sale
            if (userRole === 'ดูคลังสินค้า' && (item.id === 'nav-sale' || item.id === 'nav-import' || item.id === 'nav-export')) {
                return;
            }

            // Admin & Owner only menu check
            if (item.adminOwnerOnly && !isOwnerOrAdmin) {
                return;
            }

            var isActive = (currentPath === item.href);
            var isSubActive = false;
            if (item.subItems) {
                isSubActive = item.subItems.some(function (sub) { return currentPath === sub.href; });
            }
            var activeClass = (isActive || isSubActive) ? 'active' : '';

            if (item.subItems && item.subItems.length > 0) {
                // Dropdown item
                var subListHtml = '';
                item.subItems.forEach(function (sub) {
                    if (sub.ownerOnly && !isOwnerOrAdmin) return;
                    if (sub.adminOwnerOnly && !isOwnerOrAdmin) return;
                    var subActive = (currentPath === sub.href) ? 'active-sub' : '';
                    subListHtml += '<li><a class="dropdown-item ' + subActive + '" href="' + sub.href + '"><i class="' + sub.icon + ' mr-2 text-muted"></i>' + sub.label + '</a></li>';
                });

                if (subListHtml.trim() === '') return;

                navListHtml += '<li class="nav-item dropdown ' + activeClass + '">' +
                    '<a class="nav-link dropdown-toggle" href="#" id="dropdown-' + item.id + '" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' +
                    '<i class="' + item.icon + ' mr-1"></i> ' + item.label +
                    '</a>' +
                    '<ul class="dropdown-menu dropdown-menu-right shadow-sm" aria-labelledby="dropdown-' + item.id + '">' +
                    subListHtml +
                    '</ul>' +
                    '</li>';
            } else {
                // Standard item
                navListHtml += '<li class="nav-item ' + activeClass + '">' +
                    '<a class="nav-link" href="' + item.href + '">' +
                    '<i class="' + item.icon + ' mr-1"></i> ' + item.label +
                    '</a>' +
                    '</li>';
            }
        });

        // Logout item
        navListHtml += '<li class="nav-item nav-logout">' +
            '<a class="nav-link text-danger font-weight-bold" href="javascript:void(0)" onclick="window.ddcarLogout()">' +
            '<i class="fa fa-sign-out-alt mr-1"></i> ออกจากระบบ' +
            '</a>' +
            '</li>';

        var navbarHtml = '<nav class="navbar navbar-expand-lg navbar-dark navbar-ddcar">' +
            '<div class="container-fluid px-0">' +
                '<a class="navbar-brand d-flex align-items-center" href="web_select.html">' +
                    '<div class="brand-badge mr-2"><img src="img/logo.jpg" alt="DD Accessories" onerror="this.style.display=\'none\'; this.parentElement.innerHTML=\'<i class=\\\'fa fa-car-side\\\'></i>\';"></div>' +
                    '<div class="brand-text">' +
                        '<div class="brand-title">DD Accessories</div>' +
                        '<div class="brand-subtitle">' + pageTitle + '</div>' +
                    '</div>' +
                '</a>' +
                '<div class="d-flex align-items-center d-lg-none">' +
                    '<span class="badge badge-light-user mr-2 text-truncate" style="max-width: 100px;">' + userName + '</span>' +
                    '<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarDDCar" aria-controls="navbarDDCar" aria-expanded="false" aria-label="Toggle navigation">' +
                        '<span class="navbar-toggler-icon"></span>' +
                    '</button>' +
                '</div>' +
                '<div class="collapse navbar-collapse" id="navbarDDCar">' +
                    '<ul class="navbar-nav ml-auto align-items-lg-center">' +
                        navListHtml +
                        '<li class="nav-item user-info-item d-none d-lg-flex align-items-center ml-2 pl-2 border-left border-secondary">' +
                            '<i class="fa fa-user-circle text-light mr-1" style="font-size: 1.2rem;"></i>' +
                            '<div class="user-meta">' +
                                '<div class="user-name">' + userName + '</div>' +
                                '<div class="user-role-badge">' + userRole + '</div>' +
                            '</div>' +
                        '</li>' +
                    '</ul>' +
                '</div>' +
            '</div>' +
        '</nav>';

        // Target insertion or replacement
        var existingNav = document.querySelector('nav.navbar-ddcar') || document.querySelector('nav.navbar');
        var navContainer = document.getElementById('ddcar-nav-mount');

        if (navContainer) {
            navContainer.innerHTML = navbarHtml;
        } else if (existingNav) {
            existingNav.outerHTML = navbarHtml;
        } else {
            var firstChild = document.body.firstElementChild;
            var mountDiv = document.createElement('div');
            mountDiv.id = 'ddcar-nav-mount';
            mountDiv.innerHTML = navbarHtml;
            document.body.insertBefore(mountDiv, firstChild);
        }
    }

    // Global logout helper
    window.ddcarLogout = function () {
        if (window.confirm ? confirm('คุณต้องการออกจากระบบหรือไม่?') : true) {
            localStorage.clear();
            sessionStorage.clear();
            window.location = 'web_login.html';
        }
    };

    // Auto mount when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderNavbar);
    } else {
        renderNavbar();
    }
})();
