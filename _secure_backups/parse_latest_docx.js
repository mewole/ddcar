const fs = require('fs');
const cp = require('child_process');

const docxPath = 'C:/Users/user/Downloads/รายการแก้และพัฒนาทั้งหมด_ส่งนักพัฒนา_24-10-2026.docx';
const tempZip = 'c:/ddcar/_secure_backups/temp_latest_doc.zip';
const extractDir = 'c:/ddcar/_secure_backups/temp_latest_extracted';

if (fs.existsSync(extractDir)) {
  fs.rmSync(extractDir, { recursive: true, force: true });
}
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

  fs.writeFileSync('c:/ddcar/_secure_backups/รายการแก้และพัฒนาทั้งหมด_ส่งนักพัฒนา_24-10-2026_latest.md', cleanLines, 'utf8');
  console.log('Successfully extracted and saved latest markdown! Lines:', cleanLines.split('\n').length);
} else {
  console.error('document.xml not found!');
}

if (fs.existsSync(tempZip)) fs.unlinkSync(tempZip);
if (fs.existsSync(extractDir)) fs.rmSync(extractDir, { recursive: true, force: true });
