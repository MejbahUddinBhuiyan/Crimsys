<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../dbconnect.php';

$data = json_decode(file_get_contents("php://input"), true);
$criminalId = $data['criminalId'] ?? null;
$nextDate   = $data['nextDate'] ?? null;

if (!$criminalId) {
    echo json_encode(["success" => false, "message" => "Missing criminalId"]);
    exit;
}

$sql = "UPDATE watchlist
        SET ReviewDate = COALESCE(:nextDate, DATE_ADD(CURDATE(), INTERVAL 30 DAY))
        WHERE CriminalID = :criminalId AND Status = 'Active'";

$stmt = $pdo->prepare($sql);
$stmt->execute([":nextDate" => $nextDate, ":criminalId" => $criminalId]);

echo json_encode(["success" => true, "message" => "Review date renewed"]);
