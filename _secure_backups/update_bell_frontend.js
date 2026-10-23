const fs = require('fs');
let html = fs.readFileSync('c:/ddcar/www/web_mng_bell.html', 'utf8');

// 1. Add currentViewScope variable
const varTarget = "var currentSearch = '';";
const varReplacement = "var currentSearch = '';\n    var currentViewScope = 'active_90';\n\n    function setViewScope(scope) {\n      if (currentViewScope === scope) return;\n      currentViewScope = scope;\n      if (scope === 'active_90') {\n        $('#btn-scope-active').removeClass('btn-outline-primary').addClass('btn-primary');\n        $('#btn-scope-all').removeClass('btn-primary').addClass('btn-outline-secondary');\n      } else {\n        $('#btn-scope-all').removeClass('btn-outline-secondary').addClass('btn-primary');\n        $('#btn-scope-active').removeClass('btn-primary').addClass('btn-outline-primary');\n      }\n      loadData(true);\n    }";

if (!html.includes('currentViewScope')) {
  html = html.replace(varTarget, varReplacement);
}

// 2. Add view_scope to AJAX call
const ajaxTarget = "search: currentSearch,\n          start: start,";
const ajaxReplacement = "search: currentSearch,\n          view_scope: currentViewScope,\n          start: start,";

if (!html.includes('view_scope: currentViewScope')) {
  html = html.replace(ajaxTarget, ajaxReplacement);
}

// 3. Add toggle buttons to header
const headerTarget = `<button class="btn btn-outline-secondary btn-sm mr-2" onclick="loadData(true)" title="โหลดข้อมูลใหม่">`;
const headerReplacement = `<div class="btn-group btn-group-sm mr-2" role="group">
          <button type="button" class="btn btn-primary" id="btn-scope-active" onclick="setViewScope('active_90')" title="กรองเฉพาะสินค้าที่มียอดขายใน 90 วันที่ผ่านมา"><i class="fas fa-filter mr-1"></i> ขายใน 90 วัน (แนะนำ)</button>
          <button type="button" class="btn btn-outline-secondary" id="btn-scope-all" onclick="setViewScope('all')" title="แสดงสินค้าทุกรายการรวมสินค้าเลิกขาย">สินค้าทั้งหมด</button>
        </div>
        <button class="btn btn-outline-secondary btn-sm mr-2" onclick="loadData(true)" title="โหลดข้อมูลใหม่">`;

if (!html.includes('btn-scope-active')) {
  html = html.replace(headerTarget, headerReplacement);
}

fs.writeFileSync('c:/ddcar/www/web_mng_bell.html', html, 'utf8');
console.log('web_mng_bell.html updated with view scope toggle!');
