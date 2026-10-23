const mysql = require('mysql2/promise');

async function test() {
  const c = await mysql.createConnection({
    host: '100.77.57.16',
    user: 'ddcar_app',
    password: 'DDCarAppPass2026!',
    database: 'ddcar'
  });

  const [t1] = await c.query('SELECT COUNT(*) as c FROM product');
  console.log('Total products:', t1[0].c);

  const [t2] = await c.query("SELECT status, COUNT(*) as c FROM product GROUP BY status");
  console.log('Status counts:', t2);

  const [t3] = await c.query("SELECT COUNT(*) as c FROM product WHERE min_amount > 0");
  console.log('Products with min_amount > 0:', t3[0].c);

  const [t4] = await c.query("SELECT COUNT(DISTINCT prod_id) as c FROM order_item WHERE date_time >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
  console.log('Products sold in last 90 days:', t4[0].c);

  const [t6] = await c.query("SELECT COUNT(*) as c FROM product p WHERE p.status != 'normal' AND (p.min_amount > 0 OR p.prod_id IN (SELECT DISTINCT prod_id FROM order_item WHERE date_time >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)))");
  console.log('Critical products sold in 90 days OR min_amount > 0:', t6[0].c);

  await c.end();
}
test().catch(e => console.error(e));
