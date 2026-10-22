<?php
    date_default_timezone_set("Asia/Bangkok");
    $conn = mysqli_connect("localhost", "root", "Mewole901@", "ddcar");
    mysqli_set_charset($conn, "utf8");
    mysqli_query($conn, "SET time_zone = '+07:00'");
?>
