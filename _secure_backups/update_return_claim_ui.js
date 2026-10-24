const fs = require('fs');

let file = fs.readFileSync('c:/ddcar/www/web_return_claim.html', 'utf8');

// 1. Update the table header in #table-returns-list
const oldHeader = `<thead class="thead-dark">
                  <tr>
                    <th style="width: 14%;">เลขที่เอกสาร RT</th>
                    <th style="width: 12%;">วันที่รับคืนจริง</th>
                    <th style="width: 14%;">อ้างอิงบิลเดิม</th>
                    <th style="width: 20%;">ลูกค้า</th>
                    <th style="width: 12%;" class="text-center">ประเภทการคืน</th>
                    <th style="width: 14%;" class="text-right">ยอดเงินคืน (บาท)</th>
                    <th style="width: 14%;" class="text-center">จัดการ</th>
                  </tr>
                </thead>`;

const newHeader = `<thead class="thead-dark">
                  <tr>
                    <th style="width: 13%;">เลขที่เอกสาร RT</th>
                    <th style="width: 11%;">วันที่รับคืนจริง</th>
                    <th style="width: 13%;">อ้างอิงบิลเดิม</th>
                    <th style="width: 17%;">ลูกค้า</th>
                    <th style="width: 10%;" class="text-center">ประเภทการคืน</th>
                    <th style="width: 10%;" class="text-center">สถานะ</th>
                    <th style="width: 12%;" class="text-right">ยอดเงินคืน (บาท)</th>
                    <th style="width: 14%;" class="text-center">จัดการ</th>
                  </tr>
                </thead>`;

file = file.replace(oldHeader, newHeader);
file = file.replace(/colspan="7"/g, 'colspan="8"');

// 2. Update platform refund input note
file = file.replace(
  'placeholder="เช่น 241024-REF-0012 (ไม่บังคับ)"',
  'placeholder="เช่น 241024-REF-0012 (จำเป็นต้องระบุถ้าไม่มี Order SN)"'
);

// 3. Update submitProductReturn validation for platform refunds
const oldSubRetValidation = `var reason = ($('#in-return-reason').val() || '').trim();

      if (!customerName) {`;

const newSubRetValidation = `var reason = ($('#in-return-reason').val() || '').trim();

      var refTypeCheck = $('#in-refund-type').val();
      var platIdCheck = ($('#in-platform-refund-id').val() || '').trim();
      var orderSnCheck = window.currentLoadedBill ? (window.currentLoadedBill.order_sn || '').trim() : '';

      if (refTypeCheck === 'คืนเงินผ่านแพลตฟอร์ม' && !platIdCheck && !orderSnCheck) {
        $.alert({
          title: '⚠️ ระบุเลขอ้างอิงแพลตฟอร์ม',
          content: 'การคืนเงินผ่านแพลตฟอร์ม จำเป็นต้องระบุเลขคำสั่งซื้อ (Order SN) หรือรหัสคำขอคืนเงินของแพลตฟอร์ม (Return/Refund ID)',
          type: 'orange'
        });
        $('#in-platform-refund-id').focus();
        return;
      }

      if (!customerName) {`;

file = file.replace(oldSubRetValidation, newSubRetValidation);

// 4. Update renderReturnsTable and viewReturnDetail and add confirmApproveReturn
const oldRenderReturns = `    function renderReturnsTable(data) {
      var $tbody = $('#tbody-returns-list');
      $tbody.empty();

      if (!data || data.length === 0) {
        $tbody.html('<tr><td colspan="7" class="text-center py-4 text-muted">ไม่พบประวัติเอกสารรับคืนสินค้า</td></tr>');
        return;
      }

      data.forEach(function(row) {
        var typeBadge = '<span class="badge badge-dest-stock px-2 py-1">ขายได้</span>';
        if (row.return_type === 'แตกหัก_เสีย') {
          typeBadge = '<span class="badge badge-dest-quarantine px-2 py-1">เข้าคลังรอเคลม</span>';
        } else if (row.return_type === 'ผสม') {
          typeBadge = '<span class="badge badge-warning text-dark px-2 py-1">ผสม (ขายได้+เคลม)</span>';
        }

        var cnTag = row.credit_note_number ? ('<br><span class="badge badge-info font-mono">CN: ' + row.credit_note_number + '</span>') : '';
        var billRef = row.bill_number ? ('<span class="font-weight-bold text-primary">' + row.bill_number + '</span>') : (row.order_sn ? ('<span class="font-mono text-muted small">' + row.order_sn + '</span>') : '<span class="text-muted">(ไม่มีบิลเดิม)</span>');

        var tr = '<tr>' +
          '<td class="font-weight-bold font-mono text-dark">' + row.return_code + cnTag + '</td>' +
          '<td>' + row.actual_return_date + '<br><small class="text-muted">บันทึก: ' + row.return_date + '</small></td>' +
          '<td>' + billRef + '</td>' +
          '<td class="font-weight-bold">' + escapeHtml(row.customer_name) + '</td>' +
          '<td class="text-center">' + typeBadge + '</td>' +
          '<td class="text-right font-weight-bold text-danger">฿' + (row.total_refund_amount || 0).toLocaleString('th-TH', {minimumFractionDigits: 2}) + '<br><small class="text-muted">' + row.refund_type + '</small></td>' +
          '<td class="text-center">' +
            '<button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="viewReturnDetail(' + row.id + ')"><i class="fas fa-eye mr-1"></i>ดูรายการ</button>' +
          '</td>' +
        '</tr>';
        $tbody.append(tr);
      });
    }`;

const newRenderReturns = `    function renderReturnsTable(data) {
      var $tbody = $('#tbody-returns-list');
      $tbody.empty();

      if (!data || data.length === 0) {
        $tbody.html('<tr><td colspan="8" class="text-center py-4 text-muted">ไม่พบประวัติเอกสารรับคืนสินค้า</td></tr>');
        return;
      }

      var userRole = localStorage.user_role || 'พนักงาน';
      var isOwnerOrAdmin = (userRole === 'แอดมิน' || userRole === 'เจ้าของร้าน' || userRole === 'ผู้ดูแลระบบ');

      data.forEach(function(row) {
        var typeBadge = '<span class="badge badge-dest-stock px-2 py-1">ขายได้</span>';
        if (row.return_type === 'แตกหัก_เสีย') {
          typeBadge = '<span class="badge badge-dest-quarantine px-2 py-1">เข้าคลังรอเคลม</span>';
        } else if (row.return_type === 'ผสม') {
          typeBadge = '<span class="badge badge-warning text-dark px-2 py-1">ผสม (ขายได้+เคลม)</span>';
        }

        var cnTag = row.credit_note_number ? ('<br><span class="badge badge-info font-mono">CN: ' + row.credit_note_number + '</span>') : '';
        var billRef = row.bill_number ? ('<span class="font-weight-bold text-primary">' + row.bill_number + '</span>') : (row.order_sn ? ('<span class="font-mono text-muted small">' + row.order_sn + '</span>') : '<span class="text-muted">(ไม่มีบิลเดิม)</span>');

        var stBadge = '<span class="badge badge-success px-2 py-1"><i class="fas fa-check mr-1"></i>สมบูรณ์</span>';
        if (row.status === 'รออนุมัติ') {
          stBadge = '<span class="badge badge-warning text-dark px-2 py-1 shadow-sm font-weight-bold"><i class="fas fa-clock mr-1"></i>รออนุมัติ</span>';
        } else if (row.status && row.status.indexOf('ยกเลิก') !== -1) {
          stBadge = '<span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i>ไม่อนุมัติ</span>';
        }

        var actionBtns = '<button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="viewReturnDetail(' + row.id + ')"><i class="fas fa-eye mr-1"></i>ดูรายการ</button>';
        if (isOwnerOrAdmin && row.status === 'รออนุมัติ') {
          actionBtns += ' <button class="btn btn-sm btn-success py-0 px-2 ml-1 font-weight-bold" title="อนุมัติและเพิ่มสต๊อก" onclick="confirmApproveReturn(' + row.id + ', \\'' + row.return_code + '\\', \\'approve\\')"><i class="fas fa-check"></i> อนุมัติ</button>' +
                        ' <button class="btn btn-sm btn-outline-danger py-0 px-2 ml-1" title="ไม่อนุมัติ" onclick="confirmApproveReturn(' + row.id + ', \\'' + row.return_code + '\\', \\'reject\\')"><i class="fas fa-times"></i></button>';
        }

        var tr = '<tr>' +
          '<td class="font-weight-bold font-mono text-dark">' + row.return_code + cnTag + '</td>' +
          '<td>' + row.actual_return_date + '<br><small class="text-muted">บันทึก: ' + row.return_date + '</small></td>' +
          '<td>' + billRef + '</td>' +
          '<td class="font-weight-bold">' + escapeHtml(row.customer_name) + '</td>' +
          '<td class="text-center">' + typeBadge + '</td>' +
          '<td class="text-center">' + stBadge + '</td>' +
          '<td class="text-right font-weight-bold text-danger">฿' + (row.total_refund_amount || 0).toLocaleString('th-TH', {minimumFractionDigits: 2}) + '<br><small class="text-muted">' + row.refund_type + '</small></td>' +
          '<td class="text-center text-nowrap">' + actionBtns + '</td>' +
        '</tr>';
        $tbody.append(tr);
      });
    }`;

file = file.replace(oldRenderReturns, newRenderReturns);

// 5. Update viewReturnDetail to show approval status and banner
const oldViewDetailEnd = `<div class="small text-muted d-flex justify-content-between">' +
          '<div>ผู้ทำรายการ: ' + (item.created_by || '-') + '</div>' +
          '<div>เวลาบันทึก: ' + item.created_at + '</div>' +
        '</div>';

      $('#detail-modal-title').html('<i class="fas fa-file-invoice mr-2 text-primary"></i>รายละเอียดเอกสาร ' + item.return_code);`;

const newViewDetailEnd = `<div class="small text-muted d-flex justify-content-between mb-3">' +
          '<div>ผู้ทำรายการ: ' + (item.created_by || '-') + ' ' + (item.approved_by ? ('(อนุมัติโดย: ' + item.approved_by + ')') : '') + '</div>' +
          '<div>เวลาบันทึก: ' + item.created_at + '</div>' +
        '</div>' + approvalActionCard;

      $('#detail-modal-title').html('<i class="fas fa-file-invoice mr-2 text-primary"></i>รายละเอียดเอกสาร ' + item.return_code);`;

file = file.replace(oldViewDetailEnd, newViewDetailEnd);

// Also add approvalActionCard inside viewReturnDetail
const oldViewDetailStart = `function viewReturnDetail(retId) {
      var item = window.allReturnsData.find(function(x) { return x.id == retId; });
      if (!item) return;`;

const newViewDetailStart = `function viewReturnDetail(retId) {
      var item = window.allReturnsData.find(function(x) { return x.id == retId; });
      if (!item) return;

      var userRole = localStorage.user_role || 'พนักงาน';
      var isOwnerOrAdmin = (userRole === 'แอดมิน' || userRole === 'เจ้าของร้าน' || userRole === 'ผู้ดูแลระบบ');

      var approvalActionCard = '';
      if (isOwnerOrAdmin && item.status === 'รออนุมัติ') {
        approvalActionCard = 
          '<div class="alert alert-warning border-warning d-flex justify-content-between align-items-center mb-0 mt-3 p-3">' +
            '<div><i class="fas fa-shield-alt mr-2 fa-lg text-warning"></i><strong>คำขอนี้รอการอนุมัติ:</strong> สต๊อกสินค้ายังถูกกักไว้จนกว่าจะกดอนุมัติ</div>' +
            '<div>' +
              '<button class="btn btn-sm btn-success mr-2 font-weight-bold" onclick="confirmApproveReturn(' + item.id + ', \\'' + item.return_code + '\\', \\'approve\\')"><i class="fas fa-check-circle mr-1"></i>อนุมัติ (เพิ่มสต๊อก)</button>' +
              '<button class="btn btn-sm btn-outline-danger" onclick="confirmApproveReturn(' + item.id + ', \\'' + item.return_code + '\\', \\'reject\\')"><i class="fas fa-times mr-1"></i>ไม่อนุมัติ</button>' +
            '</div>' +
          '</div>';
      }`;

file = file.replace(oldViewDetailStart, newViewDetailStart);

// 6. Add confirmApproveReturn function before loadPendingClaims
const oldLoadPending = `function loadPendingClaims() {`;
const newConfirmApprove = `function confirmApproveReturn(retId, retCode, action) {
      var isApprove = (action === 'approve');
      var titleText = isApprove ? 'ยืนยันอนุมัติเอกสารรับคืน' : 'ยืนยันปฏิเสธ/ไม่อนุมัติเอกสารรับคืน';
      var btnClass = isApprove ? 'btn-success' : 'btn-danger';
      var contentText = isApprove 
        ? 'คุณต้องการอนุมัติเอกสารรับคืน <strong>' + retCode + '</strong> ใช่หรือไม่?<br><small class="text-success">* สินค้าจะถูกเพิ่มเข้าสต๊อกขาย (หรือส่งเข้าคลังเคลม) ทันที</small>'
        : 'คุณต้องการไม่อนุมัติเอกสารรับคืน <strong>' + retCode + '</strong> ใช่หรือไม่?<br><small class="text-danger">* เอกสารจะถูกปรับเป็นสถานะยกเลิก และไม่มีการแตะต้องสต๊อกสินค้า</small>';

      $.confirm({
        title: titleText,
        content: contentText,
        type: isApprove ? 'green' : 'red',
        buttons: {
          confirm: {
            text: isApprove ? 'ยืนยันอนุมัติ' : 'ยืนยันไม่อนุมัติ',
            btnClass: btnClass,
            action: function() {
              $.ajax({
                url: 'php/approve_product_return.php',
                type: 'POST',
                data: JSON.stringify({ return_id: retId, action: action }),
                contentType: 'application/json; charset=utf-8',
                dataType: 'json',
                success: function(res) {
                  if (res && res.success) {
                    $('#ReturnDetailModal').modal('hide');
                    $.alert({
                      title: 'สำเร็จ',
                      content: res.message || 'บันทึกรายการสำเร็จ',
                      type: 'green',
                      onClose: function() {
                        loadReturnsList();
                        loadSummaryStats();
                      }
                    });
                  } else {
                    $.alert({
                      title: 'ไม่สำเร็จ',
                      content: (res && res.error) ? res.error : 'เกิดข้อผิดพลาดในการดำเนินการ',
                      type: 'red'
                    });
                  }
                },
                error: function() {
                  $.alert({ title: 'ข้อผิดพลาด', content: 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', type: 'red' });
                }
              });
            }
          },
          cancel: { text: 'ยกเลิก' }
        }
      });
    }

    function loadPendingClaims() {`;

file = file.replace(oldLoadPending, newConfirmApprove);

fs.writeFileSync('c:/ddcar/www/web_return_claim.html', file, 'utf8');
console.log('web_return_claim.html updated successfully!');
