const fs = require('fs');
const cp = require('child_process');

function extractDocx(docxPath, outputMdPath) {
  const tempZip = 'c:/ddcar/_secure_backups/temp_' + Date.now() + '.zip';
  const extractDir = 'c:/ddcar/_secure_backups/temp_ext_' + Date.now();

  try {
    fs.copyFileSync(docxPath, tempZip);
    cp.execSync('powershell -Command "Expand-Archive -Path \'' + tempZip + '\' -DestinationPath \'' + extractDir + '\' -Force"');

    const xmlPath = extractDir + '/word/document.xml';
    if (fs.existsSync(xmlPath)) {
      const xml = fs.readFileSync(xmlPath, 'utf8');
      const text = xml
        .replace(/<w:p[^>]*>/g, '\n')
        .replace(/<w:tr[^>]*>/g, '\n| ')
        .replace(/<w:tc[^>]*>/g, ' | ')
        .replace(/<[^>]+>/g, '')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&apos;/g, "'");

      const cleanLines = text.split('\n')
        .map(l => l.trim())
        .filter(l => l.length > 0)
        .join('\n');

      fs.writeFileSync(outputMdPath, cleanLines, 'utf8');
      console.log('Saved:', outputMdPath, 'Lines:', cleanLines.split('\n').length);
    } else {
      console.error('No document.xml in', docxPath);
    }
  } finally {
    if (fs.existsSync(tempZip)) fs.unlinkSync(tempZip);
    if (fs.existsSync(extractDir)) fs.rmSync(extractDir, { recursive: true, force: true });
  }
}

// 1. แผนอัปเดตวันหยุด_ส่งนักพัฒนา_24-25-10-2026.docx
extractDocx(
  'C:/Users/user/Downloads/แผนอัปเดตวันหยุด_ส่งนักพัฒนา_24-25-10-2026.docx',
  'c:/ddcar/_secure_backups/แผนอัปเดตวันหยุด_ส่งนักพัฒนา_24-25-10-2026.md'
);

// 2. รายการแก้และพัฒนาทั้งหมด_ส่งนักพัฒนา_24-10-2026.docx
extractDocx(
  'C:/Users/user/Downloads/รายการแก้และพัฒนาทั้งหมด_ส่งนักพัฒนา_24-10-2026.docx',
  'c:/ddcar/_secure_backups/รายการแก้และพัฒนาทั้งหมด_ส่งนักพัฒนา_24-10-2026_v8.md'
);
