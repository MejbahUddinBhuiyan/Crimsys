<?php
header("Content-Type: application/json");
require_once("../dbconnect.php");

try {
    $criminalId = $_POST['criminalId'] ?? null;

    if (!$criminalId) {
        echo json_encode(["success" => false, "error" => "criminalId is required"]);
        exit;
    }

    // 1. Fetch profile info from `criminal`
    $sql = "SELECT Name, Address, OfficerInCharge, LastLocation
            FROM criminal
            WHERE CriminalID = :criminalId";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([":criminalId" => $criminalId]);
    $criminal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$criminal) {
        echo json_encode(["success" => false, "error" => "Criminal not found"]);
        exit;
    }

    // 2. Update latest history row for this criminal
    $update = "UPDATE criminalhistory h
               JOIN (
                   SELECT HistoryID
                   FROM criminalhistory
                   WHERE CriminalID = :criminalId
                   ORDER BY DateTime DESC
                   LIMIT 1
               ) latest ON h.HistoryID = latest.HistoryID
               SET h.Location = :location,
                   h.OfficerInCharge = :officer";
    $stmt = $pdo->prepare($update);
    $stmt->execute([
        ":criminalId" => $criminalId,
        ":location"   => $criminal['LastLocation'] ?? null,
        ":officer"    => $criminal['OfficerInCharge'] ?? null
    ]);

    echo json_encode(["success" => true, "message" => "History synced from criminal profile"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
