<?php
include_once "auth_guard.php";
require_login();
$conn = mysqli_connect("localhost", "id3340019_dd", "pkl2468GG", "id3340019_dd");  
    $title  = $_POST['title'];
  
    $sql = "SELECT parent.title FROM tree AS node,tree AS parent WHERE node.lft BETWEEN parent.lft 
    AND parent.rgt AND node.title = '$title' AND parent.title != '$title' ORDER BY parent.rgt LIMIT 1";
    $result = mysqli_query($conn, $sql); 

    if(mysqli_num_rows($result) > 0){    
        while($row = mysqli_fetch_array($result)){ 
            echo $row['title'];     
        }          
    }  
        
    if(!$result){       
        echo "fail";
    }  
    mysqli_close($conn);
?>