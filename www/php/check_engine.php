<?php
include "db.php";
$r = mysqli_query($conn, "SHOW TABLE STATUS LIKE 'product'");
$d = mysqli_fetch_assoc($r);
echo json_encode(['Engine' => $d['Engine'], 'Rows' => $d['Rows']]);
?>
