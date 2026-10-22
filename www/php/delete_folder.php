<?php  
    include "db.php";
include_once "auth_guard.php";
require_login();
    
    $title = isset($_POST['title']) ? trim(mysqli_real_escape_string($conn, $_POST['title'])) : '';

    if(empty($title)){
        echo "fail";
        exit;
    }

    // ⚡ ป้องกันค้างล็อคเกิน 2 วินาที
    mysqli_query($conn, "SET SESSION innodb_lock_wait_timeout = 2");

    // 1. ลบสินค้าในตาราง product ที่เป็นลูกของโฟลเดอร์นี้
    $sql_prod = "DELETE p FROM product p 
                 INNER JOIN tree t ON p.prod_id = t.title 
                 WHERE t.title = '$title' OR t.parent_title = '$title'";
    mysqli_query($conn, $sql_prod);

    // 2. ลบโฟลเดอร์แม่และโฟลเดอร์/สินค้าลูกออกจากตาราง tree ในคิวรีเดียว
    $sql_tree = "DELETE FROM tree WHERE title = '$title' OR parent_title = '$title'";
    $result = mysqli_query($conn, $sql_tree);

    if($result){
        echo "success";
    } else {
        echo "fail";
    }

    mysqli_close($conn);
?>