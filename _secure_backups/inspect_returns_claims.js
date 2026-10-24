const mysql = require('C:/Users/user/.gemini/antigravity-ide/brain/28893e91-8c41-4b90-b190-2d945c903f2f/scratch/node_modules/mysql2/promise');

async function main() {
    const conn = await mysql.createConnection({
        host: '100.77.57.16',
        user: 'ddcar_readonly',
        password: '855109a9dcc67d16d0df7f4b948c6df1Ro*3',
        database: 'ddcar',
        port: 3306
    });

    console.log('--- 1. Tables Matching return / claim ---');
    const [allTables] = await conn.execute('SHOW TABLES');
    const tableNames = allTables.map(t => Object.values(t)[0]);
    const relevantTables = tableNames.filter(t => t.includes('return') || t.includes('claim') || t.includes('stock') || t.includes('order'));
    console.log('Relevant tables:', relevantTables);

    for (const t of ['product_return', 'supplier_claim', 'product_return_item', 'supplier_claim_item']) {
        if (tableNames.includes(t)) {
            console.log(`\n--- Schema: ${t} ---`);
            const [cols] = await conn.execute(`SHOW COLUMNS FROM ${t}`);
            cols.forEach(c => console.log(`  ${c.Field} (${c.Type})`));

            console.log(`\n--- Rows in ${t} ---`);
            const [rows] = await conn.execute(`SELECT * FROM ${t}`);
            console.log(JSON.stringify(rows, null, 2));
        }
    }

    console.log('\n--- 2. Checking Products: SZSW18HL01 & TYTGD4DFG01 ---');
    const [prods] = await conn.execute("SELECT prod_id, prod_code, prod_name, min_amount, status FROM product WHERE prod_code IN ('SZSW18HL01', 'TYTGD4DFG01')");
    console.log('Products:', prods);

    for (const p of prods) {
        console.log(`\n--- Stock for prod_id ${p.prod_id} (${p.prod_code} - ${p.prod_name}) ---`);
        const [shopStock] = await conn.execute("SELECT s.shop_id, sd.shop_name, s.amount FROM shop s LEFT JOIN shop_detail sd ON s.shop_id = sd.shop_id WHERE s.prod_id = ?", [p.prod_id]);
        console.log('Shop Stock:', shopStock);

        const [wareStock] = await conn.execute("SELECT w.ware_id, wd.ware_name, w.amount FROM warehouse w LEFT JOIN warehouse_detail wd ON w.ware_id = wd.ware_id WHERE w.prod_id = ?", [p.prod_id]);
        console.log('Warehouse Stock:', wareStock);
    }

    await conn.end();
}

main().catch(console.error);
