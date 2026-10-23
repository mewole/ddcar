<?php
include_once "auth_guard.php";
require_role(['เจ้าของร้าน', 'แอดมิน']);
include "db.php";
    
    $parent = isset($_POST['parent']) ? trim(mysqli_real_escape_string($conn, $_POST['parent'])) : 'car'; 

    $msg = ""; 
    $output = array();  
   
    $sql = "SELECT 
                node.title,
                p.prod_name,
                p.prod_code,
                p.prod_id
            FROM tree AS node
            LEFT JOIN product AS p ON CAST(p.prod_id AS CHAR) = node.title 
            WHERE node.parent_title = '$parent'
            ORDER BY node.title ASC;"; 

    $result = mysqli_query($conn, $sql); 
  
    if($result && mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            
            // ⚡ เช็กว่าเป็นสินค้า
            if(is_numeric($row['title']) && !empty($row['prod_id'])){
                $msg = "last node";
                $output[] = [
                    'prod_name' => $row['prod_name'],
                    'prod_code' => $row['prod_code'],
                    'prod_id'   => $row['prod_id']
                ];
            } 
            // ⚡ เช็กว่าเป็นโฟลเดอร์
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