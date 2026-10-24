const http = require('http');

const apis = ['get_bill.php', 'get_credit.php', 'get_returns.php', 'get_autoprod.php'];

async function testUnauth() {
  console.log('Testing Unauthenticated Requests (No Cookie / No Session):');
  let allPass = true;
  for (let api of apis) {
    await new Promise(resolve => {
      const req = http.request({
        hostname: '100.77.57.16',
        port: 8083,
        path: '/dd-shop/php/' + api,
        method: 'POST'
      }, res => {
        let b = '';
        res.on('data', c => b += c);
        res.on('end', () => {
          const is401 = (res.statusCode === 401);
          console.log('[' + api + '] Status: ' + res.statusCode + ' | ' + (is401 ? '✅ PASS (Blocked)' : '❌ FAIL') + ' | ' + b.trim().slice(0, 80));
          if (!is401) allPass = false;
          resolve();
        });
      });
      req.end();
    });
  }
  console.log('All unauthenticated requests blocked with 401:', allPass ? '✅ 100% PASS' : '❌ FAIL');
}

testUnauth().catch(console.error);
