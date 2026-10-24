const fs = require('fs');
const path = require('path');

const dir = 'c:/ddcar/www';
const files = fs.readdirSync(dir).filter(f => f.endsWith('.html'));

let updatedFiles = [];

files.forEach(f => {
    const filePath = path.join(dir, f);
    let content = fs.readFileSync(filePath, 'utf8');
    let changed = false;

    // Replace navbar.js?v=... or navbar.js
    if (content.includes('navbar.js')) {
        const newContent = content.replace(/navbar\.js(?:\?v=[^\s"\'<>]*)?/g, 'navbar.js?v=3.6');
        if (newContent !== content) {
            content = newContent;
            changed = true;
        }
    }

    // Also update untitled.css?v=... to ?v=3.6
    if (content.includes('untitled.css')) {
        const newContent = content.replace(/untitled\.css(?:\?v=[^\s"\'<>]*)?/g, 'untitled.css?v=3.6');
        if (newContent !== content) {
            content = newContent;
            changed = true;
        }
    }

    if (changed) {
        fs.writeFileSync(filePath, content, 'utf8');
        updatedFiles.push(f);
    }
});

console.log('Updated ' + updatedFiles.length + ' files with ?v=3.6:');
updatedFiles.forEach(f => console.log(' - ' + f));
