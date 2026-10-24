const fs = require('fs');

const files = [
  'c:/ddcar/www/web_stock.html',
  'c:/ddcar/www/web_transfer_in.html',
  'c:/ddcar/www/web_mng_transfer.html',
  'c:/ddcar/www/web_return_claim.html'
];

files.forEach(f => {
  let content = fs.readFileSync(f, 'utf8');
  content = content.replace(/untitled\.css\?v=[0-9.]+/g, 'untitled.css?v=3.7');
  content = content.replace(/navbar\.js\?v=[0-9.]+/g, 'navbar.js?v=3.7');
  fs.writeFileSync(f, content, 'utf8');
  console.log(`Bumped version in ${f}`);
});
