const http = require('http');

// We can check tables through login and an endpoint or check via a script
const req = http.request({
  hostname: '100.77.57.16',
  port: 8083,
  path: '/dd-shop/php/login.php',
  method: 'POST',
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
    'Content-Length': 19
  }
}, res => {
  const cookie = res.headers['set-cookie'][0].split(';')[0];
  console.log('Logged in, cookie:', cookie);
});
req.write('name=Song&pass=1111');
req.end();
