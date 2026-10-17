<?php
    include "db.php";   
    
    // 1. รับค่า + ตัดเว้นวรรค + กรองข้อมูลเพื่อความปลอดภัยจาก SQL Injection
    $name     = isset($_POST['name_prod']) ? trim(mysqli_real_escape_string($conn, $_POST['name_prod'])) : '';    
    $raw_code = isset($_POST['code']) ? trim($_POST['code']) : '';   
    // บังคับตัวพิมพ์ใหญ่ และตัดช่องว่างทั้งหมด
    $code     = strtoupper(str_replace(' ', '', $raw_code));
    $parent   = isset($_POST['parent']) ? trim(mysqli_real_escape_string($conn, $_POST['parent'])) : 'car';
    $min      = isset($_POST['min']) ? trim(mysqli_real_escape_string($conn, $_POST['min'])) : '0';
    
    if(empty($name) || empty($code)){
        echo "fail";
        exit;
    }

    // ตรวจสอบ SKU: ต้องมีเฉพาะภาษาอังกฤษตัวพิมพ์ใหญ่ ตัวเลข และเครื่องหมายขีด (-) เท่านั้น
    if(!preg_match('/^[A-Z0-9\-]+$/', $code)){
        echo "invalid_sku";
        exit;
    }
    $code = mysqli_real_escape_string($conn, $code);

    mysqli_begin_transaction($conn);

    // 2. บันทึกข้อมูลลงในตาราง product ก่อนเพื่อเอา prod_id ( auto_increment )
    if(isset($_POST['img']) && !empty($_POST['img'])){
      $img = trim(mysqli_real_escape_string($conn, $_POST['img']));
      $sql = "INSERT INTO product(prod_name, prod_code, img, min_amount) VALUES ('$name', '$code', '$img', '$min')";
    } else {
      $sql = "INSERT INTO product(prod_name, prod_code, min_amount) VALUES ('$name', '$code', '$min')";
    }

    $result = mysqli_query($conn, $sql);    
    if(!$result){
      mysqli_rollback($conn);
      echo "fail";
      exit;
    } 	
    
    // ไอดีสินค้าที่เพิ่งสร้างใหม่ (จะถูกนำไปเก็บเป็น title ในตาราง tree)
    $title = mysqli_insert_id($conn);

    // 3. ค้นหาตำแหน่งโฟลเดอร์แม่เพื่อคำนวณตำแหน่ง lft, rgt
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
      
      // คำนวณค่า rgt สำหรับ node สินค้าใหม่
      $rgt_new = $rgt + 1;

      // 4. บันทึกข้อมูลสินค้าลงในตาราง tree โดยแนบ parent_title ไปด้วย
      $sql = "INSERT INTO tree(title, lft, rgt, parent_title) VALUES ('$title', $rgt, $rgt_new, '$parent')";
      $result = mysqli_query($conn, $sql); 
      if(!$result){
        mysqli_rollback($conn);
        echo "fail";
        exit;
      }  
      
      mysqli_commit($conn); 
      echo $title;   
    } else {
      mysqli_rollback($conn);
      echo "fail";
    }

    mysqli_close($conn);   
?>