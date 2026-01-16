<?php
// /Crimsys/api/suspect/fir.php
header('Content-Type: application/json');

require_once __DIR__ . '/../../dbconnect.php';
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server', 'detail' => 'DB not ready']);
    exit;
}

$name     = trim($_GET['name']     ?? '');
$district = trim($_GET['district'] ?? '');
$thana    = trim($_GET['thana']    ?? '');
$limit    = max(1, min(25, (int)($_GET['limit'] ?? 10)));

$where  = ["d.Role IN ('Suspect','Accused')"];
$params = [];

if ($name !== '') {
    $where[] = "d.PersonName LIKE :pname";
    $params[':pname'] = "%{$name}%";
}

if ($district !== '' && strtolower($district) !== 'all') {
    // f.Location sample is "Dhaka , Kotwali"
    $where[] = "f.Location LIKE :districtMatch";
    $params[':districtMatch'] = "{$district}%";
}

if ($thana !== '' && strtolower($thana) !== 'all') {
    $where[] = "f.Location LIKE :thanaMatch";
    $params[':thanaMatch'] = "%, {$thana}";
}

$sql =
"SELECT d.FdetailsID AS Id,
        d.PersonName,
        d.Role,
        d.FirID,
        f.Location,
        f.IncidentDate,
        f.Status
   FROM firdetails d
   JOIN fir f ON f.FirID = d.FirID
  WHERE " . implode(' AND ', $where) . "
  ORDER BY f.CreatedAt DESC, d.FdetailsID DESC
  LIMIT {$limit}";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode(['ok' => true, 'data' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server', 'detail' => $e->getMessage()]);
}
