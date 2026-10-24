const fs = require('fs');

let nav = fs.readFileSync('c:/ddcar/www/js/navbar.js', 'utf8');

const startStr = '<div class="brand-badge mr-2"';
const endStr = '</div>';

const startIdx = nav.indexOf(startStr);
if (startIdx !== -1) {
    const endIdx = nav.indexOf(endStr, startIdx) + endStr.length;
    // We want the JS string to have valid escaping
    const styleAttr = ' style="width:40px;height:40px;min-width:40px;min-height:40px;max-width:40px;max-height:40px;display:flex;align-items:center;justify-content:center;border-radius:8px;background:#fff;overflow:hidden;flex-shrink:0;"';
    const imgStyle = ' style="width:100%;height:100%;max-width:40px;max-height:40px;object-fit:contain;border-radius:6px;display:block;"';
    
    // In the original file:
    // '<div class="brand-badge mr-2"><img src="img/logo.jpg" alt="DD Accessories" onerror="this.style.display=\'none\'; this.parentElement.innerHTML=\'<i class=\\\'fa fa-car-side\\\'></i>\';"></div>'
    const newBadge = '<div class="brand-badge mr-2"' + styleAttr + '><img src="img/logo.jpg" alt="DD Accessories"' + imgStyle + ' onerror="this.style.display=\\\'none\\\'; this.parentElement.innerHTML=\\\'<i class=\\\\\\\'fa fa-car-side\\\\\\\'></i>\\\';"></div>';
    
    nav = nav.substring(0, startIdx) + newBadge + nav.substring(endIdx);
    console.log('Brand badge inline style replaced with proper escaping!');
} else {
    console.error('Could not find brand badge start!');
}

fs.writeFileSync('c:/ddcar/www/js/navbar.js', nav, 'utf8');

try {
    new Function(nav);
    console.log('navbar.js syntax is 100% VALID!');
} catch (e) {
    console.error('navbar.js syntax ERROR:', e.message);
}
