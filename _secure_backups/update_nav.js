const fs = require('fs');
let s = fs.readFileSync('c:/ddcar/www/js/navbar.js', 'utf8');

const target1 = "{ label: 'รายงานบิล', icon: 'fa fa-file-invoice-dollar', href: 'web_mng_bill.html' },";
const repl1 = "{ label: 'รายงานบิล', icon: 'fa fa-file-invoice-dollar', href: 'web_mng_bill.html' },\r\n                { label: 'รับคืน / เคลมสินค้า (RT)', icon: 'fa fa-undo-alt', href: 'web_return_claim.html' },";

if (s.includes(target1)) {
  s = s.replace(target1, repl1);
} else {
  // Try with \n
  s = s.replace(target1, "{ label: 'รายงานบิล', icon: 'fa fa-file-invoice-dollar', href: 'web_mng_bill.html' },\n                { label: 'รับคืน / เคลมสินค้า (RT)', icon: 'fa fa-undo-alt', href: 'web_return_claim.html' },");
}

const target2 = "case 'web_mng_bill.html': return 'รายงานบิล';";
const repl2 = "case 'web_return_claim.html': return 'ศูนย์รับคืนและเคลมสินค้า';\r\n            case 'web_mng_bill.html': return 'รายงานบิล';";

if (s.includes(target2)) {
  s = s.replace(target2, repl2);
} else {
  s = s.replace(target2, "case 'web_return_claim.html': return 'ศูนย์รับคืนและเคลมสินค้า';\n            case 'web_mng_bill.html': return 'รายงานบิล';");
}

fs.writeFileSync('c:/ddcar/www/js/navbar.js', s, 'utf8');
console.log('navbar.js updated successfully!');
