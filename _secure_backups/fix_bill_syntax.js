const fs = require('fs');

const filePath = 'c:/ddcar/www/web_mng_bill.html';
let content = fs.readFileSync(filePath, 'utf8');

// Normalize line endings
content = content.replace(/\r\n/g, '\n');

// Find the target snippet
const targetSnippet = `    function restore(bill_id,prod_id,price,total_amt,b_id,type,pay,c_id,price){
      // ปิดระบบคืนสินค้าเดิม ให้ใช้ระบบรับคืน/เคลมสินค้า RT26-xxxxx ตาม Phase 1
      window.location.href = "web_return_claim.html?bill_id=" + bill_id;
    }
        }               
      });
    }

    function delete_bill(bill_id, b_id, type, pay, total, c_id){`;

const replacementSnippet = `    function restore(bill_id,prod_id,price,total_amt,b_id,type,pay,c_id,price){
      // ปิดระบบคืนสินค้าเดิม ให้ใช้ระบบรับคืน/เคลมสินค้า RT26-xxxxx ตาม Phase 1
      window.location.href = "web_return_claim.html?bill_id=" + bill_id;
    }

    function delete_bill(bill_id, b_id, type, pay, total, c_id){`;

if (content.includes(targetSnippet)) {
  content = content.replace(targetSnippet, replacementSnippet);
  console.log('Successfully removed leftover brackets via exact match');
} else {
  // Try regex replace
  const regex = /function restore\(bill_id,prod_id,price,total_amt,b_id,type,pay,c_id,price\)[\s\S]*?\}\s*\}\s*\)\s*;\s*\}\s*function delete_bill/;
  if (regex.test(content)) {
    content = content.replace(regex, `function restore(bill_id,prod_id,price,total_amt,b_id,type,pay,c_id,price){\n      // ปิดระบบคืนสินค้าเดิม ให้ใช้ระบบรับคืน/เคลมสินค้า RT26-xxxxx ตาม Phase 1\n      window.location.href = "web_return_claim.html?bill_id=" + bill_id;\n    }\n\n    function delete_bill`);
    console.log('Successfully replaced via regex');
  } else {
    console.error('Could not match target snippet');
  }
}

fs.writeFileSync(filePath, content, 'utf8');
console.log('File written.');

// Validate that script blocks in web_mng_bill.html have valid JS syntax
const scriptMatches = content.match(/<script[\s\S]*?>([\s\S]*?)<\/script>/gi);
let errorFound = false;
if (scriptMatches) {
  scriptMatches.forEach((s, i) => {
    // Only check inline scripts (without src attribute)
    if (!/<script[^>]+src=/i.test(s)) {
      const code = s.replace(/<script[\s\S]*?>/i, '').replace(/<\/script>/i, '');
      try {
        new Function(code);
        console.log(`Script block ${i} is valid JavaScript!`);
      } catch (e) {
        console.error(`Script block ${i} SYNTAX ERROR:`, e.message);
        errorFound = true;
      }
    }
  });
}
if (!errorFound) {
  console.log('ALL INLINE JAVASCRIPT IN web_mng_bill.html IS 100% VALID SYNTAX!');
}
