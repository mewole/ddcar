<?php
include_once "auth_guard.php";
require_admin_or_owner();

include "db.php";
header("Content-Type: application/json; charset=UTF-8");

    $version = isset($_REQUEST['version']) ? trim($_REQUEST['version']) : '';
    $url = isset($_REQUEST['url']) ? trim($_REQUEST['url']) : '';

    if (empty($version)) {
        echo json_encode(["status" => "error", "message" => "version is required"]);
        exit;
    }

    if (empty($url)) {
        $url = "http://ddaccessory.trueddns.com:24330/dd-shop/ddcar-latest.apk";
    }

    // Check if row exists in version table
    $checkSql = "SELECT COUNT(*) as cnt FROM version";
    $checkRes = mysqli_query($conn, $checkSql);
    $row = mysqli_fetch_assoc($checkRes);

    if ($row && $row['cnt'] > 0) {
        $sql = "UPDATE version SET version = '" . mysqli_real_escape_string($conn, $version) . "', url = '" . mysqli_real_escape_string($conn, $url) . "'";
    } else {
        $sql = "INSERT INTO version (version, url) VALUES ('" . mysqli_real_escape_string($conn, $version) . "', '" . mysqli_real_escape_string($conn, $url) . "')";
    }

    if (mysqli_query($conn, $sql)) {
        echo json_encode([
            "status" => "success",
            "version" => $version,
            "url" => $url
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => mysqli_error($conn)
        ]);
    }

    mysqli_close($conn);
?>
