const fs = require('fs');

console.log('=== APPLYING ANALYST ROUND 5 FIXES (A1, A2, A3, A4, A8, C4) ===\n');

// -------------------------------------------------------------
// A1: web_stock.html - Fix unclosed </div> in EditModal
// -------------------------------------------------------------
console.log('--- A1: web_stock.html EditModal close div ---');
let stockHtml = fs.readFileSync('c:/ddcar/www/web_stock.html', 'utf8');
const pEdit = stockHtml.indexOf('id="EditModal"');
const pAdj = stockHtml.indexOf('id="AdjustStockModal"');
if (pEdit !== -1 && pAdj !== -1) {
  const slice = stockHtml.substring(pEdit, pAdj);
  const opens = (slice.match(/<div[\s>]/gi) || []).length;
  const closes = (slice.match(/<\/div>/gi) || []).length;
  console.log(`Current open divs: ${opens}, close divs: ${closes}, diff: ${opens - closes}`);
  if (opens > closes) {
    const targetBlock = '			</div>\r\n		  </div>\r\n		</div>\r\n	  </div>';
    const targetBlockLF = '			</div>\n		  </div>\n		</div>\n	  </div>';
    if (stockHtml.includes(targetBlock)) {
      stockHtml = stockHtml.replace(targetBlock, targetBlock + '\r\n	</div>');
      console.log('  [OK] Added closing </div> before AdjustStockModal (CRLF)');
    } else if (stockHtml.includes(targetBlockLF)) {
      stockHtml = stockHtml.replace(targetBlockLF, targetBlockLF + '\n	</div>');
      console.log('  [OK] Added closing </div> before AdjustStockModal (LF)');
    }
    fs.writeFileSync('c:/ddcar/www/web_stock.html', stockHtml, 'utf8');
  }
}

// -------------------------------------------------------------
// A2 & A3: Global canViewCost() in navbar.js + web_mng_import + web_mng_import_warehouse
// -------------------------------------------------------------
console.log('\n--- A2 & A3: Global canViewCost helper ---');
let navJs = fs.readFileSync('c:/ddcar/www/js/navbar.js', 'utf8');
if (!navJs.includes('window.canViewCost')) {
  const canViewCostFunc = `
    // Global helper: ตรวจสอบสิทธิ์ดูต้นทุนสินค้า (เฉพาะแอดมินและเจ้าของร้าน)
    window.canViewCost = function () {
        var role = localStorage.user_role || '';
        return (role === 'แอดมิน' || role === 'เจ้าของร้าน');
    };
    window.canViewCostVal = (localStorage.user_role === 'แอดมิน' || localStorage.user_role === 'เจ้าของร้าน');
`;
  navJs = navJs.replace('// Global logout helper', canViewCostFunc + '\n    // Global logout helper');
  fs.writeFileSync('c:/ddcar/www/js/navbar.js', navJs, 'utf8');
  console.log('  [OK] Added window.canViewCost to navbar.js');
}

// In web_mng_import.html: declare canViewCost at top of appendCollapse and appendCollapses
let impShop = fs.readFileSync('c:/ddcar/www/web_mng_import.html', 'utf8');
impShop = impShop.replace(
  'function appendCollapse(index){',
  'function appendCollapse(index){\n        var canViewCost = (typeof window.canViewCost === "function") ? window.canViewCost() : (localStorage.user_role === "แอดมิน" || localStorage.user_role === "เจ้าของร้าน");'
);
impShop = impShop.replace(
  'function appendCollapses(index){',
  'function appendCollapses(index){\n        var canViewCost = (typeof window.canViewCost === "function") ? window.canViewCost() : (localStorage.user_role === "แอดมิน" || localStorage.user_role === "เจ้าของร้าน");'
);
fs.writeFileSync('c:/ddcar/www/web_mng_import.html', impShop, 'utf8');
console.log('  [OK] Fixed canViewCost scope in web_mng_import.html');

// In web_mng_import_warehouse.html: declare canViewCost at top of appendCollapse and appendCollapses
let impWare = fs.readFileSync('c:/ddcar/www/web_mng_import_warehouse.html', 'utf8');
impWare = impWare.replace(
  'function appendCollapse(index){',
  'function appendCollapse(index){\n        var canViewCost = (typeof window.canViewCost === "function") ? window.canViewCost() : (localStorage.user_role === "แอดมิน" || localStorage.user_role === "เจ้าของร้าน");'
);
impWare = impWare.replace(
  'function appendCollapses(index){',
  'function appendCollapses(index){\n        var canViewCost = (typeof window.canViewCost === "function") ? window.canViewCost() : (localStorage.user_role === "แอดมิน" || localStorage.user_role === "เจ้าของร้าน");'
);

// -------------------------------------------------------------
// A4: web_mng_import_warehouse.html - Safe check supplier_name
// -------------------------------------------------------------
console.log('\n--- A4: Safe supplier_name check in web_mng_import_warehouse.html ---');
const oldSupHeader1 = "var supHeader = (obj[0].supplier_name || obj[0].source_from || '-');";
const newSupHeader1 = "var firstRow = (obj && obj.length > 0) ? obj[0] : {};\n              var supHeader = (firstRow.supplier_name || firstRow.source_from || '-');";
impWare = impWare.replaceAll(oldSupHeader1, newSupHeader1);

const oldSupHeader2 = "var sup = obj[0].supplier_name || obj[0].source_from || '-';";
const newSupHeader2 = "var firstRow = (obj && obj.length > 0) ? obj[0] : {};\n          var sup = (firstRow.supplier_name || firstRow.source_from || '-');";
impWare = impWare.replaceAll(oldSupHeader2, newSupHeader2);

fs.writeFileSync('c:/ddcar/www/web_mng_import_warehouse.html', impWare, 'utf8');
console.log('  [OK] Added safe supplier_name fallback in web_mng_import_warehouse.html');

// -------------------------------------------------------------
// A8: web_mng_sale.html - Add "ตัดไม่คิดเงิน" Write-off KPI Card
// -------------------------------------------------------------
console.log('\n--- A8: Adding Write-off KPI card to web_mng_sale.html ---');
let saleRptHtml = fs.readFileSync('c:/ddcar/www/web_mng_sale.html', 'utf8');

const targetGrid = '<div class="kpi-grid">';
const writeoffCardHtml = `<div class="kpi-grid">
            <div class="kpi-card" id="card-writeoff" style="border-left: 4px solid #f59e0b;">
                <div class="kpi-card-header">
                    <div class="kpi-card-title text-warning">ตัดไม่คิดเงิน (Write-off)</div>
                    <div class="kpi-card-icon" style="background: rgba(245, 158, 11, 0.15); color: #d97706;"><i class="fa fa-hand-holding-heart"></i></div>
                </div>
                <div class="kpi-card-value text-warning" id="kpi-writeoff">฿0.00</div>
                <div class="kpi-card-sub" id="kpi-writeoff-sub">0 บิล (ไม่นำมาคิดยอดขาย)</div>
            </div>`;

if (!saleRptHtml.includes('id="card-writeoff"')) {
  saleRptHtml = saleRptHtml.replace(targetGrid, writeoffCardHtml);
}

// Update JS in web_mng_sale.html to populate kpi-writeoff
const oldKpiPopulate = "$('#kpi-count').text(formatNumber(summary.total_bills || 0));";
const newKpiPopulate = `$('#kpi-count').text(formatNumber(summary.total_bills || 0));
                    var wCost = parseFloat(summary.writeoff_cost || 0);
                    var wBills = parseInt(summary.writeoff_bills || 0);
                    $('#kpi-writeoff').text('฿' + formatMoney(wCost));
                    $('#kpi-writeoff-sub').text(wBills + ' บิล (แยกต้นทุนไม่รวมในยอดขาย)');`;

if (!saleRptHtml.includes('kpi-writeoff-sub')) {
  saleRptHtml = saleRptHtml.replace(oldKpiPopulate, newKpiPopulate);
}

fs.writeFileSync('c:/ddcar/www/web_mng_sale.html', saleRptHtml, 'utf8');
console.log('  [OK] Added Write-off KPI card and data population in web_mng_sale.html');

// -------------------------------------------------------------
// C4: Cache-busting: bump ?v=3.5 across all html files
// -------------------------------------------------------------
console.log('\n--- C4: Bumping cache-busting version ?v=3.5 ---');
const wwwDir = 'c:/ddcar/www';
const htmlFiles = fs.readdirSync(wwwDir).filter(f => f.endsWith('.html'));
let bumpedCount = 0;

for (const hf of htmlFiles) {
  const filePath = wwwDir + '/' + hf;
  let content = fs.readFileSync(filePath, 'utf8');
  let changed = false;

  if (content.includes('navbar.js?v=3.0')) {
    content = content.replace(/navbar\.js\?v=3\.0/g, 'navbar.js?v=3.5');
    changed = true;
  }
  if (content.includes('navbar.js"')) {
    content = content.replace(/navbar\.js"/g, 'navbar.js?v=3.5"');
    changed = true;
  }
  if (changed) {
    fs.writeFileSync(filePath, content, 'utf8');
    bumpedCount++;
  }
}
console.log(`  [OK] Bumped navbar.js?v=3.5 across ${bumpedCount} HTML files`);

console.log('\n=== ALL ROUND 5 FIXES APPLIED SUCCESSFULLY! ===');
