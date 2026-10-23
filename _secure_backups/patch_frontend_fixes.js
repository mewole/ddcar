const fs = require('fs');

// --- 1. Patch web_sale.html ---
let sale = fs.readFileSync('c:/ddcar/www/web_sale.html', 'utf8');

// Unblock Tab key
sale = sale.replace(
  /if\s*\(\s*objEvent\.keyCode\s*==\s*9\s*\)\s*\{\s*objEvent\.preventDefault\(\);\s*\}/,
  '// Tab key enabled for smooth navigation'
);

// Fix duplicate event listener in edit(arg)
sale = sale.replace(
  /\$\('#amount-'\+arg\)\.on\("keyup input",\s*function\(\)\{/,
  "$('#amount-'+arg).off('keyup input').on('keyup input', function(){"
);

// Add isOrderSaving and validate save2db
const oldSave2DbStart = `function save2db(){
			if(arr_data.length == 0 || cus_id == null){
					return 0;
			}
			if($("#change-money").val() == "" || $("#get-money").val() == ""){
					return 0;
			}`;

const newSave2DbStart = `var isOrderSaving = false;
		function save2db(){
			if(isOrderSaving){
				console.warn("กำลังบันทึกบิลอยู่ กรุณารอสักครู่");
				return 0;
			}
			if(arr_data.length == 0){
				alert("ยังไม่มีรายการสินค้าในบิล");
				return 0;
			}
			if(cus_id == null){
				alert("กรุณาเลือกลูกค้าก่อนบันทึกรายการ");
				$('#cus').focus();
				return 0;
			}
			var pay = $('#btn-p').text();
			if(pay != 'ตัดไม่คิดเงิน' && ($("#get-money").val() == "" || $("#change-money").val() == "")){
				alert("กรุณากรอกจำนวนเงินที่รับ");
				$('#get-money').focus();
				return 0;
			}
			isOrderSaving = true;
			$('#btn_save').prop('disabled', true);`;

sale = sale.replace(oldSave2DbStart, newSave2DbStart);

// Add complete and error handlers in save_order ajax
const oldSaveOrderAjax = `$.ajax({
					type: "POST",
					url:"php/save_order.php",           
					data: dataString,
					beforeSend: function(){
						EasyLoading.show({                           
							text: "กำลังโหลด"                            
						});
					},               
					success: function(data){`;

const newSaveOrderAjax = `$.ajax({
					type: "POST",
					url:"php/save_order.php",           
					data: dataString,
					beforeSend: function(){
						EasyLoading.show({                           
							text: "กำลังบันทึกข้อมูล..."                            
						});
					},
					complete: function(){
						isOrderSaving = false;
						$('#btn_save').prop('disabled', false);
						EasyLoading.hide();
					},
					error: function(xhr, status, error){
						isOrderSaving = false;
						$('#btn_save').prop('disabled', false);
						EasyLoading.hide();
						if(WinPrint && !WinPrint.closed){
							WinPrint.close();
						}
						alert("เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์ กรุณาตรวจสอบในรายงานบิลก่อนบันทึกซ้ำ");
					},
					success: function(data){`;

sale = sale.replace(oldSaveOrderAjax, newSaveOrderAjax);
fs.writeFileSync('c:/ddcar/www/web_sale.html', sale, 'utf8');
console.log('web_sale.html patched successfully!');

// --- 2. Patch web_import.html ---
let imp = fs.readFileSync('c:/ddcar/www/web_import.html', 'utf8');

// Ensure datalists are present
if (!imp.includes('<datalist id="supplier-datalist"></datalist>')) {
  imp = imp.replace(
    '<datalist id="header_supplier_datalist"></datalist>',
    '<datalist id="header_supplier_datalist"></datalist>\n<datalist id="supplier-datalist"></datalist>'
  );
}

// Update populateSupplierDatalist to fill both datalists
const oldPopulate = `function populateSupplierDatalist(data) {
			var dl = $('#supplier-datalist');
			dl.empty();
			var seen = {};
			if (data.history && data.history.length > 0) {
				$.each(data.history, function(idx, item) {
					seen[item.supplier] = true;
					dl.append('<option value="' + item.supplier + '">ถูกสุด: ' + item.min_cost + ' ฿ (สั่งล่าสุด: ' + (item.last_date ? item.last_date.split(' ')[0] : '-') + ')</option>');
				});
			}
			if (data.all_suppliers && data.all_suppliers.length > 0) {
				$.each(data.all_suppliers, function(idx, sup) {
					if (!seen[sup]) {
						dl.append('<option value="' + sup + '">ร้านในระบบ</option>');
					}
				});
			}
		}`;

const newPopulate = `function populateSupplierDatalist(data) {
			var dl1 = $('#supplier-datalist');
			var dl2 = $('#header_supplier_datalist');
			dl1.empty();
			dl2.empty();
			var seen = {};
			if (data.history && data.history.length > 0) {
				$.each(data.history, function(idx, item) {
					seen[item.supplier] = true;
					var opt = '<option value="' + item.supplier + '">ถูกสุด: ' + item.min_cost + ' ฿ (สั่งล่าสุด: ' + (item.last_date ? item.last_date.split(' ')[0] : '-') + ')</option>';
					dl1.append(opt);
					dl2.append(opt);
				});
			}
			if (data.all_suppliers && data.all_suppliers.length > 0) {
				$.each(data.all_suppliers, function(idx, sup) {
					if (!seen[sup]) {
						var opt2 = '<option value="' + sup + '">ร้านในระบบ</option>';
						dl1.append(opt2);
						dl2.append(opt2);
					}
				});
			}
		}`;

imp = imp.replace(oldPopulate, newPopulate);
fs.writeFileSync('c:/ddcar/www/web_import.html', imp, 'utf8');
console.log('web_import.html patched successfully!');
