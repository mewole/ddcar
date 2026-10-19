<?php
    include "db.php";
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $output = array();
    $txt = "(cancel)";

    if(isset($_POST['date'])){
        $date = mysqli_real_escape_string($conn, $_POST['date']);
        $date_to = mysqli_real_escape_string($conn, $_POST['date_to']);
        $query = "SELECT s.order_id,s.order_number,c.cus_name,s.date_time FROM sale_order s INNER JOIN customer c
        ON s.customer_id = c.cus_id WHERE date_time BETWEEN '$date' AND '$date_to' AND s.order_number NOT LIKE '%$txt%' LIMIT $start,30";
    }else{
        $bill_prod = isset($_POST['bill_prod']) ? trim(mysqli_real_escape_string($conn, $_POST['bill_prod'])) : '';
        $cus_name = isset($_POST['cus_name']) ? trim(mysqli_real_escape_string($conn, $_POST['cus_name'])) : '';
        $legacy_val = isset($_POST['value']) ? trim(mysqli_real_escape_string($conn, $_POST['value'])) : '';

        if($bill_prod !== '' && $cus_name !== ''){
            // Case 1: Both Bill Number / Product Name AND Customer Name
            $query = "SELECT DISTINCT s.order_id, s.order_number, c.cus_name, s.date_time 
                      FROM sale_order s 
                      INNER JOIN customer c ON s.customer_id = c.cus_id 
                      INNER JOIN sale_order_item i ON s.order_id = i.order_id 
                      INNER JOIN product p ON i.prod_id = p.prod_id 
                      WHERE (s.order_number LIKE '%$bill_prod%' OR p.prod_name LIKE '%$bill_prod%' OR p.prod_code LIKE '%$bill_prod%') 
                        AND c.cus_name LIKE '%$cus_name%' 
                        AND s.order_number NOT LIKE '%$txt%' 
                      ORDER BY s.order_id DESC LIMIT $start,30";
        }elseif($bill_prod !== ''){
            // Case 2: Only Bill Number OR Product Name (or barcode SKU)
            $query = "SELECT DISTINCT s.order_id, s.order_number, c.cus_name, s.date_time 
                      FROM sale_order s 
                      INNER JOIN customer c ON s.customer_id = c.cus_id 
                      INNER JOIN sale_order_item i ON s.order_id = i.order_id 
                      INNER JOIN product p ON i.prod_id = p.prod_id 
                      WHERE (s.order_number LIKE '%$bill_prod%' OR p.prod_name LIKE '%$bill_prod%' OR p.prod_code LIKE '%$bill_prod%') 
                        AND s.order_number NOT LIKE '%$txt%' 
                      ORDER BY s.order_id DESC LIMIT $start,30";
        }elseif($cus_name !== ''){
            // Case 3: Only Customer Name
            $query = "SELECT s.order_id, s.order_number, c.cus_name, s.date_time 
                      FROM sale_order s 
                      INNER JOIN customer c ON s.customer_id = c.cus_id 
                      WHERE c.cus_name LIKE '%$cus_name%' 
                        AND s.order_number NOT LIKE '%$txt%' 
                      ORDER BY s.order_id DESC LIMIT $start,30";
        }elseif($legacy_val !== ''){
            // Case 4: Legacy single search value (for backward compatibility)
            $query = "SELECT DISTINCT s.order_id, s.order_number, c.cus_name, s.date_time 
                      FROM sale_order s 
                      INNER JOIN customer c ON s.customer_id = c.cus_id 
                      INNER JOIN sale_order_item i ON s.order_id = i.order_id 
                      INNER JOIN product p ON i.prod_id = p.prod_id 
                      WHERE (s.order_number LIKE '%$legacy_val%' OR c.cus_name LIKE '%$legacy_val%' OR p.prod_name LIKE '%$legacy_val%' OR p.prod_code LIKE '%$legacy_val%') 
                        AND s.order_number NOT LIKE '%$txt%' 
                      ORDER BY s.order_id DESC LIMIT $start,30";
        }else{
            // Case 5: Default pagination (no filters)
            $query = "SELECT s.order_id, s.order_number, c.cus_name, s.date_time 
                      FROM sale_order s 
                      INNER JOIN customer c ON s.customer_id = c.cus_id 
                      WHERE s.order_number NOT LIKE '%$txt%' 
                      ORDER BY s.order_id DESC LIMIT $start,30";
        }
    }    
    $result = mysqli_query($conn, $query);

    if($result && mysqli_num_rows($result) > 0){
        if(mysqli_num_rows($result) < 30){           
            $output[] = 'last';
        }else{
            $output[] = 'not last';
        }
        while($row = mysqli_fetch_array($result)){      
            $output[] = $row;  
        }   
    }else{
        $output[] = 'last';
    }

    if($result){
        echo json_encode($output);		   
    }else{
        echo "fail";
    }
	mysqli_close($conn);
?>