const mysql = require('mysql2/promise');
const fs = require('fs');

async function main() {
  const conn = await mysql.createConnection({
    host: '100.77.57.16',
    port: 3306,
    user: 'ddcar_app',
    password: process.env.DB_PASS || '60fad888cf98d0b92a45fb4ce77c209eApp$7',
    database: 'ddcar'
  });

  console.log('Connected to MySQL on 100.77.57.16:3306 successfully.');

  // 1. Audit shop table for invalid shop_id
  const [shopDetails] = await conn.query('SELECT * FROM shop_detail');
  console.log('\n--- 1. Valid shop_detail ---');
  console.log(shopDetails);

  const [invalidShopRows] = await conn.query(`
    SELECT s.shop_id, s.prod_id, p.prod_code, p.prod_name, s.amount
    FROM shop s
    LEFT JOIN product p ON s.prod_id = p.prod_id
    WHERE s.shop_id NOT IN (SELECT shop_id FROM shop_detail)
  `);
  console.log('\n--- Rows in shop where shop_id NOT IN shop_detail ---');
  console.log(`Found: ${invalidShopRows.length} rows`);
  if (invalidShopRows.length > 0) {
    console.log(JSON.stringify(invalidShopRows.slice(0, 10), null, 2));
  }

  // 2. Audit MBPJ20FDF stock
  const [mbpjProd] = await conn.query(`SELECT prod_id, prod_code, prod_name FROM product WHERE prod_code = 'MBPJ20FDF'`);
  console.log('\n--- 2. Product MBPJ20FDF ---');
  console.log(mbpjProd);

  if (mbpjProd.length > 0) {
    const pid = mbpjProd[0].prod_id;
    const [shopStk] = await conn.query(`SELECT * FROM shop WHERE prod_id = ?`, [pid]);
    const [wareStk] = await conn.query(`SELECT * FROM warehouse WHERE prod_id = ?`, [pid]);
    console.log('Shop stock for MBPJ20FDF:', shopStk);
    console.log('Warehouse stock for MBPJ20FDF:', wareStk);
  }

  // 3. Audit user passwords
  const [users] = await conn.query('SELECT user_id, user_name, user_role, user_pass FROM user');
  console.log('\n--- 3. Users and Password Hashes ---');
  users.forEach(u => {
    const isBcrypt = u.user_pass && (u.user_pass.startsWith('$2y$') || u.user_pass.startsWith('$2a$') || u.user_pass.startsWith('$2b$'));
    console.log(`User: ${u.user_name} | Role: ${u.user_role} | Hash: ${isBcrypt ? 'YES (bcrypt)' : 'NO (plaintext/other)'}`);
  });

  // 4. Audit platform_sku_mapping
  const [mappings] = await conn.query(`
    SELECT id, channel_code, platform_sku, platform_product_name, status
    FROM platform_sku_mapping
  `);
  console.log(`\n--- 4. Platform SKU Mapping (Total: ${mappings.length}) ---`);
  const corrupt = mappings.filter(m => {
    const name = m.platform_product_name || '';
    const sku = m.platform_sku || '';
    return name.trim().startsWith('{') || name.includes('????') || (!sku.trim() && !name.trim());
  });
  console.log(`Corrupt items matching JSON, ????, or no SKU/Name: ${corrupt.length}`);
  console.log(`Good items: ${mappings.length - corrupt.length}`);
  if (corrupt.length > 0) {
    console.log('Sample corrupt items:', corrupt.slice(0, 5));
  }

  await conn.end();
}

main().catch(err => {
  console.error('Audit DB error:', err);
  process.exit(1);
});
