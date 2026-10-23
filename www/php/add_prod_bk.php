<?php
include_once "auth_guard.php";
require_role(['เจ้าของร้าน', 'แอดมิน']);
include "db.php";   
    $name  = $_POST['name_prod'];    
    $code  = $_POST['code'];   
    $parent  = $_POST['parent'];
    $min = $_POST['min'];
    
    mysqli_begin_transaction($conn);
    if(isset($_POST['img'])){
      $img = $_POST['img'];
      $sql = "INSERT INTO product(prod_name,prod_code,img,min_amount) VALUE ('$name','$code','$img','$min')";
      $result = mysqli_query($conn, $sql);    
      $title = mysqli_insert_id($conn);
    }else{
      $sql = "INSERT INTO product(prod_name,prod_code,min_amount) VALUE ('$name','$code','$min')";
      $result = mysqli_query($conn, $sql);    
      $title = mysqli_insert_id($conn);
    }
    if(!$result){
      mysqli_rollback($conn);
      echo "fail";
      exit;
    } 	

    $sql = "SELECT lft,rgt FROM tree WHERE title = '$parent'";
    $result1 = mysqli_query($conn, $sql);  
    if(mysqli_num_rows($result1) > 0){    
      while($row = mysqli_fetch_array($result1)){            
        $rgt = $row['rgt'];
        $last_child =  $rgt-1;
        
        $sql = "UPDATE tree SET rgt = rgt + 2 WHERE rgt > '$last_child'";
        $result = mysqli_query($conn, $sql);  
        if(!$result){
          mysqli_rollback($conn);
          echo "fail";
          exit;
        } 
        $sql = "UPDATE tree SET lft = lft + 2 WHERE lft > '$last_child'";
        $result = mysqli_query($conn, $sql);  
        if(!$result){
          mysqli_rollback($conn);
          echo "fail";
          exit;
        } 
        $sql = "INSERT INTO tree(title,lft,rgt) VALUES ('$title','$rgt','$rgt'+1)";
        $result = mysqli_query($conn, $sql); 
        if(!$result){
          mysqli_rollback($conn);
          echo "fail";
          exit;
        }  
      }   
    }       
    mysqli_commit($conn); 
    echo $title;   
    mysqli_close($conn);   
?>