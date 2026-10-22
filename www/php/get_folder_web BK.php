<?php
    include "db.php";
include_once "auth_guard.php";
require_login();
    
    $parent      = isset($_POST['parent']) ? trim(mysqli_real_escape_string($conn, $_POST['parent'])) : 'car';
    $order_input = isset($_POST['order_by']) ? $_POST['order_by'] : 'ASC';
    $order_by    = (strtoupper($order_input) === 'DESC') ? 'DESC' : 'ASC';
    
    $msg = ""; 
    $output = array();  
   
    $sql = "SELECT 
                node.title,
                p.prod_name,
                p.prod_code,
                p.img,
                p.prod_id
            FROM tree AS node
            LEFT JOIN product AS p ON CAST(p.prod_id AS CHAR) = node.title 
            WHERE node.parent_title = '$parent'
            ORDER BY node.title $order_by;";

    $result = mysqli_query($conn, $sql); 
  
    if($result && mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            
            // ⚡ เช็กชัวร์ๆ: ถ้า title เป็นตัวเลข และมี prod_id -> คือ "สินค้า"
            if(is_numeric($row['title']) && !empty($row['prod_id'])){
                $msg = "last node";
                $output[] = [
                    'prod_name' => $row['prod_name'],
                    'prod_code' => $row['prod_code'],
                    'img'       => isset($row['img']) ? $row['img'] : '',
                    'prod_id'   => $row['prod_id']
                ];
            } 
            // ⚡ นอกนั้น: คือ "โฟลเดอร์" คืนโครงสร้างกลับเป็นแบบดั้งเดิมที่ JS ต้องการ
            else {
                $output[] = [
                    'title' => trim($row['title'])
                ];
            }               
        }
        
        if($msg !== "") {
            array_push($output, $msg);
        }
        
        echo json_encode($output);   
    } else {
        echo "last node";
    } 
    
    mysqli_close($conn);
?>