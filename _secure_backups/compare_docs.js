const fs = require('fs');

const f1 = fs.readFileSync('c:/ddcar/_secure_backups/แผนพัฒนา_ddshop_20261024_v6.md', 'utf8');
const f2 = fs.readFileSync('c:/ddcar/_secure_backups/รายการแก้และพัฒนาทั้งหมด_ส่งนักพัฒนา_24-10-2026_latest.md', 'utf8');

console.log('File 1 (v6) length:', f1.length, 'lines:', f1.split('\n').length);
console.log('File 2 (latest 5:10 PM) length:', f2.length, 'lines:', f2.split('\n').length);

// Compare line sets or keywords
const lines1 = f1.split('\n').map(l => l.trim()).filter(l => l);
const lines2 = f2.split('\n').map(l => l.trim()).filter(l => l);

const newLinesIn2 = lines2.filter(l => !lines1.includes(l));
console.log('New lines in latest doc (count):', newLinesIn2.length);
if (newLinesIn2.length > 0) {
  console.log('Sample of new lines:');
  newLinesIn2.slice(0, 30).forEach((l, i) => console.log(`${i+1}: ${l}`));
} else {
  console.log('Content is substantially identical.');
}
