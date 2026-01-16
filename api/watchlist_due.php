<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../dbconnect.php';

$days = intval($_GET['days'] ?? 7);

$sql = "SELECT w.WatchListID, w.CriminalID, w.Status, w.ReviewDate, w.Reason,
               c.FullName, c.NID, c.Photo, c.City, c.Street,
               TIMESTAMPDIFF(YEAR, c.DateOfBirth, CURDATE()) AS Age
        FROM watchlist w
        JOIN criminal c ON c.CriminalID = w.CriminalID
        WHERE w.Status='Active'
          AND w.ReviewDate <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
        ORDER BY w.ReviewDate ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([":days" => $days]);

echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
