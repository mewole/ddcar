const fs = require('fs');
let content = fs.readFileSync('c:\\ddcar\\www\\web_mng_transfer.html', 'utf8');

content = content.replace(/var routeIcon = field\.direction === 'ware_to_shop' \? '📦 ➜ 🏪' : '🏪 ➜ 📦';\r?\n\s*var routeStr = '<span class="text-secondary mr-1">' \+ routeIcon \+ '<\/span> ' \+ \(field\.source_name \|\| 'โกดัง'\) \+ ' ➜ ' \+ \(field\.dest_name \|\| 'หน้าร้าน'\);/g, 
  "var routeIcon = field.direction === 'ware_to_shop' ? '📦 ➜ 🏪' : (field.direction === 'ware_to_ware' ? '📦 ➜ 📦' : '🏪 ➜ 📦');\r\n              var routeStr = '<span class=\"text-secondary mr-1\">' + routeIcon + '</span> ' + (field.source_name || '-') + ' ➜ ' + (field.dest_name || '-');");

fs.writeFileSync('c:\\ddcar\\www\\web_mng_transfer.html', content, 'utf8');
console.log('web_mng_transfer.html updated successfully');
