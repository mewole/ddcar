const fs = require('fs');
const path = require('path');

const phpDir = 'c:/ddcar/www/php';

// Categorization Rules
const adminOnlyFiles = new Set([
  'delete_bill.php',
  'delete_prod.php',
  'delete_prod_bk.php',
  'delete_user.php',
  'delete_import.php',
  'delete_export.php',
  'delete_transfer.php',
  'delete_transfer_to.php',
  'delete_ware_shop.php',
  'delete_customer.php',
  'delete_cus.php',
  'delete_folder.php',
  'delete_folder_bk.php',
  'delete_place.php',
  'delete_platform_mapping.php',
  'delete_threshold.php',
  'backup_db.php',
  'register.php',
  'rename_user.php',
  'edit_user.php',
  'save_stock_adjustment.php',
  'set_cost.php',
  'set_cost_byprd.php',
  'save_cost_import.php',
  'save_supplier_sku.php',
  'save_bundle_recipe.php',
  'clear_pending_vat.php',
  'set_status_user.php',
  'update_user.php',
  'fix_channel_names.php',
  'migrate_adjustment.php',
  'migrate_images.php',
  'migrate_supplier_sku.php',
  'add_performance_indexes.php',
  'kill_zombies.php',
  'rename_bill.php',
  'restore_product.php',
  'setup_status_ext.php',
  'set_lastV.php'
]);

const staffOrAdminFiles = new Set([
  'save_order.php',
  'save_order_offline.php',
  'pay_credit.php',
  'stock_in.php',
  'save_import.php',
  'transfer_stock.php',
  'create_transfer.php',
  'confirm_transfer.php',
  'reject_transfer.php',
  'add_prod.php',
  'add_prod_bk.php',
  'edit_prod.php',
  'update_prod.php',
  'rename_prod.php',
  'rename_prod_web.php',
  'add_cus.php',
  'edit_cus.php',
  'update_customer.php',
  'rename_cus.php',
  'add_folder.php',
  'add_folder_bk.php',
  'rename_folder.php',
  'rename_folder_bk.php',
  'add_ware_shop.php',
  'update_ware_shop.php',
  'rename_ware_shop.php',
  'save_platform_orders.php',
  'import_platform_skus.php',
  'sync_platform_orders.php',
  'sync_platform_catalog.php',
  'save_platform_mapping.php',
  'save_platform_channel.php',
  'save_platform_sku.php',
  'update_platform_product_name.php',
  'save_pending_vat.php',
  'add_pending_vat.php',
  'save_place.php',
  'rename_place.php',
  'save_shift_bill.php',
  'save_export.php',
  'rename_export.php',
  'save_threshold.php',
  'update_threshold.php',
  'save_pic.php',
  'save_order_temp.php',
  'save_prod_temp.php',
  'rename_import.php',
  'rename_transfer.php',
  'save_credit.php',
  'update_credit_auto.php',
  'update_bell.php',
  'update_bell_status.php',
  'stock_out.php',
  'stock_out_web.php',
  'transfer_in.php'
]);

const exemptFiles = new Set([
  'login.php',
  'logout.php',
  'auth_guard.php',
  'db.php',
  'folder_helper.php',
  'img_helper.php',
  'my_ip.php'
]);

const files = fs.readdirSync(phpDir).filter(f => f.endsWith('.php'));
console.log(`Processing ${files.length} PHP files...`);

let modifiedFiles = [];
let adminGuarded = [];
let staffGuarded = [];
let loginGuarded = [];

files.forEach(filename => {
  if (exemptFiles.has(filename)) {
    console.log(`[EXEMPT] ${filename}`);
    return;
  }

  const filePath = path.join(phpDir, filename);
  let content = fs.readFileSync(filePath, 'utf8');

  let targetGuard = 'require_login();';
  let category = 'login';
  if (adminOnlyFiles.has(filename)) {
    targetGuard = 'require_admin_or_owner();';
    category = 'admin';
  } else if (staffOrAdminFiles.has(filename)) {
    targetGuard = 'require_staff_or_admin();';
    category = 'staff';
  }

  // Remove existing auth_guard includes / require_role calls to normalize
  let cleanContent = content;
  cleanContent = cleanContent.replace(/include_once\s+['"]auth_guard\.php['"];\s*/g, '');
  cleanContent = cleanContent.replace(/require_role\([^)]*\);\s*/g, '');
  cleanContent = cleanContent.replace(/require_login\(\);\s*/g, '');
  cleanContent = cleanContent.replace(/require_admin_or_owner\(\);\s*/g, '');
  cleanContent = cleanContent.replace(/require_staff_or_admin\(\);\s*/g, '');

  const guardSnippet = `include_once "auth_guard.php";\n${targetGuard}\n`;

  let newContent = cleanContent;
  if (cleanContent.startsWith('<?php')) {
    newContent = cleanContent.replace('<?php', '<?php\n' + guardSnippet);
  } else {
    newContent = '<?php\n' + guardSnippet + '?>\n' + cleanContent;
  }

  fs.writeFileSync(filePath, newContent, 'utf8');
  modifiedFiles.push({ file: filename, guard: targetGuard, category: category });

  if (category === 'admin') adminGuarded.push(filename);
  else if (category === 'staff') staffGuarded.push(filename);
  else loginGuarded.push(filename);
});

console.log('\n--- BATCH SECURITY SUMMARY ---');
console.log(`Total PHP files processed: ${modifiedFiles.length}`);
console.log(`Admin/Owner only (${adminGuarded.length}):`, adminGuarded);
console.log(`Staff/Admin with write access (${staffGuarded.length}):`, staffGuarded);
console.log(`All logged-in read access (${loginGuarded.length}):`, loginGuarded.length);

fs.writeFileSync('c:/ddcar/_secure_backups/group1_modified_files.json', JSON.stringify({
  timestamp: new Date().toISOString(),
  admin_only: adminGuarded,
  staff_or_admin: staffGuarded,
  read_login_only: loginGuarded,
  total: modifiedFiles.length
}, null, 2), 'utf8');
