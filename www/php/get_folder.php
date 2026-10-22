<?php
    ini_set('memory_limit', '512M');
    include "db.php";
include_once "auth_guard.php";
require_login();
    
    // 1. รับค่าและตัดเว้นวรรค
    $parent = isset($_POST['parent']) ? trim(mysqli_real_escape_string($conn, $_POST['parent'])) : 'car'; 

    $msg = ""; 
    $output = array();  
   
    // 2. Query ดึงข้อมูลพร้อมตรวจว่ามีลูกใต้ Node หรือไม่
    $sql = "SELECT 
                node.title,
                p.prod_name,
                p.prod_code,
                p.prod_id,
                EXISTS(SELECT 1 FROM tree WHERE parent_title = node.title LIMIT 1) AS has_children
            FROM tree AS node
            LEFT JOIN product AS p ON p.prod_id = node.title
            WHERE node.parent_title = '$parent'
            ORDER BY node.title ASC;"; 

    $result = mysqli_query($conn, $sql); 
  
    if($result && mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            
            // ⚡ เงื่อนไขที่ 1: ถ้ามีรายการลูกอยู่ข้างใน (has_children > 0) -> มันคือ "โฟลเดอร์ย่อย"
            if((int)$row['has_children'] > 0){
                $output[] = [
                    'title' => $row['title']
                ];
            } 
            // ⚡ เงื่อนไขที่ 2: ถ้าไม่มีลูก และมี prod_id -> มันคือ "สินค้า"
            else if(!empty($row['prod_id'])){
                $msg = "last node";
                $output[] = [
                    'title'     => $row['title'],
                    'prod_name' => $row['prod_name'],
                    'prod_code' => $row['prod_code'],
                    'prod_id'   => $row['prod_id']
                ];
            } 
            // ⚡ เงื่อนไขที่ 3: โฟลเดอร์ว่างเปล่า (ไม่มีลูกและไม่ใช่สินค้า)
            else {
                $output[] = [
                    'title' => $row['title']
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