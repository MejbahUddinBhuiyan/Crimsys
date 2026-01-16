<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../dbconnect.php';

$name       = $_GET['name'] ?? null;
$nid        = $_GET['nid'] ?? null;
$criminalId = $_GET['criminalId'] ?? null;
$status     = $_GET['status'] ?? "Active";
$limit      = intval($_GET['limit'] ?? 25);
$offset     = intval($_GET['offset'] ?? 0);

$sql = "SELECT w.WatchListID, w.CriminalID, w.Status, w.ReviewDate, w.Reason,
               c.FullName, c.NID, c.Photo, c.City, c.Street,
               TIMESTAMPDIFF(YEAR, c.DateOfBirth, CURDATE()) AS Age
        FROM watchlist w
        JOIN criminal c ON c.CriminalID = w.CriminalID
        WHERE w.Status = :status";

$params = [":status" => $status];

if ($name) {
    $sql .= " AND c.FullName LIKE :name";
    $params[":name"] = "%$name%";
}
if ($nid) {
    $sql .= " AND c.NID LIKE :nid";
    $params[":nid"] = "%$nid%";
}
if ($criminalId) {
    $sql .= " AND c.CriminalID = :criminalId";
    $params[":criminalId"] = $criminalId;
}

$sql .= " ORDER BY w.ReviewDate ASC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    if ($k == ":criminalId" || $k == ":limit" || $k == ":offset") {
        $stmt->bindValue($k, $v, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
}
$stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
$stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
$stmt->execute();

echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
