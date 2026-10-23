<?php
include_once "auth_guard.php";
require_staff_or_admin();

include "db.php";
    $title  = $_POST['title'];    
    $parent  = $_POST['parent'];
   
    $sql = "SELECT title FROM tree";
    $result = mysqli_query($conn, $sql);  

    mysqli_begin_transaction($conn);
    if(mysqli_num_rows($result) == 0){    
        $sql = "INSERT INTO tree(title,lft,rgt) VALUES ('car','1','2')";
        $result = mysqli_query($conn, $sql);
        if(!$result){
            mysqli_rollback($conn);
            echo "fail";
            exit;
        }            
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
    echo "success";    
    mysqli_close($conn);
?>