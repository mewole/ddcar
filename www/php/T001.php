<?php
include_once "auth_guard.php";
require_login();

// Scratch SQL queries from 2022 preserved for reference
/*
SELECT Sum(s.prod_price*s.prod_amount) AS "07 total" FROM sale_order_item s INNER JOIN sale_order so ON s.order_id = so.order_id INNER JOIN product p ON s.prod_id = p.prod_id WHERE p.prod_name LIKE "%กระจังหน้าตะข่าย%" AND so.date_time BETWEEN '2022-07-01' AND '2022-07-31';
SELECT Sum(p.prod_cost*s.prod_amount) FROM sale_order_item s INNER JOIN sale_order so ON s.order_id = so.order_id INNER JOIN product p ON s.prod_id = p.prod_id WHERE p.prod_name LIKE "%กระจังหน้าตะข่าย%" AND so.date_time BETWEEN '2022-04-01' AND '2022-04-30';
*/
echo json_encode(["status" => "deprecated"]);