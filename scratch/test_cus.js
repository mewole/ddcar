const http = require('http');
http.get('http://ddaccessory.trueddns.com:24330/dd-shop/php/get_cus.php', res => {
  let data = '';
  res.on('data', c => data += c);
  res.on('end', () => {
    try {
      const json = JSON.parse(data);
      console.log('Customer count:', json.length);
      const special = json.filter(x => {
        const s = x.cus_name || '';
        return s.includes('\n') || s.includes('"') || s.includes("'") || s.includes('\\');
      });
      console.log('Special customers count:', special.length);
      special.forEach(x => console.log(x.cus_id, JSON.stringify(x.cus_name)));
    } catch(e) {
      console.error(e);
    }
  });
});
