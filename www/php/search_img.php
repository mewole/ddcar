<?php
include "db.php"; 
$prod_id = isset($_POST['prod_id']) ? intval($_POST['prod_id']) : 0;  
$output = array(); 

if ($prod_id > 0) {
    $query = "SELECT img FROM product WHERE prod_id = '$prod_id' LIMIT 1";     
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {      
        $row = mysqli_fetch_assoc($result);      
        $output[] = $row;  
    }
}

echo json_encode($output);
mysqli_close($conn);
?>