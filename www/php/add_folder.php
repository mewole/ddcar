<?php
include_once "auth_guard.php";
require_staff_or_admin();

include "db.php";
// 1. กรองข้อมูลป้องกัน SQL Injection + Trim ตัดเว้นวรรคหน้า-หลังออก
    $title  = isset($_POST['title']) ? trim(mysqli_real_escape_string($conn, $_POST['title'])) : '';    
    $parent = isset($_POST['parent']) ? trim(mysqli_real_escape_string($conn, $_POST['parent'])) : 'car';

    if(empty($title)) {
        echo "fail";
        exit;
    }

    // เริ่ม Transaction
    mysqli_begin_transaction($conn);

    // 2. ตรวจสอบว่ามี Root Node หรือยัง
    $sql = "SELECT title FROM tree LIMIT 1";
    $result = mysqli_query($conn, $sql);  

    if(mysqli_num_rows($result) == 0){    
        // หากตารางยังว่าง (สร้าง Root Node ครั้งแรก) ให้ parent_title เป็น NULL
        $sql = "INSERT INTO tree(title, lft, rgt, parent_title) VALUES ('car', 1, 2, NULL)";
        $result = mysqli_query($conn, $sql);
        if(!$result){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        }            
    }

    // 3. ค้นหาโฟลเดอร์แม่เพื่อคำนวณตำแหน่ง lft, rgt
    $sql = "SELECT lft, rgt FROM tree WHERE title = '$parent' LIMIT 1";
    $result1 = mysqli_query($conn, $sql);  
    
    if($result1 && mysqli_num_rows($result1) > 0){    
        $row = mysqli_fetch_array($result1, MYSQLI_ASSOC);
        $rgt = intval($row['rgt']);
        $last_child = $rgt - 1;
        
        // ขยับค่า rgt ของ Node อื่นๆ
        $sql = "UPDATE tree SET rgt = rgt + 2 WHERE rgt > $last_child";
        $result = mysqli_query($conn, $sql);  
        if(!$result){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        }  

        // ขยับค่า lft ของ Node อื่นๆ
        $sql = "UPDATE tree SET lft = lft + 2 WHERE lft > $last_child";
        $result = mysqli_query($conn, $sql);  
        if(!$result){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        }  
        
        // คำนวณค่า rgt_new สำหรับตัวลูกใหม่
        $rgt_new = $rgt + 1;

        // 4. เพิ่มโฟลเดอร์/สินค้าใหม่ พร้อมบันทึก lft, rgt และ parent_title
        $sql = "INSERT INTO tree(title, lft, rgt, parent_title) VALUES ('$title', $rgt, $rgt_new, '$parent')";
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