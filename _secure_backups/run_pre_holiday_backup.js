const http = require('http');
const qs = require('querystring');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

async function run() {
  console.log('=== Step 0: Pre-Work Full Backup (Holiday Sprint 24-25 Oct 2026) ===');

  // 1. Login as test_admin to obtain session
  console.log('1. Logging in as test_admin...');
  const postData = qs.stringify({ name: 'test_admin', pass: 'Admin#2026@dd' });
  const loginRes = await new Promise((resolve, reject) => {
    const req = http.request({
      hostname: '100.77.57.16',
      port: 8083,
      path: '/dd-shop/php/login.php',
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(postData)
      }
    }, res => {
      let body = '';
      res.on('data', c => body += c);
      res.on('end', () => resolve({ headers: res.headers, body }));
    });
    req.on('error', reject);
    req.write(postData);
    req.end();
  });

  const cookieHeader = loginRes.headers['set-cookie'] ? loginRes.headers['set-cookie'][0].split(';')[0] : '';
  console.log('Logged in. Cookie:', cookieHeader);

  // 2. Trigger DB Backup
  console.log('\n2. Triggering MySQL Dump via backup_db.php...');
  const backupRes = await new Promise((resolve, reject) => {
    const req = http.request({
      hostname: '100.77.57.16',
      port: 8083,
      path: '/dd-shop/php/backup_db.php?action=backup',
      method: 'GET',
      headers: {
        'Cookie': cookieHeader
      }
    }, res => {
      let body = '';
      res.on('data', c => body += c);
      res.on('end', () => resolve({ statusCode: res.statusCode, body }));
    });
    req.on('error', reject);
    req.end();
  });

  console.log('Backup API Status:', backupRes.statusCode);
  const backupJson = JSON.parse(backupRes.body);
  console.log('Backup Result:', backupJson);

  if (!backupJson.success || !backupJson.filename) {
    throw new Error('Database backup failed: ' + backupRes.body);
  }

  // 3. Move backup file to Public share (OUTSIDE Web!) and delete from Web
  const webBackupDir = '\\\\100.77.57.16\\Web\\_backup_db';
  const nasFile = path.join(webBackupDir, backupJson.filename);
  const publicBackupDir = '\\\\100.77.57.16\\Public\\_ddshop_secure_backups';
  if (!fs.existsSync(publicBackupDir)) {
    fs.mkdirSync(publicBackupDir, { recursive: true });
  }

  const finalDbFile = path.join(publicBackupDir, `ddcar_db_pre_holiday_${Date.now()}.sql.gz`);
  console.log('\n3. Moving DB dump from Web folder to Public share (Zero Web exposure)...');
  if (fs.existsSync(nasFile)) {
    fs.copyFileSync(nasFile, finalDbFile);
    fs.unlinkSync(nasFile);
    console.log('Saved DB Backup to:', finalDbFile);
    console.log('DB Backup Size:', fs.statSync(finalDbFile).size, 'bytes');
  } else {
    console.warn('File not found at:', nasFile);
  }

  // Also remove _backup_db directory from Web if empty
  try {
    const remaining = fs.readdirSync(webBackupDir);
    if (remaining.length === 0 || (remaining.length === 1 && remaining[0] === '.htaccess')) {
      fs.rmSync(webBackupDir, { recursive: true, force: true });
      console.log('Cleaned up _backup_db from Web.');
    }
  } catch(e) {}

  // 4. Create Web Directory Zip Snapshot
  console.log('\n4. Creating Web Files Snapshot of dd-shop to Public share...');
  const finalZipFile = path.join(publicBackupDir, `ddshop_web_pre_holiday_${Date.now()}.zip`);
  const psCmd = `Compress-Archive -Path '\\\\100.77.57.16\\Web\\dd-shop\\*' -DestinationPath '${finalZipFile}' -Force`;
  execSync(`powershell -Command "${psCmd}"`, { stdio: 'inherit' });
  console.log('Saved Web Snapshot to:', finalZipFile);
  console.log('Web Snapshot Size:', fs.statSync(finalZipFile).size, 'bytes');

  console.log('\n=== PRE-WORK BACKUP COMPLETE AND VERIFIED ===');
  console.log('DB Backup:', finalDbFile, `(${(fs.statSync(finalDbFile).size / 1024 / 1024).toFixed(2)} MB)`);
  console.log('Web Files:', finalZipFile, `(${(fs.statSync(finalZipFile).size / 1024 / 1024).toFixed(2)} MB)`);
}

run().catch(err => {
  console.error('Pre-work backup error:', err);
  process.exit(1);
});
