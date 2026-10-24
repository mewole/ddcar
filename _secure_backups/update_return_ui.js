const fs = require('fs');

let html = fs.readFileSync('c:/ddcar/www/web_return_claim.html', 'utf8');

// 1. Update in-branch-select with data-id and data-type (N3)
const oldBranchSelect = `<select id="in-branch-select" class="form-control font-weight-bold">
                  <option value="หน้าร้าน">หน้าร้าน (DD MOTOR SPORT)</option>
                  <option value="โกดัง">โกดัง (Warehouse)</option>
                </select>`;

const newBranchSelect = `<select id="in-branch-select" class="form-control font-weight-bold">
                  <option value="shop_6" data-type="หน้าร้าน" data-id="6" selected>หน้าร้าน (DD MOTOR SPORT)</option>
                  <option value="ware_1" data-type="โกดัง" data-id="1">โกดัง A</option>
                  <option value="ware_2" data-type="โกดัง" data-id="2">โกดัง B</option>
                  <option value="ware_3" data-type="โกดัง" data-id="3">โกดัง C</option>
                  <option value="ware_4" data-type="โกดัง" data-id="4">โกดัง D</option>
                  <option value="ware_5" data-type="โกดัง" data-id="5">พระราม 5</option>
                </select>
                <small id="lbl-orig-sold-branch" class="text-danger font-weight-bold d-block mt-1"></small>`;

if (html.includes(oldBranchSelect)) {
    html = html.replace(oldBranchSelect, newBranchSelect);
    console.log('1. in-branch-select updated');
} else {
    // Try LF
    const oldBranchSelectLF = oldBranchSelect.replace(/\r\n/g, '\n');
    if (html.includes(oldBranchSelectLF)) {
        html = html.replace(oldBranchSelectLF, newBranchSelect);
        console.log('1. in-branch-select updated (LF)');
    } else {
        console.error('1. Could not match oldBranchSelect!');
    }
}

// 2. Add Select All / Deselect All buttons (N5)
const oldTableHead = `<div id="btn-add-manual-item-wrap" style="display:none;">
                  <button type="button" class="btn btn-outline-primary btn-sm font-weight-bold" onclick="openManualProductSelect()">
                    <i class="fas fa-plus mr-1"></i> เลือกสินค้าจากคลัง DDShop
                  </button>
                </div>`;

const newTableHead = `<div>
                  <button type="button" class="btn btn-outline-secondary btn-sm mr-1 font-weight-bold" onclick="toggleSelectAllItems(true)"><i class="fas fa-check-square mr-1"></i>เลือกทั้งหมด</button>
                  <button type="button" class="btn btn-outline-secondary btn-sm mr-2 font-weight-bold" onclick="toggleSelectAllItems(false)"><i class="fas fa-square mr-1"></i>ล้างการเลือก</button>
                  <span id="btn-add-manual-item-wrap" style="display:none;">
                    <button type="button" class="btn btn-outline-primary btn-sm font-weight-bold" onclick="openManualProductSelect()">
                      <i class="fas fa-plus mr-1"></i> เลือกสินค้าจากคลัง DDShop
                    </button>
                  </span>
                </div>`;

if (html.includes(oldTableHead)) {
    html = html.replace(oldTableHead, newTableHead);
    console.log('2. Select all buttons added');
} else {
    const oldTableHeadLF = oldTableHead.replace(/\r\n/g, '\n');
    if (html.includes(oldTableHeadLF)) {
        html = html.replace(oldTableHeadLF, newTableHead);
        console.log('2. Select all buttons added (LF)');
    } else {
        console.error('2. Could not match oldTableHead!');
    }
}

// 3. Add Platform Refund option and input field (N4)
const oldRefundSelect = `<option value="เปลี่ยนสินค้า">เปลี่ยนเป็นสินค้าอื่น</option>
                        <option value="ไม่คืนเงิน">ไม่คืนเงิน (รับคืนสต๊อกเฉยๆ)</option>`;

const newRefundSelect = `<option value="เปลี่ยนสินค้า">เปลี่ยนเป็นสินค้าอื่น</option>
                        <option value="คืนเงินผ่านแพลตฟอร์ม">คืนเงินผ่านแพลตฟอร์ม (Shopee / Lazada / TikTok)</option>
                        <option value="ไม่คืนเงิน">ไม่คืนเงิน (รับคืนสต๊อกเฉยๆ)</option>`;

if (html.includes(oldRefundSelect)) {
    html = html.replace(oldRefundSelect, newRefundSelect);
    console.log('3. Platform refund option added');
} else {
    const oldRefundSelectLF = oldRefundSelect.replace(/\r\n/g, '\n');
    if (html.includes(oldRefundSelectLF)) {
        html = html.replace(oldRefundSelectLF, newRefundSelect);
        console.log('3. Platform refund option added (LF)');
    } else {
        console.error('3. Could not match oldRefundSelect!');
    }
}

// Add platform refund ID box below the row
const refundRowEnd = `<div class="col-6">
                      <label class="small text-muted font-weight-bold mb-1">ยอดเงินคืนสุทธิ (บาท) *</label>
                      <input type="number" step="0.01" min="0" id="in-total-refund-amount" class="form-control font-weight-bold text-right text-danger" placeholder="0.00">
                    </div>
                  </div>`;

const newRefundRowEnd = `<div class="col-6">
                      <label class="small text-muted font-weight-bold mb-1">ยอดเงินคืนสุทธิ (บาท) *</label>
                      <input type="number" step="0.01" min="0" id="in-total-refund-amount" class="form-control font-weight-bold text-right text-danger" placeholder="0.00">
                    </div>
                  </div>
                  <div id="box-platform-refund" class="mt-2" style="display:none;">
                    <label class="small text-primary font-weight-bold mb-1"><i class="fas fa-shopping-bag mr-1"></i>เลขคำขอคืนเงินของแพลตฟอร์ม (Return/Refund ID)</label>
                    <input type="text" id="in-platform-refund-id" class="form-control form-control-sm" placeholder="เช่น 241024-REF-0012 (ไม่บังคับ)">
                  </div>`;

if (html.includes(refundRowEnd)) {
    html = html.replace(refundRowEnd, newRefundRowEnd);
    console.log('3.1 Platform refund ID input added');
} else {
    const refundRowEndLF = refundRowEnd.replace(/\r\n/g, '\n');
    if (html.includes(refundRowEndLF)) {
        html = html.replace(refundRowEndLF, newRefundRowEnd);
        console.log('3.1 Platform refund ID input added (LF)');
    } else {
        console.error('3.1 Could not match refundRowEnd!');
    }
}

// 4. Update populateBillToForm, item table rendering, changeItemQty, and submitProductReturn
const oldPopulate = `// Populate Items
      window.returnFormItems = (bill.items || []).map(function(it) {
        return {
          item_id: it.item_id,
          prod_id: it.prod_id,
          prod_code: it.prod_code,
          prod_name: it.prod_name,
          img: it.img,
          unit_price: it.unit_price,
          cost: it.cost,
          qty_sold: it.qty_sold,
          qty_available: it.qty_available,
          return_qty: it.qty_available > 0 ? 1 : 0,
          destination: 'stock', // 'stock' or 'quarantine'
          supplier_name: it.suggested_supplier || '',
          damage_reason: '',
          damage_images: [],
          selected: it.qty_available > 0
        };
      });`;

const newPopulate = `// N3: Show original sold location
      var origBranch = bill.branch_type || (bill.branch_id === 6 ? 'หน้าร้าน' : ('โกดัง ' + (bill.branch_id || '')));
      $('#lbl-orig-sold-branch').text('📍 ตัดขายจาก: ' + origBranch);

      // N4: Auto-detect platform orders
      var isOnline = (bill.customer_name && (bill.customer_name.includes('Tiktok') || bill.customer_name.includes('TikTok') || bill.customer_name.includes('Shopee') || bill.customer_name.includes('Lazada'))) || (bill.order_sn && bill.order_sn.trim() !== '');
      if (isOnline) {
        $('#in-refund-type').val('คืนเงินผ่านแพลตฟอร์ม');
        $('#box-platform-refund').slideDown();
      } else {
        $('#in-refund-type').val('คืนเงินสด');
        $('#box-platform-refund').slideUp();
      }

      // Pro-rate discount ratio
      var discountRatio = 1;
      if (bill.sum_price > 0 && bill.total_discount > 0) {
        discountRatio = Math.max(0, 1 - (bill.total_discount / bill.sum_price));
      }

      // N5: Populate Items - Default selected: false (พนักงานต้องติ๊กเลือกเอง)
      window.returnFormItems = (bill.items || []).map(function(it) {
        return {
          item_id: it.item_id,
          prod_id: it.prod_id,
          prod_code: it.prod_code,
          prod_name: it.prod_name,
          img: it.img,
          unit_price: it.unit_price,
          cost: it.cost,
          discount_ratio: discountRatio,
          qty_sold: it.qty_sold,
          qty_available: it.qty_available,
          return_qty: it.qty_available > 0 ? 1 : 0,
          destination: 'stock', // 'stock' or 'quarantine'
          supplier_name: it.suggested_supplier || '',
          damage_reason: '',
          damage_images: [],
          selected: false // N5: Default unselected
        };
      });`;

if (html.includes(oldPopulate)) {
    html = html.replace(oldPopulate, newPopulate);
    console.log('4. populateBillToForm updated');
} else {
    const oldPopulateLF = oldPopulate.replace(/\r\n/g, '\n');
    if (html.includes(oldPopulateLF)) {
        html = html.replace(oldPopulateLF, newPopulate);
        console.log('4. populateBillToForm updated (LF)');
    } else {
        console.error('4. Could not match oldPopulate!');
    }
}

// 5. Update renderReturnItemsTable for disabled state on qty_available <= 0
const oldTableRow = `var chkBox = '<input type="checkbox" class="chk-item" data-idx="' + idx + '" ' + (item.selected ? 'checked' : '') + ' onchange="toggleItemSelect(' + idx + ', this.checked)">';
        var imgThumb = '<img src="' + item.img + '" class="img-thumb-preview" onerror="this.src=\\'img/no_img.png\\'">';
        var skuBadge = '<span class="badge badge-dark font-mono px-2 py-1">' + escapeHtml(item.prod_code) + '</span>';
        var nameSpan = '<span class="font-weight-bold text-dark">' + escapeHtml(item.prod_name) + '</span>';
        var qtyInfo = '<div class="small text-muted font-weight-bold">' + item.qty_sold + ' / <span class="text-success">' + item.qty_available + '</span></div>';
        
        var qtyInput = '<input type="number" min="1" max="' + item.qty_available + '" class="form-control form-control-sm text-center font-weight-bold item-qty-input" value="' + item.return_qty + '" onchange="changeItemQty(' + idx + ', this.value)">';`;

const newTableRow = `var isAvail = (item.qty_available > 0);
        var chkBox = isAvail
          ? '<input type="checkbox" class="chk-item" data-idx="' + idx + '" ' + (item.selected ? 'checked' : '') + ' onchange="toggleItemSelect(' + idx + ', this.checked)">'
          : '<span class="badge badge-secondary" style="font-size:0.75rem;">คืนครบแล้ว</span>';
        var imgThumb = '<img src="' + item.img + '" class="img-thumb-preview" onerror="this.src=\\'img/no_img.png\\'">';
        var skuBadge = '<span class="badge badge-dark font-mono px-2 py-1">' + escapeHtml(item.prod_code) + '</span>';
        var nameSpan = '<span class="font-weight-bold text-dark">' + escapeHtml(item.prod_name) + '</span>';
        var qtyInfo = '<div class="small text-muted font-weight-bold">' + item.qty_sold + ' / <span class="' + (isAvail ? 'text-success' : 'text-danger') + '">' + item.qty_available + '</span></div>';
        
        var qtyInput = isAvail
          ? '<input type="number" min="1" max="' + item.qty_available + '" class="form-control form-control-sm text-center font-weight-bold item-qty-input" value="' + item.return_qty + '" onchange="changeItemQty(' + idx + ', this.value)">'
          : '<input type="number" class="form-control form-control-sm text-center font-weight-bold" value="0" disabled>';`;

if (html.includes(oldTableRow)) {
    html = html.replace(oldTableRow, newTableRow);
    console.log('5. Table row with disabled state updated');
} else {
    const oldTableRowLF = oldTableRow.replace(/\r\n/g, '\n');
    if (html.includes(oldTableRowLF)) {
        html = html.replace(oldTableRowLF, newTableRow);
        console.log('5. Table row with disabled state updated (LF)');
    } else {
        console.error('5. Could not match oldTableRow!');
    }
}

// 6. Fix changeItemQty 0 || 999 bug and add toggleSelectAllItems
const oldChangeQty = `function changeItemQty(idx, val) {
      var num = parseInt(val) || 1;
      if (window.returnFormItems[idx]) {
        var max = window.returnFormItems[idx].qty_available || 999;
        if (num > max && window.currentLoadedBill) {
          num = max;
        }
        window.returnFormItems[idx].return_qty = Math.max(1, num);
        renderReturnItemsTable();
        calculateRefundTotals();
      }
    }`;

const newChangeQty = `function toggleSelectAllItems(selectAll) {
      if (window.returnFormItems) {
        window.returnFormItems.forEach(function(it) {
          if (it.qty_available > 0) {
            it.selected = selectAll;
          }
        });
        renderReturnItemsTable();
        calculateRefundTotals();
      }
    }

    function changeItemQty(idx, val) {
      var num = parseInt(val) || 1;
      if (window.returnFormItems[idx]) {
        var avail = window.returnFormItems[idx].qty_available;
        var max = (avail !== undefined && avail !== null) ? parseInt(avail) : 999;
        if (max <= 0) {
          window.returnFormItems[idx].return_qty = 0;
          window.returnFormItems[idx].selected = false;
        } else {
          if (num > max && window.currentLoadedBill) {
            num = max;
          }
          window.returnFormItems[idx].return_qty = Math.max(1, num);
        }
        renderReturnItemsTable();
        calculateRefundTotals();
      }
    }`;

if (html.includes(oldChangeQty)) {
    html = html.replace(oldChangeQty, newChangeQty);
    console.log('6. changeItemQty bug fixed & toggleSelectAllItems added');
} else {
    const oldChangeQtyLF = oldChangeQty.replace(/\r\n/g, '\n');
    if (html.includes(oldChangeQtyLF)) {
        html = html.replace(oldChangeQtyLF, newChangeQty);
        console.log('6. changeItemQty bug fixed & toggleSelectAllItems added (LF)');
    } else {
        console.error('6. Could not match oldChangeQty!');
    }
}

// 7. Update calculateRefundTotals for pro-rated discount
const oldCalc = `function calculateRefundTotals() {
      var sumRefund = 0;
      window.returnFormItems.forEach(function(it) {
        if (it.selected) {
          sumRefund += (it.unit_price * it.return_qty);
        }
      });

      var refType = $('#in-refund-type').val();
      if (refType === 'ไม่คืนเงิน') {
        sumRefund = 0;
      }`;

const newCalc = `function calculateRefundTotals() {
      var sumRefund = 0;
      window.returnFormItems.forEach(function(it) {
        if (it.selected) {
          var ratio = it.discount_ratio !== undefined ? it.discount_ratio : 1;
          sumRefund += (it.unit_price * ratio * it.return_qty);
        }
      });

      var refType = $('#in-refund-type').val();
      if (refType === 'คืนเงินผ่านแพลตฟอร์ม') {
        $('#box-platform-refund').slideDown();
      } else {
        $('#box-platform-refund').slideUp();
      }
      if (refType === 'ไม่คืนเงิน') {
        sumRefund = 0;
      }`;

if (html.includes(oldCalc)) {
    html = html.replace(oldCalc, newCalc);
    console.log('7. calculateRefundTotals pro-rated discount updated');
} else {
    const oldCalcLF = oldCalc.replace(/\r\n/g, '\n');
    if (html.includes(oldCalcLF)) {
        html = html.replace(oldCalcLF, newCalc);
        console.log('7. calculateRefundTotals pro-rated discount updated (LF)');
    } else {
        console.error('7. Could not match oldCalc!');
    }
}

// 8. Update submitProductReturn for dynamic branch and N3 confirmation
const oldSubmitBranch = `items: selectedItems.map(function(it) {
          return {
            prod_id: it.prod_id,
            prod_code: it.prod_code,
            prod_name: it.prod_name,
            qty: it.return_qty,
            unit_price: it.unit_price,
            cost: it.cost,
            destination: it.destination,
            branch_id: 1,
            branch_type: $('#in-branch-select').val(),
            damage_reason: it.damage_reason,
            supplier_name: it.supplier_name
          };
        })`;

const newSubmitBranch = `items: selectedItems.map(function(it) {
          var selOpt = $('#in-branch-select option:selected');
          var bId = parseInt(selOpt.data('id')) || 6;
          var bType = selOpt.data('type') || 'หน้าร้าน';
          return {
            prod_id: it.prod_id,
            prod_code: it.prod_code,
            prod_name: it.prod_name,
            qty: it.return_qty,
            unit_price: it.unit_price,
            cost: it.cost,
            destination: it.destination,
            branch_id: bId,
            branch_type: bType,
            damage_reason: it.damage_reason,
            supplier_name: it.supplier_name
          };
        })`;

if (html.includes(oldSubmitBranch)) {
    html = html.replace(oldSubmitBranch, newSubmitBranch);
    console.log('8. submitProductReturn dynamic branch updated');
} else {
    const oldSubmitBranchLF = oldSubmitBranch.replace(/\r\n/g, '\n');
    if (html.includes(oldSubmitBranchLF)) {
        html = html.replace(oldSubmitBranchLF, newSubmitBranch);
        console.log('8. submitProductReturn dynamic branch updated (LF)');
    } else {
        console.error('8. Could not match oldSubmitBranch!');
    }
}

// Update confirm dialog with N3 summary
const oldConfirm = `title: 'ยืนยันบันทึกเอกสารรับคืนสินค้า',
        content: 'ต้องการบันทึกเอกสารรับคืนสินค้าจำนวน <strong>' + selectedItems.length + ' รายการ</strong> หรือไม่?<br><br><small class="text-muted">• ระบบจะสร้างเลขที่ RT26-xxxxx ต่อเนื่องอัตโนมัติ<br>• สต๊อกขายจะเพิ่มเฉพาะรายการที่ "ขายได้"<br>• รายการ "แตกหัก" จะเข้าคลังรอเคลม</small>',`;

const newConfirm = `title: 'ยืนยันบันทึกเอกสารรับคืนสินค้า',
        content: (function() {
          var selOpt = $('#in-branch-select option:selected');
          var bName = selOpt.text();
          var origSold = window.currentLoadedBill ? (window.currentLoadedBill.branch_name || window.currentLoadedBill.branch_type || 'หน้าร้าน') : 'ไม่ระบุ';
          var refTxt = $('#in-refund-type').val();
          var totalAmt = parseFloat($('#in-total-refund-amount').val()) || 0;
          return '<div class="alert alert-warning py-2 mb-3 text-center font-weight-bold" style="font-size:0.95rem;">' +
                 '📍 ตัดขายจาก: <span class="text-danger">' + escapeHtml(origSold) + '</span> ➔ รับคืนเข้า: <span class="text-success">' + escapeHtml(bName) + '</span>' +
                 '</div>' +
                 '<div class="mb-2">จำนวนรายการรับคืน: <strong>' + selectedItems.length + ' รายการ</strong></div>' +
                 '<div class="mb-2">รูปแบบการคืนเงิน: <strong>' + escapeHtml(refTxt) + '</strong></div>' +
                 '<div class="mb-2">ยอดคืนสุทธิ: <strong class="text-danger">฿' + totalAmt.toLocaleString('th-TH', {minimumFractionDigits: 2}) + '</strong></div>' +
                 '<small class="text-muted d-block mt-3">• ระบบจะสร้างเลขที่ RT26-xxxxx ต่อเนื่องอัตโนมัติ<br>• สต๊อกจะเพิ่มเฉพาะรายการที่เลือก "ขายได้"<br>• รายการ "แตกหัก" จะส่งเข้าคลังรอเคลม</small>';
        })(),`;

if (html.includes(oldConfirm)) {
    html = html.replace(oldConfirm, newConfirm);
    console.log('8.1 Confirmation dialog N3 summary added');
} else {
    const oldConfirmLF = oldConfirm.replace(/\r\n/g, '\n');
    if (html.includes(oldConfirmLF)) {
        html = html.replace(oldConfirmLF, newConfirm);
        console.log('8.1 Confirmation dialog N3 summary added (LF)');
    } else {
        console.error('8.1 Could not match oldConfirm!');
    }
}

fs.writeFileSync('c:/ddcar/www/web_return_claim.html', html, 'utf8');

// Verify script syntax in web_return_claim.html
const scriptMatches = html.match(/<script[\s\S]*?<\/script>/gi) || [];
let syntaxErrors = 0;
scriptMatches.forEach((m, idx) => {
    const tag = m.substring(0, m.indexOf('>') + 1);
    const code = m.replace(/<script[\s\S]*?>/i, '').replace(/<\/script>/i, '');
    if (code.trim() && !tag.includes('src=')) {
        try {
            new Function(code);
        } catch (e) {
            console.error('Syntax error in script block ' + (idx + 1) + ':', e.message);
            syntaxErrors++;
        }
    }
});

if (syntaxErrors === 0) {
    console.log('web_return_claim.html syntax is 100% VALID!');
} else {
    console.error('web_return_claim.html has ' + syntaxErrors + ' syntax errors!');
}
