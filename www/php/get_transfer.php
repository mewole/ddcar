<?php
include_once "auth_guard.php";
require_login();
    include "db.php";
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $output = array();
    $txt = "(cancel)";

    if(isset($_POST['date'])){
        $date = mysqli_real_escape_string($conn, $_POST['date']);
        $date_to = mysqli_real_escape_string($conn, $_POST['date_to']);
        if (strlen($date) <= 10 && !empty($date)) {
            $date .= ' 00:00:00';
        }
        if (strlen($date_to) <= 10 && !empty($date_to)) {
            $date_to .= ' 23:59:59';
        }
        $query = "SELECT s.tran_id, s.tran_number, w.shop_name, s.date_time, s.supplier_name, s.ref_invoice 
                  FROM transfer_in s 
                  INNER JOIN shop_detail w ON s.shop_id = w.shop_id 
                  WHERE s.date_time BETWEEN '$date' AND '$date_to' 
                    AND s.tran_number NOT LIKE '%$txt%' 
                  ORDER BY s.tran_id DESC 
                  LIMIT $start, 30";
    }else if(isset($_POST['value'])){
        $val = mysqli_real_escape_string($conn, $_POST['value']);     
        $query = "SELECT DISTINCT s.tran_id, s.tran_number, w.shop_name, s.date_time, s.supplier_name, s.ref_invoice 
                  FROM transfer_in s 
                  INNER JOIN shop_detail w ON s.shop_id = w.shop_id 
                  INNER JOIN transfer_in_item i ON s.tran_id = i.tran_id 
                  INNER JOIN product p ON i.prod_id = p.prod_id 
                  WHERE (s.tran_number LIKE '%$val%' OR w.shop_name LIKE '%$val%' OR p.prod_name LIKE '%$val%' 
                         OR s.supplier_name LIKE '%$val%' OR s.ref_invoice LIKE '%$val%' 
                         OR i.source_from LIKE '%$val%' OR i.supplier_sku LIKE '%$val%') 
                    AND s.tran_number NOT LIKE '%$txt%' 
                  ORDER BY s.tran_id DESC 
                  LIMIT $start, 30";
    }else{
        $query = "SELECT s.tran_id, s.tran_number, w.shop_name, s.date_time, s.supplier_name, s.ref_invoice 
                  FROM transfer_in s 
                  INNER JOIN shop_detail w ON s.shop_id = w.shop_id 
                  WHERE s.tran_number NOT LIKE '%$txt%' 
                  ORDER BY s.tran_id DESC 
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
            $row['source_from'] = !empty($row['supplier_name']) ? $row['supplier_name'] : null;
            $rows[] = $row;
            if(empty($row['source_from'])){
                $ids[] = intval($row['tran_id']);
            }
        }

        // ดึง source_from สำหรับบิลเก่าที่ยังไม่มี supplier_name ในหัวบิล
        if(!empty($ids)){
            $idList = implode(',', $ids);
            $chkCol = mysqli_query($conn, "SHOW COLUMNS FROM transfer_in_item LIKE 'source_from'");
            if($chkCol && mysqli_num_rows($chkCol) > 0){
                $sqlSrc = "SELECT tran_id, GROUP_CONCAT(DISTINCT TRIM(source_from) SEPARATOR ', ') AS src 
                           FROM transfer_in_item 
                           WHERE tran_id IN ($idList) 
                             AND source_from IS NOT NULL 
                             AND TRIM(source_from) != '' 
                           GROUP BY tran_id";
                $resSrc = mysqli_query($conn, $sqlSrc);
                if($resSrc && mysqli_num_rows($resSrc) > 0){
                    $srcMap = array();
                    while($sr = mysqli_fetch_assoc($resSrc)){
                        $srcMap[$sr['tran_id']] = $sr['src'];
                    }
                    foreach($rows as &$rw){
                        if(empty($rw['source_from']) && isset($srcMap[$rw['tran_id']])){
                            $rw['source_from'] = $srcMap[$rw['tran_id']];
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