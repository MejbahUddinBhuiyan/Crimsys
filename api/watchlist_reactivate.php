<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../dbconnect.php';

$data = json_decode(file_get_contents("php://input"), true);
$criminalId = $data['criminalId'] ?? null;
$reason     = $data['reason'] ?? null;
$nextDate   = $data['nextDate'] ?? null;

if (!$criminalId) {
    echo json_encode(["success" => false, "message" => "Missing criminalId"]);
    exit;
}

$sql = "UPDATE watchlist
        SET Status='Active',
            Reason=COALESCE(:reason, CONCAT('Reactivated on ', CURDATE())),
            ReviewDate=COALESCE(:nextDate, DATE_ADD(CURDATE(), INTERVAL 30 DAY))
        WHERE CriminalID = :criminalId";

$stmt = $pdo->prepare($sql);
$stmt->execute([":reason" => $reason, ":nextDate" => $nextDate, ":criminalId" => $criminalId]);

echo json_encode(["success" => true, "message" => "Watchlist entry reactivated"]);
