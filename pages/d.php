<?php
header('Content-Type: application/json');
$conn = mysqli_connect("localhost", "root", "", "clinic_db");
if (!$conn) {
    echo json_encode(["error" => mysqli_connect_error()]);
    exit;
}
$result = mysqli_query($conn, "SELECT * FROM health_metrics LIMIT 1");
$row = mysqli_fetch_assoc($result);
echo json_encode($row ?? ["message" => "No data found"]);
?>