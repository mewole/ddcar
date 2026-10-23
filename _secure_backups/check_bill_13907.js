const mysql = require('mysql2/promise');

async function run() {
  const conn = await mysql.createConnection({
    host: '100.77.57.16',
    user: 'ddcar_readonly',
    password: '855109a9dcc67d16d0df7f4b948c6df1Ro*3',
    database: 'ddcar'
  });
  const [rows] = await conn.execute("SELECT s.order_id, s.order_number, s.total_price, s.payment_type, s.remark, s.customer_id, c.customer_name FROM sale_order s LEFT JOIN customer c ON s.customer_id = c.customer_id WHERE s.order_number LIKE '%13907%' LIMIT 5");
  console.log('Bill 13907:', rows);
  await conn.end();
}

run().catch(console.error);
