const mysql = require('mysql2/promise');

async function run() {
  const conn = await mysql.createConnection({
    host: '100.77.57.16',
    user: 'ddcar_web',
    password: 'SecureWebUser2026!#',
    database: 'ddcar_db'
  });
  
  const [allRows] = await conn.query('SELECT prod_id, prod_code FROM product');
  const codeMap = new Map();
  allRows.forEach(r => {
    if (r.prod_code) {
      codeMap.set(r.prod_code, r.prod_id);
    }
  });
  
  const [invalidRows] = await conn.query("SELECT prod_id, prod_code, prod_name FROM product WHERE NOT (prod_code REGEXP BINARY '^[A-Z0-9-]+$')");
  console.log('Total invalid:', invalidRows.length);
  
  let cleanable = 0;
  let collision = 0;
  let manual = 0;
  
  const collisionItems = [];

  invalidRows.forEach(r => {
    let raw = r.prod_code || '';
    let suggested = raw.trim().toUpperCase().replace(/\s+/g, '').replace(/[\u0E00-\u0E7F]/g, '').replace(/[._/]/g, '');
    if (!suggested || !/^[A-Z0-9-]+$/.test(suggested)) {
      manual++;
    } else if (codeMap.has(suggested) && codeMap.get(suggested) !== r.prod_id) {
      collision++;
      collisionItems.push({ prod_id: r.prod_id, raw, suggested, collide_with_prod_id: codeMap.get(suggested) });
    } else {
      cleanable++;
    }
  });
  
  console.log('Cleanable:', cleanable);
  console.log('Collision:', collision);
  console.log('Manual syntax:', manual);
  console.log('Total manual needed (collision + manual syntax):', collision + manual);
  if (collisionItems.length > 0) {
    console.log('Sample collisions:', collisionItems.slice(0, 5));
  }
  await conn.end();
}
run().catch(console.error);
