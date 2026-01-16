<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../dbconnect.php';

$data = json_decode(file_get_contents("php://input"), true);
$criminalId = $data['criminalId'] ?? null;
$reason     = $data['reason'] ?? "Manual: officer added to watchlist";
$reviewDate = $data['reviewDate'] ?? null;

if (!$criminalId) {
    echo json_encode(["success" => false, "message" => "Missing criminalId"]);
    exit;
}

$sql = "INSERT INTO watchlist (Status, Reason, ReviewDate, CriminalID)
        VALUES ('Active',
                :reason,
                COALESCE(:reviewDate, DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
                :criminalId)
        ON DUPLICATE KEY UPDATE
            Status='Active',
            Reason=VALUES(Reason),
            ReviewDate=VALUES(ReviewDate)";

$stmt = $pdo->prepare($sql);
$stmt->execute([":reason" => $reason, ":reviewDate" => $reviewDate, ":criminalId" => $criminalId]);

echo json_encode(["success" => true, "message" => "Watchlist updated"]);
