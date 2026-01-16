<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../dbconnect.php';

$data = json_decode(file_get_contents("php://input"), true);
$criminalId = $data['criminalId'] ?? null;
$reason     = $data['reason'] ?? null;

if (!$criminalId) {
    echo json_encode(["success" => false, "message" => "Missing criminalId"]);
    exit;
}

$sql = "UPDATE watchlist
        SET Status='Removed',
            Reason=CONCAT('Manual remove: ', COALESCE(:reason, 'no longer under observation')),
            ReviewDate=CURDATE()
        WHERE CriminalID = :criminalId AND Status='Active'";

$stmt = $pdo->prepare($sql);
$stmt->execute([":reason" => $reason, ":criminalId" => $criminalId]);

echo json_encode(["success" => true, "message" => "Watchlist entry removed"]);
