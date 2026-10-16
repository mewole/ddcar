const fs = require('fs');
const path = 'C:/ddcar/www/web_import.html';

let html = fs.readFileSync(path, 'utf8');

// 1. Replace header
const oldHeadRegex = /<div id="head" class="row mgb-5">[\s\S]*?<\/div>\s*<\/div>\s*<\/div>/;
const newHead = `<div id="head" class="row mgb-5">
       		  <div class="col-2 text-center nopd">บาร์โค้ด</div>
       		  <div class="col-1 text-center nopd-8">ที่วางของ</div>
       		  <div class="col-1 text-center nopd-8">จาก (From)</div>
       		  <div class="col-3 text-center nopd">ชื่อสินค้า</div>
       		  <div class="col-1 text-center nopd-8">จำนวน</div>
       		  <div class="col-1 text-center nopd-8">ทุน</div>
       		  <div class="col-1 text-center nopd-8">ราคาส่ง</div>
       		  <div class="col-1 text-center nopd-8">ราคาปลีก</div>
       		  <div class="col-1 text-center nopd">จัดการ</div>
       		</div>
       	</div>`;

html = html.replace(/<div id="head" class="row mgb-5">[\s\S]*?<\/div>\s*<\/div>\s*<\/div>/, newHead);
html = html.replace(/<\/div>\s*<\/div>\s*<div id="cnt-add"/, '</div>\n\n\t\t<div id="cnt-add"');

// 2. Replace input row
const oldInputRegex = /<!-- Input -->[\s\S]*?<div class="col-1 nopd text-center">[\s\S]*?<\/div>\s*<\/div>/;
const newInput = `<!-- Input -->
			<div class="row">
			  <div class="col-2">
			    <div class='input-group-prepend'>
			      <span id="span-no" class='input-group-text'>#</span>
			      <input id='barcode' type='text' class='form-control text-center' readonly>
			    </div>
			  </div>
			  <div class='col-1 nopd-8'>
			    <input id='at' type='text' class='form-control text-center' placeholder="ที่วาง/ชั้น" data-index="6">
			  </div>
			  <div class='col-1 nopd-8'>
			    <input id='from_shop' type='text' class='form-control text-center font-weight-bold text-primary' placeholder="ตัวย่อร้าน" list="supplier-datalist" data-index="7">
			    <datalist id="supplier-datalist"></datalist>
			  </div>
			  <div class='col-3 nopd-8'>
			    <input id="name" class="form-control text-center" rel="popover" data-img="" data-index="1" autofocus>
			    <div id="best-price-badge" style="display: none; font-size: 0.8em; margin-top: 3px; text-align: left;">
			      <span class="badge badge-success p-1" style="cursor: pointer;" onclick="openSupplierModal()" title="คลิกเพื่อดูประวัติราคาทุกร้าน">
			        <i class="fas fa-trophy"></i> ถูกสุด: <span id="best-shop-tag">-</span>
			      </span>
			      <span id="last-shop-badge" class="badge badge-secondary p-1 ml-1" style="cursor: pointer;" onclick="openSupplierModal()" title="คลิกเพื่อดูประวัติราคาทุกร้าน">
			        <i class="fas fa-history"></i> ล่าสุด: <span id="last-shop-tag">-</span>
			      </span>
			      <a href="javascript:void(0)" onclick="openSupplierModal()" class="text-primary ml-1" style="font-size: 0.9em; text-decoration: underline;"><i class="fas fa-search"></i> เทียบร้าน</a>
			    </div>
			  </div>
			  <div class='col-1 nopd-8'>
			    <input id='amount' type='number' onchange="cal_avg('amt')" class='form-control text-center no-spin' min='1' value="1" data-index="2">
			  </div>
			  <div class='col-1 nopd-8 popup'>
			  	<span class="popuptext" id="myPopup">เฉลี่ย : <span id="avg-rate"></span></span>
			    <input id='fund' type='number'  onchange="cal_avg('cost')" class='form-control text-center no-spin' min='0' data-index="3">
			  </div>
			  <div class='col-1 nopd-8'>
			    <input id='whole' type='number' class='form-control text-center no-spin' min='0' data-index="4">
			  </div>
			  <div class='col-1 nopd-8'>
			    <input id='retail' type='number' class='form-control text-center no-spin' min='0' data-index="5">
			  </div>
			  <div class="col-1 nopd text-center">
			  	<button class="btn btn-sm btn-primary" disabled> <i class="fas fa-pencil-alt"></i></button>
			  	<button class="btn btn-sm btn-danger" disabled> <i class="fa fa-trash"></i></button>
			  	<button class="btn btn-sm btn-success" hidden disabled> <i class="fa fa-check"></i></button>
			  </div>
			</div>`;

if (oldInputRegex.test(html)) {
    html = html.replace(oldInputRegex, newInput);
    console.log("Updated Input Row!");
} else {
    console.log("Input regex did not match!");
}

// 3. Add modal and javascript functions before </body>
const modalAndJs = `
	<!-- Modal ดูประวัติราคาทุกร้าน & ร้านที่ถูกที่สุด -->
	<div class="modal fade" id="supplierModal" tabindex="-1" role="dialog" aria-hidden="true">
	  <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
	    <div class="modal-content">
	      <div class="modal-header bg-dark text-white">
	        <h5 class="modal-title"><i class="fas fa-chart-line text-warning"></i> ประวัติราคาแต่ละร้าน & เลือกร้านที่ถูกที่สุด</h5>
	        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
	          <span aria-hidden="true">&times;</span>
	        </button>
	      </div>
	      <div class="modal-body">
	        <div class="d-flex justify-content-between align-items-center mb-3">
	          <h6 id="modal-supplier-prod-name" class="font-weight-bold text-primary mb-0"></h6>
	          <span class="badge badge-info p-2"><i class="fas fa-info-circle"></i> เรียงจากร้านที่ราคาถูกที่สุดไปแพง</span>
	        </div>
	        <div class="table-responsive">
	          <table class="table table-bordered table-hover table-sm text-center mb-0">
	            <thead class="thead-light">
	              <tr>
	                <th style="width: 10%;">อันดับ</th>
	                <th style="width: 22%;">ร้านค้า (From)</th>
	                <th style="width: 18%;">ราคาทุนล่าสุด</th>
	                <th style="width: 20%;">วันที่สั่งล่าสุด</th>
	                <th style="width: 18%;">คำแนะนำ</th>
	                <th style="width: 12%;">เลือก</th>
	              </tr>
	            </thead>
	            <tbody id="supplier-history-tbody">
	              <!-- rows -->
	            </tbody>
	          </table>
	        </div>
	      </div>
	      <div class="modal-footer">
	        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">ปิดหน้าต่าง</button>
	      </div>
	    </div>
	  </div>
	</div>

	<script>
		var current_supplier_history = [];
		function get_supplier_history(pid, pname) {
			if (!pid) return;
			$.ajax({
				type: "POST",
				url: "php/get_supplier_prices.php",
				data: { 'prod_id': pid },
				dataType: 'json',
				success: function(data) {
					current_supplier_history = data || [];
					if (current_supplier_history.length > 0) {
						var best = current_supplier_history[0];
						$('#best-shop-tag').text(best.supplier + ' (' + best.min_cost + '.-)');
						
						var sortedByDate = current_supplier_history.slice().sort(function(a, b) {
							return new Date(b.last_date) - new Date(a.last_date);
						});
						var latest = sortedByDate[0];
						$('#last-shop-tag').text(latest.supplier + ' (' + (latest.last_cost || latest.min_cost) + '.-)');
						
						$('#best-price-badge').show();
						
						// Auto fill datalist
						var dl = $('#supplier-datalist');
						dl.empty();
						$.each(current_supplier_history, function(idx, item) {
							dl.append('<option value="' + item.supplier + '">ถูกสุด: ' + item.min_cost + ' บาท (สั่งล่าสุด: ' + (item.last_date ? item.last_date.split(' ')[0] : '-') + ')</option>');
						});
					} else {
						$('#best-price-badge').hide();
					}
				},
				error: function() {
					$('#best-price-badge').hide();
				}
			});
		}

		function openSupplierModal() {
			var pname = $('#name').val() || 'สินค้า';
			$('#modal-supplier-prod-name').text('📦 ' + pname + ' (รหัส: ' + $('#barcode').val() + ')');
			var tbody = $('#supplier-history-tbody');
			tbody.empty();
			if (!current_supplier_history || current_supplier_history.length === 0) {
				tbody.append('<tr><td colspan="6" class="text-muted py-4"><i class="fas fa-box-open fa-2x mb-2 d-block"></i>ยังไม่มีประวัติการสั่งซื้อจากร้านค้าสำหรับสินค้านี้</td></tr>');
			} else {
				$.each(current_supplier_history, function(i, item) {
					var badge = '';
					if (item.is_best) {
						badge = '<span class="badge badge-success p-1"><i class="fas fa-star"></i> ถูกที่สุด (โทรเช็คก่อน)</span>';
					} else {
						var diff = parseFloat(item.min_cost) - parseFloat(current_supplier_history[0].min_cost);
						badge = '<span class="badge badge-warning text-dark p-1">+ ' + diff.toFixed(0) + ' บาท</span>';
					}
					var rowHtml = '<tr>' +
						'<td class="align-middle font-weight-bold">' + (i === 0 ? '🥇 1' : (i === 1 ? '🥈 2' : (i === 2 ? '🥉 3' : (i+1)))) + '</td>' +
						'<td class="align-middle font-weight-bold text-primary">' + item.supplier + '</td>' +
						'<td class="align-middle font-weight-bold text-success">' + item.min_cost + ' ฿</td>' +
						'<td class="align-middle text-muted">' + (item.last_date ? item.last_date.split(' ')[0] : '-') + '</td>' +
						'<td class="align-middle">' + badge + '</td>' +
						'<td class="align-middle"><button type="button" class="btn btn-sm btn-outline-primary" onclick="selectSupplierChoice(\\'' + item.supplier + '\\', ' + item.min_cost + ')">เลือก</button></td>' +
					'</tr>';
					tbody.append(rowHtml);
				});
			}
			$('#supplierModal').modal('show');
		}

		function selectSupplierChoice(supplier, cost) {
			$('#from_shop').val(supplier);
			if (cost) {
				$('#fund').val(cost);
			}
			$('#supplierModal').modal('hide');
			$('#at').focus();
		}
	</script>
`;

if (!html.includes('id="supplierModal"')) {
    html = html.replace('<!-- Central Navbar Script -->', modalAndJs + '\n\t<!-- Central Navbar Script -->');
    console.log("Added Modal & JS functions!");
}

fs.writeFileSync(path, html, 'utf8');
console.log("Successfully updated web_import.html!");
