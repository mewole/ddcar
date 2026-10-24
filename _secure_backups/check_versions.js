const fs = require('fs');
const path = require('path');

const dir = 'c:/ddcar/www';
const files = fs.readdirSync(dir).filter(f => f.endsWith('.html'));

console.log('Total HTML files in www:', files.length);

files.forEach(f => {
    const content = fs.readFileSync(path.join(dir, f), 'utf8');
    const vMatches = content.match(/[a-zA-Z0-9_\-\.\/]+\.(?:js|css)\?v=[^\s"\'<>]+/g) || [];
    console.log(f.padEnd(32) + ' -> ' + (vMatches.length > 0 ? vMatches.join(', ') : 'NO ?v='));
});
