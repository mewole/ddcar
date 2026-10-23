<?php
include_once "auth_guard.php";
require_login();
include "db.php";
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $output = array();
    $txt = "(cancel)";

    if(isset($_POST['date'])){
        $date = mysqli_real_escape_string($conn, trim($_POST['date']));
        $date_to = mysqli_real_escape_string($conn, trim($_POST['date_to']));
        if (strlen($date) <= 10 && !empty($date)) {
            $date .= ' 00:00:00';
        }
        if (strlen($date_to) <= 10 && !empty($date_to)) {
            $date_to .= ' 23:59:59';
        }
        $query = "SELECT s.stock_id, s.stock_number, w.ware_name, s.date_time, s.supplier_name, s.ref_invoice 
                  FROM stock_in s 
                  INNER JOIN warehouse_detail w ON s.ware_id = w.ware_id 
                  WHERE s.date_time BETWEEN '$date' AND '$date_to' 
                    AND s.stock_number NOT LIKE '%$txt%' 
                  ORDER BY s.stock_id DESC 
                  LIMIT $start, 30";
    }else if(isset($_POST['value'])){
        $val = mysqli_real_escape_string($conn, $_POST['value']);     
        $query = "SELECT DISTINCT s.stock_id, s.stock_number, w.ware_name, s.date_time, s.supplier_name, s.ref_invoice 
                  FROM stock_in s 
                  INNER JOIN warehouse_detail w ON s.ware_id = w.ware_id 
                  INNER JOIN stock_in_item i ON s.stock_id = i.stock_id 
                  INNER JOIN product p ON i.prod_id = p.prod_id 
                  WHERE (s.stock_number LIKE '%$val%' OR w.ware_name LIKE '%$val%' OR p.prod_name LIKE '%$val%' 
                         OR s.supplier_name LIKE '%$val%' OR s.ref_invoice LIKE '%$val%' 
                         OR i.source_from LIKE '%$val%' OR i.supplier_sku LIKE '%$val%') 
                    AND s.stock_number NOT LIKE '%$txt%' 
                  ORDER BY s.stock_id DESC 
                  LIMIT $start, 30";
    }else{
        $query = "SELECT s.stock_id, s.stock_number, w.ware_name, s.date_time, s.supplier_name, s.ref_invoice 
                  FROM stock_in s 
                  INNER JOIN warehouse_detail w ON s.ware_id = w.ware_id 
                  WHERE s.stock_number NOT LIKE '%$txt%' 
                  ORDER BY s.stock_id DESC 
                  LIMIT $start, 30";
    }    
    $result = mysqli_query($conn, $query);

    if($result && mysqli_num_rows($result) > 0){
        if(mysqli_num_rows($result) < 30){           
            $output[] = 'last';
        }else{
            $output[] = 'not last';
        }
        $rows = array();
        $ids = array();
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){      
            // กำหนด source_from เริ่มต้นจาก supplier_name ของบิล
            $row['source_from'] = !empty($row['supplier_name']) ? $row['supplier_name'] : null;
            $rows[] = $row;
            if(empty($row['source_from'])){
                $ids[] = intval($row['stock_id']);
            }
        }

        // ดึง source_from สำหรับบิลเก่าที่ยังไม่มี supplier_name ในหัวบิล
        if(!empty($ids)){
            $idList = implode(',', $ids);
            $chkCol = mysqli_query($conn, "SHOW COLUMNS FROM stock_in_item LIKE 'source_from'");
            if($chkCol && mysqli_num_rows($chkCol) > 0){
                $sqlSrc = "SELECT stock_id, GROUP_CONCAT(DISTINCT TRIM(source_from) SEPARATOR ', ') AS src 
                           FROM stock_in_item 
                           WHERE stock_id IN ($idList) 
                             AND source_from IS NOT NULL 
                             AND TRIM(source_from) != '' 
                           GROUP BY stock_id";
                $resSrc = mysqli_query($conn, $sqlSrc);
                if($resSrc && mysqli_num_rows($resSrc) > 0){
                    $srcMap = array();
                    while($sr = mysqli_fetch_assoc($resSrc)){
                        $srcMap[$sr['stock_id']] = $sr['src'];
                    }
                    foreach($rows as &$rw){
                        if(empty($rw['source_from']) && isset($srcMap[$rw['stock_id']])){
                            $rw['source_from'] = $srcMap[$rw['stock_id']];
                        }
                    }
                }
            }
        }

        foreach($rows as $rw){
            $output[] = $rw;
        }
    }
    if($result){
        echo json_encode($output, JSON_UNESCAPED_UNICODE);		   
    }else{
        echo "fail";
    }
	mysqli_close($conn);
?>