const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const filesToSync = [
  'php/auth_guard.php',
  'php/get_autoprod.php',
  'php/get_bill_details_for_return.php',
  'php/search_prod.php',
  'php/search_prod_full.php',
  'php/get_prod.php',
  'php/get_import_info.php',
  'php/get_low_stock.php',
  'php/get_status_prod.php',
  'php/get_all_cost.php',
  'php/get_sales_summary.php',
  'php/get_shift_bill.php'
];

const srcDir = 'c:/ddcar/www';
const nasDir = 'Z:/dd-shop';

function getHash(filePath) {
  const buf = fs.readFileSync(filePath);
  return crypto.createHash('sha256').update(buf).digest('hex');
}

for (const rel of filesToSync) {
  const src = path.join(srcDir, rel);
  const dest = path.join(nasDir, rel);
  
  if (!fs.existsSync(src)) {
    console.error('Source does not exist: ' + src);
    continue;
  }
  
  const destDir = path.dirname(dest);
  if (!fs.existsSync(destDir)) {
    fs.mkdirSync(destDir, { recursive: true });
  }
  
  fs.copyFileSync(src, dest);
  const hSrc = getHash(src);
  const hDest = getHash(dest);
  
  if (hSrc === hDest) {
    console.log(`[OK] ${rel} synced successfully (SHA256: ${hSrc.substring(0, 8)})`);
  } else {
    console.error(`[FAIL] ${rel} hash mismatch!`);
  }
}
