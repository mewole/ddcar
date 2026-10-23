const fs = require('fs');
let html = fs.readFileSync('c:/ddcar/www/web_platform_mapping.html', 'utf8');

// 1. TikTok link to consumer view
html = html.replace(
  "return 'https://seller-th.tiktok.com/product/edit/' + idMatch[1];",
  "return 'https://www.tiktok.com/view/product/' + idMatch[1];"
);

// 2. Image fallback to matched_img
const oldImgLine = "var platformImg = (item.image_url && (item.image_url.startsWith('http://') || item.image_url.startsWith('https://'))) ? item.image_url : '';";
const newImgLine = "var platformImg = (item.image_url && (item.image_url.startsWith('http://') || item.image_url.startsWith('https://') || item.image_url.startsWith('img/'))) ? item.image_url : (item.matched_img || '');";
html = html.replace(oldImgLine, newImgLine);

fs.writeFileSync('c:/ddcar/www/web_platform_mapping.html', html, 'utf8');
console.log('web_platform_mapping.html updated successfully!');
