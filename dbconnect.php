<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "Crimsys";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'db_connect_failed']);
  exit;
}
$conn->set_charset('utf8mb4');
