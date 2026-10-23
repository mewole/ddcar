<?php
include_once "auth_guard.php";
require_role(['เจ้าของร้าน', 'แอดมิน']);
include "db.php";
    $title  = $_POST['title'];

    mysqli_begin_transaction($conn);
    $sql = "SELECT lft,rgt FROM tree WHERE title = '$title'";
    $result1 = mysqli_query($conn, $sql);  
        if(mysqli_num_rows($result1) > 0){    
            while($row = mysqli_fetch_array($result1)){ 
                $lft = $row['lft']; 
                $rgt = $row['rgt'];   
                $width = $rgt - $lft + 1;       
                $sql = "DELETE FROM tree WHERE lft BETWEEN '$lft' AND '$rgt'";
                $result = mysqli_query($conn, $sql);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }    
              
                $sql = "UPDATE tree SET rgt = rgt - $width WHERE rgt > '$rgt'";
                $result = mysqli_query($conn, $sql);  
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }  
                $sql = "UPDATE tree SET lft = lft - $width WHERE lft > '$rgt'";
                $result = mysqli_query($conn, $sql);
                if(!$result){
                    mysqli_rollback($conn);
                    echo "fail";
                    exit;
                }
            }
        }
        
    $sql = "SELECT p.prod_id,t.title FROM product p left join tree t on p.prod_id=t.title WHERE t.title is null";
    $result1 = mysqli_query($conn, $sql);  
    if(mysqli_num_rows($result1) > 0){    
        while($row = mysqli_fetch_array($result1)){
            $prod_id = $row['prod_id'];
            $sql = "DELETE FROM product WHERE prod_id = '$prod_id'";
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