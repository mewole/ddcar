<?php
/**
 * folder_helper.php
 * ฟังก์ชันช่วยดึงโครงสร้างลำดับชั้นโฟลเดอร์/หมวดหมู่ของสินค้า (Folder Path Hierarchy)
 * สถาปัตยกรรม Batch Indexed Traversal: ใช้ UNIQUE KEY `title` วิ่งตรง 3 ระดับ เร็วระดับ 1 มิลลิวินาที
 */

if (!function_exists('get_products_folder_paths')) {
    /**
     * ดึงเส้นทางโฟลเดอร์ของสินค้าหลายรายการพร้อมกัน (Ultra-fast Batch Fetch)
     *
     * @param mysqli $conn การเชื่อมต่อฐานข้อมูล
     * @param array $prod_ids อาร์เรย์ของ prod_id
     * @return array ผลลัพธ์ในรูปแบบ [prod_id => ['path' => 'Toyota > Fortuner > กระจังหน้า', 'chain' => [...]]]
     */
    function get_products_folder_paths($conn, $prod_ids) {
        $result_map = array();
        if (empty($prod_ids) || !is_array($prod_ids)) {
            return $result_map;
        }

        $clean_ids = array();
        foreach ($prod_ids as $id) {
            $id_str = trim((string)$id);
            if ($id_str !== '') {
                $clean_ids[$id_str] = "'" . mysqli_real_escape_string($conn, $id_str) . "'";
                $result_map[$id_str] = array(
                    'path' => '',
                    'chain' => array()
                );
            }
        }

        if (empty($clean_ids)) {
            return $result_map;
        }

        // ขั้นตอนที่ 1: ดึงโฟลเดอร์แม่โดยตรงของสินค้าแต่ละตัวด้วยดัชนี title
        $id_str = implode(',', array_values($clean_ids));
        $res1 = mysqli_query($conn, "SELECT title, parent_title FROM tree WHERE title IN ($id_str)");
        $direct_parents = array();
        $needed_parents = array();

        if ($res1) {
            while ($r = mysqli_fetch_assoc($res1)) {
                $pid = (string)$r['title'];
                $pt = trim((string)$r['parent_title']);
                if ($pt !== '' && $pt !== 'car') {
                    $direct_parents[$pid] = $pt;
                    $needed_parents[$pt] = true;
                }
            }
        }

        // ขั้นตอนที่ 2: วิ่งขึ้นไปตามลำดับชั้นของโฟลเดอร์ (ระดับ 2, 3, 4) ทีละระดับด้วย Batch Query
        $parent_hierarchy = array();
        $current_level = array_keys($needed_parents);
        $depth = 0;

        while (!empty($current_level) && $depth < 5) {
            $depth++;
            $escaped = array_map(function($t) use ($conn) {
                return "'" . mysqli_real_escape_string($conn, $t) . "'";
            }, $current_level);
            $in_parents = implode(',', $escaped);

            $res_p = mysqli_query($conn, "SELECT title, parent_title FROM tree WHERE title IN ($in_parents)");
            $next_level = array();

            if ($res_p) {
                while ($r = mysqli_fetch_assoc($res_p)) {
                    $t = trim((string)$r['title']);
                    $pt = trim((string)$r['parent_title']);
                    $parent_hierarchy[$t] = $pt;
                    if ($pt !== '' && $pt !== 'car' && !isset($parent_hierarchy[$pt]) && !isset($next_level[$pt])) {
                        $next_level[$pt] = true;
                    }
                }
            }
            $current_level = array_keys($next_level);
        }

        // ขั้นตอนที่ 3: ประกอบเส้นทางโฟลเดอร์ให้แต่ละสินค้า
        foreach ($result_map as $pid => &$val) {
            if (isset($direct_parents[$pid])) {
                $chain = array();
                $curr = $direct_parents[$pid];
                $visited = array();
                while ($curr !== '' && $curr !== 'car' && !isset($visited[$curr])) {
                    $visited[$curr] = true;
                    array_unshift($chain, $curr);
                    $curr = isset($parent_hierarchy[$curr]) ? $parent_hierarchy[$curr] : '';
                }
                $val['chain'] = $chain;
                $val['path'] = implode(' > ', $chain);
            }
        }

        return $result_map;
    }
}

if (!function_exists('get_product_folder_path')) {
    /**
     * ดึงเส้นทางโฟลเดอร์ของสินค้า 1 ชิ้น
     */
    function get_product_folder_path($conn, $prod_id) {
        $map = get_products_folder_paths($conn, array($prod_id));
        $pid_str = (string)$prod_id;
        return isset($map[$pid_str]) ? $map[$pid_str] : array('path' => '', 'chain' => array());
    }
}
?>
