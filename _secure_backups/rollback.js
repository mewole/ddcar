const fs = require('fs');
const { execSync } = require('child_process');

console.log('--- EMERGENCY ROLLBACK SCRIPT ---');
console.log('Restoring code snapshot to c:/ddcar/www and NAS...');

// Extract zip back to c:/ddcar/www
execSync('powershell -Command "Expand-Archive -Path \'c:\\ddcar\\_secure_backups\\www_code_snapshot_20261023.zip\' -DestinationPath \'c:\\ddcar\\www\' -Force"', { stdio: 'inherit' });

// Sync to NAS
console.log('Syncing restored files to NAS...');
execSync('powershell -Command "Copy-Item -Path \'c:\\ddcar\\www\\*\' -Destination \'\\\\100.77.57.16\\Web\\dd-shop\\\' -Recurse -Force"', { stdio: 'inherit' });

console.log('ROLLBACK COMPLETED.');
