<?php
    include "db.php";
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $output = array();
    $txt = "(cancel)";

    if(isset($_POST['date'])){
        $date = mysqli_real_escape_string($conn, trim($_POST['date']));
        $date_to = mysqli_real_escape_string($conn, trim($_POST['date_to']));
        if (strlen($date) <= 10 && !empty($date)) {
            $date .= ' 00:00:00';
        }
        if (strlen($date_to) <= 10 && !empty($date_to)) {
            $date_to .= ' 23:59:59';
        }
        $query = "SELECT s.stock_out_id, s.stock_out_number, w.ware_name, s.date_time 
                  FROM stock_out s 
                  INNER JOIN warehouse_detail w ON s.ware_id = w.ware_id 
                  WHERE s.date_time BETWEEN '$date' AND '$date_to' 
                    AND s.stock_out_number NOT LIKE '%$txt%' 
                  ORDER BY s.stock_out_id DESC 
                  LIMIT $start, 30";
    }else if(isset($_POST['value'])){
        $val = $_POST['value'];     
        $query = "SELECT DISTINCT s.stock_out_id,s.stock_out_number,w.ware_name,s.date_time FROM stock_out s INNER JOIN warehouse_detail w
        ON s.ware_id = w.ware_id INNER JOIN stock_out_item i ON s.stock_out_id = i.stock_out_id INNER JOIN product p 
        ON i.prod_id = p.prod_id WHERE (s.stock_out_number LIKE '%$val%' OR w.ware_name LIKE '%$val%' OR p.prod_name LIKE '%$val%') 
        AND s.stock_out_number NOT LIKE '%$txt%' ORDER BY s.stock_out_id DESC LIMIT $start,30";
    }else{
        $query = "SELECT s.stock_out_id,s.stock_out_number,w.ware_name,s.date_time FROM stock_out s INNER JOIN warehouse_detail w
        ON s.ware_id = w.ware_id WHERE s.stock_out_number NOT LIKE '%$txt%' ORDER BY s.stock_out_id DESC LIMIT $start,30";
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