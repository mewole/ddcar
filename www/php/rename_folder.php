<?php
include_once "auth_guard.php";
require_staff_or_admin();

include "db.php";
// 1. ดักความปลอดภัยป้องกัน SQL Injection + trim ตัดเว้นวรรคส่วนเกิน
    $name      = isset($_POST['name']) ? trim(mysqli_real_escape_string($conn, $_POST['name'])) : '';   
    $old_name  = isset($_POST['old_name']) ? trim(mysqli_real_escape_string($conn, $_POST['old_name'])) : '';

    if(empty($name) || empty($old_name)){
        echo "fail";
        exit;
    }

    // เริ่มต้น Transaction เพื่อให้มั่นใจว่าต้องอัปเดตสำเร็จทั้งสองคิวรี
    mysqli_begin_transaction($conn);

    // คิวรีที่ 1: เปลี่ยนชื่อตัวมันเองในตาราง tree
    $sql1 = "UPDATE tree SET title = '$name' WHERE title = '$old_name'";
    $result1 = mysqli_query($conn, $sql1);
            
    if(!$result1){
        mysqli_rollback($conn);
        echo "fail";      
        exit;
    }

    // คิวรีที่ 2: อัปเดต parent_title ของรายการลูกๆ ทั้งหมดให้เปลี่ยนตามชื่อใหม่
    $sql2 = "UPDATE tree SET parent_title = '$name' WHERE parent_title = '$old_name'";
    $result2 = mysqli_query($conn, $sql2);

    if(!$result2){
        mysqli_rollback($conn);
        echo "fail";      
        exit;
    }

    // ทำงานสำเร็จทั้งหมดจึงทำการ Commit ข้อมูล
    mysqli_commit($conn);
    echo "success";      
    mysqli_close($conn);
?>