<?php
include_once "auth_guard.php";
require_login();
/**
 * DDCar - Get TikTok SKU Assistant Items with Safe Tri-Match Candidates & Strict Guard
 */
require_once "db.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate");

$status = isset($_GET['status']) ? trim($_GET['status']) : 'unmapped';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$limit = isset($_GET['limit']) ? max(1, min(200, intval($_GET['limit']))) : 25;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$where = ["m.channel_code = 'tiktok_main'"];
if ($status === 'unmapped') {
    $where[] = "m.status = 'unmapped'";
} elseif ($status === 'mapped') {
    $where[] = "m.status = 'mapped'";
}

if (!empty($search)) {
    $where[] = "(m.platform_name LIKE '%$search%' OR m.platform_sku LIKE '%$search%' OR m.target_sku LIKE '%$search%')";
}

$where_sql = implode(" AND ", $where);

// Total counts for KPI
$kpi_res = mysqli_query($conn, "SELECT 
    COUNT(*) as total,
    SUM(status = 'unmapped') as unmapped_cnt,
    SUM(status = 'mapped') as mapped_cnt
    FROM platform_sku_mapping 
    WHERE channel_code = 'tiktok_main'");
$kpi = mysqli_fetch_assoc($kpi_res);

// Filtered count
$count_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM platform_sku_mapping m WHERE $where_sql");
$total_filtered = $count_res ? intval(mysqli_fetch_assoc($count_res)['total']) : 0;

// Pre-load known cross-channel mapped SKUs (exact 100% verified cross match)
$known_cross_map = [];
$kq = mysqli_query($conn, "SELECT platform_name, target_sku FROM platform_sku_mapping WHERE channel_code != 'tiktok_main' AND status = 'mapped' AND target_sku != ''");
if ($kq) {
    while ($kr = mysqli_fetch_assoc($kq)) {
        $pname_clean = trim($kr['platform_name']);
        if (!empty($pname_clean)) {
            $known_cross_map[$pname_clean] = $kr['target_sku'];
        }
    }
}

// Category and Model Dictionaries for Strict Tri-Match
$categories_list = [
    'ครอบไฟท้าย', 'ครอบไฟหน้า', 'ครอบฝาถัง', 'ครอบมือเปิด', 'ครอบกระจก', 'เบ้ามือเปิด',
    'กระจังหน้า', 'ไฟหน้า', 'ไฟท้าย', 'ไฟมุม', 'ไฟเลี้ยว', 'ไฟตัดหมอก', 'ไฟทับทิม',
    'กันสาด', 'คิ้วล้อ', 'สปอยเลอร์', 'ลิ้นหน้า', 'สเกิร์ต', 'ตัว u', 'พวงมาลัย',
    'ใบปัด', 'หลอด', 'ขั้วหลอด', 'ยางกันกระแทก', 'โลโก้', 'โช๊คค้ำ', 'space arm', 'balance arm', 'กันโคลง'
];

$models_list = [
    'revo', 'rocco', 'vigo', 'triton', 'dmax', 'd-max', 'bt50', 'bt-50', 'ranger', 'vios', 'yaris',
    'altis', 'fortuner', 'commuter', 'hiace', 'tiger', 'd4d', 'city', 'civic', 'jazz', 'navara',
    'mu-x', 'everest', 'pajero', 'alphard', 'vellfire', 'sienta', 'corolla cross', 'yaris cross',
    'crv', 'hrv', 'accord', 'camry', 'mirage', 'attrage', 'swift', 'lancer', 'e-car', 'ecar', 'ae100', 'ae101'
];

function tm_extract_category($text, $categories) {
    $t = mb_strtolower($text, 'UTF-8');
    foreach ($categories as $cat) {
        if (mb_strpos($t, $cat) !== false) {
            return $cat;
        }
    }
    return '';
}

function tm_extract_models($text, $models) {
    $t = mb_strtolower($text, 'UTF-8');
    $t = str_replace(['d-max', 'bt-50', 'e-car'], ['dmax', 'bt50', 'ecar'], $t);
    $found = [];
    foreach ($models as $m) {
        $m_clean = str_replace(['d-max', 'bt-50', 'e-car'], ['dmax', 'bt50', 'ecar'], $m);
        if (mb_strpos($t, $m_clean) !== false) {
            $found[] = $m_clean;
        }
    }
    return array_unique($found);
}

function tm_extract_years($text) {
    if (preg_match('/(19\d{2}|20\d{2})\s*[-–]\s*(19\d{2}|20\d{2}|\d{2})/', $text, $m)) {
        $y1 = intval($m[1]);
        $y2 = intval($m[2]);
        if ($y2 < 100) $y2 = intval(substr($m[1], 0, 2) . str_pad($y2, 2, '0', STR_PAD_LEFT));
        return [$y1, $y2];
    } elseif (preg_match('/\b(19\d{2}|20\d{2})\b/', $text, $m)) {
        $y = intval($m[1]);
        return [$y, $y];
    }
    return null;
}

// Pre-load products into memory for fast strict tri-matching
$products_cache = [];
$pq = mysqli_query($conn, "SELECT prod_id, prod_code, prod_name, prod_price, 
       CASE WHEN img LIKE 'img/%' THEN img WHEN LENGTH(img) < 200 THEN img ELSE '' END as prod_img 
       FROM product WHERE prod_code != ''");
if ($pq) {
    while ($p = mysqli_fetch_assoc($pq)) {
        $p['cat'] = tm_extract_category($p['prod_name'], $categories_list);
        $p['models'] = tm_extract_models($p['prod_name'], $models_list);
        $p['years'] = tm_extract_years($p['prod_name']);
        $p['name_lower'] = mb_strtolower($p['prod_name'], 'UTF-8');
        $products_cache[] = $p;
    }
}

// Query current page items
$sql = "SELECT m.id, m.platform_sku, m.platform_name, m.target_sku, m.status, m.mapping_source, m.image_url, m.product_url,
               p.prod_id as current_prod_id, p.prod_name as current_prod_name,
               CASE WHEN p.img LIKE 'img/%' THEN p.img WHEN LENGTH(p.img) < 200 THEN p.img ELSE '' END as current_img
        FROM platform_sku_mapping m
        LEFT JOIN product p ON (m.target_sku != '' AND m.target_sku = p.prod_code)
        WHERE $where_sql
        ORDER BY (m.status = 'unmapped') DESC, m.id DESC
        LIMIT $offset, $limit";

$res = mysqli_query($conn, $sql);
$items = [];

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $name = $row['platform_name'] ?? '';
        $sku = $row['platform_sku'] ?? '';
        $prod_url = $row['product_url'] ?? '';
        $img = $row['image_url'] ?? '';

        // Extract TikTok Product ID
        $tiktok_id = '';
        if (preg_match('/ID\s*([0-9]{15,22})/i', $sku, $m)) {
            $tiktok_id = $m[1];
        } elseif (preg_match('/\b([0-9]{15,22})\b/', $sku, $m)) {
            $tiktok_id = $m[1];
        } elseif (preg_match('/edit\/([0-9]{15,22})/i', $prod_url, $m)) {
            $tiktok_id = $m[1];
        }

        // Direct edit URL on TikTok Seller Center
        $direct_edit_url = $tiktok_id 
            ? "https://seller-th.tiktok.com/product/edit/$tiktok_id"
            : ($prod_url ?: "https://seller-th.tiktok.com/product/manage");

        // Parse variation text
        $var_text = '';
        $base_name = $name;
        if (preg_match('/^(.*?)\[(.*?)\]$/', $name, $m)) {
            $base_name = trim($m[1]);
            $var_text = trim($m[2]);
        }

        // Safe Tri-Match Engine
        $suggestion = null;
        $candidates = [];

        if ($row['status'] === 'unmapped' && empty($row['target_sku'])) {
            // 1. Exact Cross-Channel Match from Shopee/Lazada (100% verified)
            $cross_sku = $known_cross_map[$name] ?? $known_cross_map[$base_name] ?? '';
            if (!empty($cross_sku)) {
                $suggestion = [
                    'prod_id' => 0,
                    'prod_code' => $cross_sku,
                    'prod_name' => 'ตรงกับสินค้าบน Shopee/Lazada (' . $cross_sku . ')',
                    'prod_price' => 0,
                    'prod_img' => '',
                    'stock' => 0,
                    'confidence' => 'cross_channel_verified'
                ];
            } elseif (!empty($products_cache)) {
                // 2. Strict Tri-Match Candidate Search
                $t_cat = tm_extract_category($name, $categories_list);
                $t_models = tm_extract_models($name, $models_list);
                $t_years = tm_extract_years($name);
                $t_lower = mb_strtolower($name, 'UTF-8');

                $scored_list = [];

                foreach ($products_cache as $p) {
                    // Strict Category Match: If TikTok title has category, product must match!
                    if (!empty($t_cat) && $p['cat'] !== $t_cat) {
                        continue;
                    }

                    // Strict Model Match: If TikTok title has model, product must match!
                    if (!empty($t_models)) {
                        $model_intersect = array_intersect($t_models, $p['models']);
                        if (empty($model_intersect)) {
                            continue;
                        }
                    }

                    // Strict Year Range Match: If both specify years, they must overlap!
                    if ($t_years && $p['years']) {
                        if ($t_years[1] < $p['years'][0] || $t_years[0] > $p['years'][1]) {
                            continue; // Disjoint years rejected
                        }
                    }

                    $score = 50;
                    $reasons = [];
                    if ($t_cat) $reasons[] = "หมวด: $t_cat";
                    if (!empty($t_models)) $reasons[] = "รุ่น: " . implode(',', $t_models);
                    if ($t_years && $p['years']) {
                        $score += 20;
                        $reasons[] = "ปี: " . $p['years'][0] . "-" . $p['years'][1];
                    }

                    // Keyword overlap
                    $words = preg_split('/[\s,\-\/()]+/u', $t_lower);
                    foreach ($words as $w) {
                        if (mb_strlen($w, 'UTF-8') >= 3 && strpos($p['name_lower'], $w) !== false) {
                            $score += 5;
                        }
                    }

                    if ($score >= 60) {
                        $scored_list[] = [
                            'prod_id' => intval($p['prod_id']),
                            'prod_code' => $p['prod_code'],
                            'prod_name' => $p['prod_name'],
                            'prod_price' => floatval($p['prod_price']),
                            'prod_img' => $p['prod_img'],
                            'score' => min(95, $score),
                            'reasons' => implode(' | ', $reasons)
                        ];
                    }
                }

                if (!empty($scored_list)) {
                    usort($scored_list, function($a, $b) {
                        return $b['score'] - $a['score'];
                    });
                    $candidates = array_slice($scored_list, 0, 3);
                }
            }
        }

        $items[] = [
            'id' => intval($row['id']),
            'tiktok_id' => $tiktok_id,
            'platform_sku' => $sku,
            'platform_name' => $name,
            'base_name' => $base_name,
            'var_text' => $var_text,
            'target_sku' => $row['target_sku'] ?: '',
            'status' => $row['status'],
            'mapping_source' => $row['mapping_source'] ?: 'manual',
            'image_url' => $img,
            'direct_edit_url' => $direct_edit_url,
            'current_prod_id' => intval($row['current_prod_id']),
            'current_prod_name' => $row['current_prod_name'] ?: '',
            'current_img' => $row['current_img'] ?: '',
            'current_stock' => 0,
            'suggestion' => $suggestion,
            'candidates' => $candidates
        ];
    }
}

// Fetch stock for current items
$pids_to_fetch = [];
foreach ($items as $item) {
    if (!empty($item['current_prod_id'])) {
        $pids_to_fetch[] = $item['current_prod_id'];
    }
    if (!empty($item['candidates'])) {
        foreach ($item['candidates'] as $c) {
            $pids_to_fetch[] = $c['prod_id'];
        }
    }
}

$stock_map = [];
if (!empty($pids_to_fetch)) {
    $pids_to_fetch = array_unique($pids_to_fetch);
    $pid_str = implode(',', array_map('intval', $pids_to_fetch));
    $sq = mysqli_query($conn, "SELECT prod_id, SUM(amount) as total FROM warehouse WHERE prod_id IN ($pid_str) GROUP BY prod_id");
    if ($sq) {
        while ($sr = mysqli_fetch_assoc($sq)) {
            $stock_map[$sr['prod_id']] = intval($sr['total']);
        }
    }
    $sq2 = mysqli_query($conn, "SELECT prod_id, SUM(amount) as total FROM shop WHERE prod_id IN ($pid_str) GROUP BY prod_id");
    if ($sq2) {
        while ($sr2 = mysqli_fetch_assoc($sq2)) {
            $stock_map[$sr2['prod_id']] = ($stock_map[$sr2['prod_id']] ?? 0) + intval($sr2['total']);
        }
    }
}

foreach ($items as &$item) {
    if (!empty($item['current_prod_id'])) {
        $item['current_stock'] = $stock_map[$item['current_prod_id']] ?? 0;
    }
    if (!empty($item['candidates'])) {
        foreach ($item['candidates'] as &$c) {
            $c['stock'] = $stock_map[$c['prod_id']] ?? 0;
        }
    }
}

$mapped_cnt = intval($kpi['mapped_cnt'] ?? 0);
$total_cnt = intval($kpi['total'] ?? 0);
$percent = ($total_cnt > 0) ? round(($mapped_cnt / $total_cnt) * 100) : 0;

echo json_encode([
    'success' => true,
    'kpi' => [
        'total' => $total_cnt,
        'unmapped' => intval($kpi['unmapped_cnt'] ?? 0),
        'mapped' => $mapped_cnt,
        'percent' => $percent
    ],
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total_filtered' => $total_filtered,
        'total_pages' => ceil($total_filtered / $limit)
    ],
    'total_filtered' => $total_filtered,
    'page' => $page,
    'limit' => $limit,
    'items' => $items
], JSON_UNESCAPED_UNICODE);
?>
