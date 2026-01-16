<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../dbconnect.php';

$criminalId = $_GET['criminalId'] ?? null;
if (!$criminalId) {
    echo json_encode(["success" => false, "message" => "Missing criminalId"]);
    exit;
}

$sql = "SELECT w.*, c.FullName, c.NID, c.Photo, c.City, c.Street,
               TIMESTAMPDIFF(YEAR, c.DateOfBirth, CURDATE()) AS Age
        FROM watchlist w
        JOIN criminal c ON c.CriminalID = w.CriminalID
        WHERE w.CriminalID = :criminalId";

$stmt = $pdo->prepare($sql);
$stmt->execute([":criminalId" => $criminalId]);

echo json_encode(["success" => true, "data" => $stmt->fetch(PDO::FETCH_ASSOC)]);
