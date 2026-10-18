<?php
    include "db.php";
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $output = array();
    $txt = "(cancel)";

    if(isset($_POST['date'])){
        $date = $_POST['date'];
        $date_to = $_POST['date_to'];
        $query = "SELECT s.order_id,s.order_number,c.cus_name,s.date_time FROM sale_order s INNER JOIN customer c
        ON s.customer_id = c.cus_id WHERE date_time BETWEEN '$date' AND '$date_to' AND s.order_number NOT LIKE '%$txt%' LIMIT $start,30";
    }elseif(isset($_POST['value'])){
        $val = $_POST['value'];     
        $query = "SELECT DISTINCT s.order_id,s.order_number,c.cus_name,s.date_time FROM sale_order s INNER JOIN customer c
        ON s.customer_id = c.cus_id INNER JOIN sale_order_item i ON s.order_id = i.order_id INNER JOIN product p 
        ON i.prod_id = p.prod_id WHERE (s.order_number LIKE '%$val%' OR c.cus_name LIKE '%$val%' OR p.prod_name LIKE '%$val%') 
        AND s.order_number NOT LIKE '%$txt%' ORDER BY s.order_id DESC LIMIT $start,30";
    }else{
        $query = "SELECT s.order_id,s.order_number,c.cus_name,s.date_time FROM sale_order s INNER JOIN customer c
        ON s.customer_id = c.cus_id WHERE s.order_number NOT LIKE '%$txt%' ORDER BY s.order_id DESC LIMIT $start,30";
    }    
    $result = mysqli_query($conn, $query);

    if(mysqli_num_rows($result) > 0){
        if(mysqli_num_rows($result) < 30){           
            $output[] = 'last';
        }else{
            $output[] = 'not last';
        }
        while($row = mysqli_fetch_array($result)){      
            $output[] = $row;  
        }   
    }
    if($result){
        echo json_encode($output);		   
    }else{
        echo "fail";
    }
	mysqli_close($conn);
?>