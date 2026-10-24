const http = require('http');

// First login as Song
const loginData = 'name=Song&pass=1111';
const req = http.request({
  hostname: '100.77.57.16',
  port: 8083,
  path: '/dd-shop/php/login.php',
  method: 'POST',
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
    'Content-Length': Buffer.byteLength(loginData)
  }
}, (res) => {
  const cookie = res.headers['set-cookie'];
  
  const req2 = http.request({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/init_return_tables.php',
    method: 'GET',
    headers: {
      'Cookie': cookie ? cookie[0].split(';')[0] : ''
    }
  }, (res2) => {
    console.log('Status code:', res2.statusCode);
    console.log('Headers:', res2.headers);
    let data = '';
    res2.on('data', c => data += c);
    res2.on('end', () => {
      console.log('Body length:', data.length);
      console.log('Body:\n', data);
    });
  });
  req2.end();
});
req.write(loginData);
req.end();
