const fs = require('fs');
const path = require('path');
const dir = 'c:/ddcar/www/php';
const files = fs.readdirSync(dir).filter(f => f.endsWith('.php'));

files.forEach(file => {
  const content = fs.readFileSync(path.join(dir, file), 'utf8');
  const lines = content.split('\n');
  lines.forEach((line, idx) => {
    if (line.includes('??')) {
      console.log(file + ':' + (idx + 1) + ' -> ' + line.trim());
    }
  });
});
