
<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../dbconnect.php';

// Count by status
$sql1 = "SELECT Status, COUNT(*) AS total FROM watchlist GROUP BY Status";
$stmt1 = $pdo->query($sql1);
$stats = $stmt1->fetchAll(PDO::FETCH_ASSOC);

// Count due soon
$sql2 = "SELECT COUNT(*) AS due_soon
         FROM watchlist
         WHERE Status='Active'
           AND ReviewDate <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
$stmt2 = $pdo->query($sql2);
$due = $stmt2->fetch(PDO::FETCH_ASSOC);

echo json_encode(["success" => true, "stats" => $stats, "due" => $due]);
