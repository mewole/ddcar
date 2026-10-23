const fs = require('fs');

function checkFile(filePath, patterns) {
  const content = fs.readFileSync(filePath, 'utf8');
  console.log('=== Checking:', filePath, '===');
  for (const p of patterns) {
    const regex = new RegExp(p, 'gi');
    const matches = [...content.matchAll(regex)];
    console.log('Pattern /' + p + '/ found ' + matches.length + ' times:');
    matches.forEach((m, idx) => {
      const start = Math.max(0, m.index - 50);
      const end = Math.min(content.length, m.index + m[0].length + 50);
      console.log(`  [#${idx + 1} pos ${m.index}] ...${content.substring(start, end).replace(/[\r\n]+/g, ' ')}...`);
    });
  }
}

checkFile('c:/ddcar/www/web_sale.html', [
  'id=["\']head["\']',
  'setwareto',
  'id=["\']c["\']',
  '\\$?\\([\'"]#c[\'"]\\)'
]);

checkFile('c:/ddcar/www/web_stock.html', [
  'edit-bundle-action-wrap'
]);

checkFile('c:/ddcar/www/web_mng_bill.html', [
  'id=["\']printModal["\']',
  'id=["\']reModal["\']'
]);
