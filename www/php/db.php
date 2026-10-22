<?php
    date_default_timezone_set("Asia/Bangkok");
    $conn = @mysqli_connect("localhost", "ddcar_app", '60fad888cf98d0b92a45fb4ce77c209eApp$7', "ddcar");
    if (!$conn) {
        // Fallback to 127.0.0.1 if socket fails
        $conn = @mysqli_connect("127.0.0.1", "ddcar_app", '60fad888cf98d0b92a45fb4ce77c209eApp$7', "ddcar");
    }
    if (!$conn) {
        header("HTTP/1.1 500 Internal Server Error");
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["success" => false, "error" => "Database connection failure"]);
        exit;
    }
    mysqli_set_charset($conn, "utf8");
    mysqli_query($conn, "SET time_zone = '+07:00'");
?>
