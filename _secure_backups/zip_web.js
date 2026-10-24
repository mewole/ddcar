const { execSync } = require('child_process');
const fs = require('fs');

const localZip = 'c:/ddcar/_secure_backups/ddshop_web_pre_holiday_20261024_2045.zip';
const nasZip = '//100.77.57.16/Public/_ddshop_secure_backups/ddshop_web_pre_holiday_20261024_2045.zip';

console.log('1. Compressing c:/ddcar/www locally...');
const cmd = "powershell -Command \"Compress-Archive -Path 'c:/ddcar/www/*' -DestinationPath '" + localZip + "' -Force\"";
execSync(cmd, { stdio: 'inherit' });
console.log('Local zip size:', fs.statSync(localZip).size, 'bytes');

console.log('2. Copying to NAS Public share...');
fs.copyFileSync(localZip, nasZip);
console.log('Copied to NAS. Public zip size:', fs.statSync(nasZip).size, 'bytes');
