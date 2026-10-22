<?php
include_once "auth_guard.php";
require_role(['เจ้าของร้าน', 'แอดมิน']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('HTTP/1.1 405 Method Not Allowed'); exit; }  
    include "db.php"; 
    
    // 1. รับค่า + trim ตัดเว้นวรรค + กรองป้องกัน SQL Injection
    $title = isset($_POST['title']) ? trim(mysqli_real_escape_string($conn, $_POST['title'])) : '';

    if(empty($title)){
        echo "fail";
        exit;
    }

    mysqli_begin_transaction($conn);
    
    // 2. ค้นหาตำแหน่ง lft, rgt ของสินค้าที่ต้องการลบ
    $sql = "SELECT lft, rgt FROM tree WHERE title = '$title' LIMIT 1";
    $result1 = mysqli_query($conn, $sql);  

    if($result1 && mysqli_num_rows($result1) > 0){    
        $row = mysqli_fetch_array($result1, MYSQLI_ASSOC);
        $lft   = intval($row['lft']); 
        $rgt   = intval($row['rgt']);   
        $width = $rgt - $lft + 1;       
        
        // 2.1 ลบสินค้าออกจากตาราง tree (parent_title และข้อมูลแถวนี้จะถูกลบออกไป)
        $sql = "DELETE FROM tree WHERE lft BETWEEN $lft AND $rgt";
        $result = mysqli_query($conn, $sql);  
        if(!$result){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        }  
      
        // 2.2 ปรับขยับค่า rgt ของ Node อื่นๆ ในระบบ Nested Set
        $sql = "UPDATE tree SET rgt = rgt - $width WHERE rgt > $rgt";
        $result = mysqli_query($conn, $sql);  
        if(!$result){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        }  
        
        // 2.3 ปรับขยับค่า lft ของ Node อื่นๆ ในระบบ Nested Set
        $sql = "UPDATE tree SET lft = lft - $width WHERE lft > $rgt";
        $result = mysqli_query($conn, $sql);  
        if(!$result){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        }               

        // 3. ลบรายละเอียดสินค้าชิ้นนี้ออกจากตารางหลัก product
        $sql = "DELETE FROM product WHERE prod_id = '$title'";
        $result = mysqli_query($conn, $sql); 
        if(!$result){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        }     

        mysqli_commit($conn); 
        echo "success";
    } else {
        mysqli_rollback($conn);
        echo "fail";
    }

    mysqli_close($conn);
?>