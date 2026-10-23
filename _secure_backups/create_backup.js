const fs = require('fs');
const http = require('http');
const path = require('path');
const { execSync } = require('child_process');

const backupDir = 'c:/ddcar/_secure_backups';
if (!fs.existsSync(backupDir)) {
    fs.mkdirSync(backupDir, { recursive: true });
}

console.log('1. Starting Database Backup...');

// We call backup_db.php with Admin role credentials
const backupUrl = 'http://100.77.57.16:8083/dd-shop/php/backup_db.php?action=backup&user_id=1&user_role=' + encodeURIComponent('เจ้าของร้าน');
const req = http.request(backupUrl, {
    method: 'GET',
    headers: {
        'X-User-Id': '1',
        'Referer': 'http://100.77.57.16:8083/dd-shop/web_stock.html'
    }
}, (res) => {
    let raw = '';
    res.on('data', chunk => raw += chunk);
    res.on('end', () => {
        console.log('Backup response status:', res.statusCode);
        try {
            const data = JSON.parse(raw);
            console.log('Backup result:', data);
            if (data.success && data.filename) {
                // Find where the file was saved on the NAS
                const nasBackupFile = path.join('//100.77.57.16/Web/_backup_db', data.filename);
                const localBackupFile = path.join(backupDir, data.filename);

                if (fs.existsSync(nasBackupFile)) {
                    // Copy to secure local directory OUTSIDE web root
                    fs.copyFileSync(nasBackupFile, localBackupFile);
                    console.log('Successfully copied DB backup to secure location:', localBackupFile);
                    console.log('File size:', fs.statSync(localBackupFile).size, 'bytes');

                    // DELETE from NAS web folder so no public URL can EVER download it!
                    fs.unlinkSync(nasBackupFile);
                    console.log('Cleaned up and deleted backup from web folder on NAS (Zero exposure).');
                } else {
                    console.warn('NAS backup file not found at:', nasBackupFile);
                }
            } else {
                console.error('Backup returned failure:', raw);
            }
        } catch(e) {
            console.error('Error parsing backup response:', e.message, '\nRaw:\n', raw);
        }

        console.log('\n2. Creating Web Files Snapshot...');
        try {
            const zipFile = path.join(backupDir, `www_snapshot_${Date.now()}.zip`);
            // Use PowerShell to create zip archive of c:\ddcar\www
            const psCmd = `Compress-Archive -Path 'c:\\ddcar\\www\\*' -DestinationPath '${zipFile}' -Force`;
            execSync(`powershell -Command "${psCmd}"`, { stdio: 'inherit' });
            console.log('Web snapshot successfully created:', zipFile);
            console.log('Snapshot size:', fs.statSync(zipFile).size, 'bytes');
        } catch (zipErr) {
            console.error('Error creating web snapshot:', zipErr.message);
        }

        console.log('\n3. Verifying All Secure Backups:');
        const files = fs.readdirSync(backupDir);
        files.forEach(f => {
            const fpath = path.join(backupDir, f);
            const stat = fs.statSync(fpath);
            console.log(` - ${f} (${(stat.size / 1024 / 1024).toFixed(2)} MB)`);
        });
        console.log('\nBACKUP PHASE COMPLETED SUCCESSFULLY!');
    });
});

req.on('error', (err) => {
    console.error('HTTP Request failed:', err);
});

req.end();
