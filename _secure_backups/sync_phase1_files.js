const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const filesToSync = [
  'web_return_claim.html',
  'web_mng_bill.html',
  'web_stock.html',
  'js/navbar.js',
  'php/auth_guard.php',
  'php/get_invalid_skus.php',
  'php/get_bill_details_for_return.php',
  'php/save_product_return.php',
  'php/get_returns.php',
  'php/get_supplier_claims.php',
  'php/update_claim_status.php',
  'php/restore_product.php'
];

const srcDir = 'c:/ddcar/www';
const nasDir = '//100.77.57.16/Web/dd-shop';

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
