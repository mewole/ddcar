const fs = require('fs');
const readline = require('readline');

const rl = readline.createInterface({
    input: fs.createReadStream('C:/Users/user/.gemini/antigravity-ide/brain/f31a68ea-c376-47c0-8c00-a07d41e201d5/.system_generated/logs/transcript_full.jsonl')
});

rl.on('line', line => {
    if (line.includes('PLANNER_RESPONSE') && line.includes('ตัดสินค้าจากหน้าร้าน')) {
        const obj = JSON.parse(line);
        console.log(obj.content);
    }
});
