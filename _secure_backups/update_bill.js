const fs = require('fs');

const filePath = 'c:/ddcar/www/web_mng_bill.html';
let content = fs.readFileSync(filePath, 'utf8');

// Replace old restore functions with redirect to web_return_claim.html
const newFunctions = `    function init_restore(bill_id,prod_id,price,total_amt,b_id,type,pay,c_id,price){
      // ปิดระบบคืนสินค้าเดิม ให้ใช้ระบบรับคืน/เคลมสินค้า RT26-xxxxx ตาม Phase 1
      window.location.href = "web_return_claim.html?bill_id=" + bill_id;
    }

    function restore(bill_id,prod_id,price,total_amt,b_id,type,pay,c_id,price){
      // ปิดระบบคืนสินค้าเดิม ให้ใช้ระบบรับคืน/เคลมสินค้า RT26-xxxxx ตาม Phase 1
      window.location.href = "web_return_claim.html?bill_id=" + bill_id;
    }`;

// Regex match init_restore and restore
const regexRestore = /function init_restore[\s\S]*?function restore[\s\S]*?\}\s*\}\s*\);\s*\}/;
if (regexRestore.test(content)) {
  content = content.replace(regexRestore, newFunctions);
  console.log('Replaced restore functions via regex');
} else {
  console.log('Regex did not match, checking if already replaced');
}

// Add RT button in appendCollapses
const targetSnippet = "'<button class=\"btn btn-info mr-2 mb-2\" onclick=\"printA4(' + index + ')\"><i class=\"fas fa-print mr-1\"></i> พิมพ์ใบส่งของ (A4)</button>' +";
const replacementSnippet = targetSnippet + "\n                      '<a href=\"web_return_claim.html?order_number=' + encodeURIComponent(obj[0].order_number) + '\" class=\"btn btn-warning mr-2 mb-2 text-dark font-weight-bold\"><i class=\"fas fa-undo-alt mr-1\"></i> รับคืน / เคลมสินค้า (RT)</a>' +";

if (content.includes(targetSnippet) && !content.includes('web_return_claim.html?order_number=')) {
  content = content.replace(targetSnippet, replacementSnippet);
  console.log('Added RT button in appendCollapses');
} else {
  console.log('Snippet not found or RT button already exists');
}

fs.writeFileSync(filePath, content, 'utf8');
console.log('Updated ' + filePath);
