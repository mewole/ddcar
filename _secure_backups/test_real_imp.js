const http = require('http');

function test(imp_id) {
  const postBody = 'imp_id=' + imp_id;
  const req = http.request({
    hostname: '100.77.57.16',
    port: 8083,
    path: '/dd-shop/php/get_import_info.php',
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'Content-Length': Buffer.byteLength(postBody),
      'Referer': 'http://100.77.57.16:8083/dd-shop/web_mng_import_warehouse.html',
      'X-User-Id': '33'
    }
  }, (res) => {
    let d = '';
    res.on('data', c => d += c);
    res.on('end', () => console.log('imp_id ' + imp_id + ' result:', d.slice(0, 300)));
  });
  req.write(postBody);
  req.end();
}

test(11764);
test(11783);
