const fs = require('fs');
const path = require('path');

const src = '\\\\100.77.57.16\\Web\\_secure_backups\\ddcar_backup_20261023_144027.sql.gz';
const destDir = '\\\\100.77.57.16\\Public\\_ddshop_secure_backups';
const dest = path.join(destDir, 'ddcar_backup_20261023_144027.sql.gz');

if (!fs.existsSync(destDir)) {
  fs.mkdirSync(destDir, { recursive: true });
}

console.log('Moving file from Web to Public...');
if (fs.existsSync(src)) {
  fs.copyFileSync(src, dest);
  fs.unlinkSync(src);
  console.log('Successfully copied to Public and deleted from Web.');
} else {
  console.log('Source file does not exist:', src);
}

const webBackupDir = '\\\\100.77.57.16\\Web\\_secure_backups';
if (fs.existsSync(webBackupDir)) {
  fs.rmSync(webBackupDir, { recursive: true, force: true });
  console.log('Successfully deleted _secure_backups from Web.');
}

console.log('Web backup dir exists?', fs.existsSync(webBackupDir));
console.log('Public backup file exists?', fs.existsSync(dest));
if (fs.existsSync(dest)) {
  console.log('Public backup file size:', fs.statSync(dest).size);
}
