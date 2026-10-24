const mysql = require('mysql2');
const fs = require('fs');

const dbFile = fs.readFileSync('c:/ddcar/www/php/db.php', 'utf8');
const userMatch = dbFile.match(/\$user\s*=\s*['"]([^'"]+)['"]/);
const passMatch = dbFile.match(/\$pass\s*=\s*['"]([^'"]+)['"]/);
const dbMatch = dbFile.match(/\$db\s*=\s*['"]([^'"]+)['"]/);

const connection = mysql.createConnection({
  host: '100.77.57.16',
  user: userMatch[1],
  password: passMatch[1],
  database: dbMatch[1]
});

connection.query('DESCRIBE sale_order', (err, rows) => {
  if (err) {
    console.error('Error:', err);
    connection.end();
    return;
  }
  console.log('sale_order columns:\n', rows.map(r => r.Field).join(', '));
  connection.end();
});
