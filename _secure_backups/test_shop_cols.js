const http = require('http');

// We can run a small debug request to inspect columns of shop and warehouse
const req = http.request({
  hostname: '100.77.57.16',
  port: 8083,
  path: '/dd-shop/php/login.php',
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': 19 }
}, res => {
  const cookie = res.headers['set-cookie'][0].split(';')[0];
  console.log('Logged in');
});
req.write('name=Song&pass=1111');
req.end();
