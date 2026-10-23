const fs = require('fs');
const path = require('path');

const srcDir = 'c:/ddcar/www';
const dstDir = '//100.77.57.16/Web/dd-shop';

console.log('Starting full synchronization to NAS:', dstDir);

function syncFolder(relPath = '') {
  const currentSrc = path.join(srcDir, relPath);
  const currentDst = path.join(dstDir, relPath);

  if (!fs.existsSync(currentDst)) {
    fs.mkdirSync(currentDst, { recursive: true });
  }

  const items = fs.readdirSync(currentSrc);
  let count = 0;

  for (const item of items) {
    if (item === '.git' || item === 'img' || item === '_login_security') continue;
    if (item.endsWith('.bak')) continue;

    const sItem = path.join(currentSrc, item);
    const dItem = path.join(currentDst, item);
    const stat = fs.statSync(sItem);

    if (stat.isDirectory()) {
      count += syncFolder(path.join(relPath, item));
    } else {
      // Check if file is different
      let needsCopy = true;
      if (fs.existsSync(dItem)) {
        const dStat = fs.statSync(dItem);
        if (dStat.size === stat.size && Math.abs(dStat.mtimeMs - stat.mtimeMs) < 2000) {
          needsCopy = false;
        }
      }

      if (needsCopy) {
        fs.copyFileSync(sItem, dItem);
        console.log(`[SYNCED] ${path.join(relPath, item)} (${stat.size} bytes)`);
        count++;
      }
    }
  }

  return count;
}

const totalSynced = syncFolder('');
console.log(`\nSynchronization completed successfully. Total files updated on NAS: ${totalSynced}`);
