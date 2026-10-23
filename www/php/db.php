<?php
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
    date_default_timezone_set("Asia/Bangkok");
    $conn = @mysqli_connect("localhost", "ddcar_app", '60fad888cf98d0b92a45fb4ce77c209eApp$7', "ddcar");
    if (!$conn) {
        $conn = @mysqli_connect("127.0.0.1", "ddcar_app", '60fad888cf98d0b92a45fb4ce77c209eApp$7', "ddcar");
    }
    if (!$conn) {
        $conn = @mysqli_connect("localhost", "root", "Mewole901@", "ddcar");
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
