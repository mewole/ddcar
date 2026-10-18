<?php
    include "db.php";
    header("Content-Type: application/json; charset=UTF-8");
    $output = array();    

    $query = "SELECT user_id,user_name,pass,phone,user_role FROM user ORDER BY user_id ASC";
    $result = mysqli_query($conn, $query);

    if($result && mysqli_num_rows($result) > 0){    
      while($row = mysqli_fetch_assoc($result)){      
        $output[] = $row;  
      }   
    }

    if($result){
        echo json_encode($output, JSON_UNESCAPED_UNICODE);		   
    }else{
        echo json_encode(array());
    }
	mysqli_close($conn);
?>