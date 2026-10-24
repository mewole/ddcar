const fs = require('fs');
const mysql = require('mysql2/promise');

const dbphp = fs.readFileSync('www/php/db.php', 'utf8');
const matches = [...dbphp.matchAll(/["']([^"']+)["']/g)].map(m => m[1]);
// Typically host, user, pass, db
const host = matches[0];
const user = matches[1];
const password = matches[2];
const database = matches[3];

async function check() {
  const conn = await mysql.createConnection({ host, user, password, database });
  const [cols] = await conn.query('DESCRIBE product_return');
  console.log('Columns in product_return:');
  cols.forEach(c => console.log('  ' + c.Field + ' (' + c.Type + ')'));
  await conn.end();
}

check().catch(console.error);
