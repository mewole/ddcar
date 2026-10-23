const mysql = require('mysql2/promise');

async function run() {
  const conn = await mysql.createConnection({
    host: '100.77.57.16',
    user: 'ddcar_readonly',
    password: '855109a9dcc67d16d0df7f4b948c6df1Ro*3',
    database: 'ddcar'
  });
  const [b1] = await conn.execute("SELECT * FROM stock_in WHERE stock_id IN (11764, 11783) OR stock_number IN ('IMP26-01545', 'IMP26-01526')");
  console.log('stock_in rows:', b1);
  const [items] = await conn.execute("SELECT * FROM stock_in_item WHERE stock_id IN (11764, 11783)");
  console.log('stock_in_item rows count:', items.length);
  await conn.end();
}
run().catch(console.error);
