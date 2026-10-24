const mysql = require('C:/Users/user/.gemini/antigravity-ide/brain/28893e91-8c41-4b90-b190-2d945c903f2f/scratch/node_modules/mysql2/promise');

async function main() {
    const config = {
        host: '100.77.57.16',
        user: 'ddcar_readonly',
        password: '855109a9dcc67d16d0df7f4b948c6df1Ro*3',
        database: 'ddcar',
        port: 3306
    };

    console.log('Connecting to MySQL at', config.host, config.port, 'as', config.user, '...');
    const conn = await mysql.createConnection(config);
    const [rows] = await conn.execute('SELECT user_id, user_name, user_role, LENGTH(pass) as pass_len, pass FROM user ORDER BY user_id ASC');
    console.log(`Found ${rows.length} users:`);
    rows.forEach(r => {
        const isBcrypt = r.pass && (r.pass.startsWith('$2y$') || r.pass.startsWith('$2a$') || r.pass.startsWith('$2b$'));
        console.log(`ID: ${r.user_id} | Name: ${r.user_name} | Role: ${r.user_role} | HashType: ${isBcrypt ? 'BCRYPT' : 'PLAIN'} | Len: ${r.pass_len}`);
    });
    await conn.end();
}

main().catch(console.error);
