<?php
// /Crimsys/api/suspect/criminal.php
header('Content-Type: application/json');

require_once __DIR__ . '/../../dbconnect.php';
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server', 'detail' => 'DB not ready']);
    exit;
}

$name     = trim($_GET['name']     ?? '');
$nid      = trim($_GET['nid']      ?? '');
$dob      = trim($_GET['dob']      ?? '');     // yyyy-mm-dd
$district = trim($_GET['district'] ?? '');
$thana    = trim($_GET['thana']    ?? '');
$strict   = (int)($_GET['strict']  ?? 0);      // 0 | 1
$limit    = max(1, min(25, (int)($_GET['limit'] ?? 10)));

$where  = [];
$params = [];

/* --- geography (optional) --- */
if ($district !== '' && strtolower($district) !== 'all') {
    $where[] = "City = :district";
    $params[':district'] = $district;
}
if ($thana !== '' && strtolower($thana) !== 'all') {
    // No dedicated thana column in criminal table (based on your schema),
    // so we skip this if it doesn't exist. If you store thana in Street or a column,
    // change the condition accordingly.
    // Example if you stored thana in Street:
    // $where[] = "Street = :thana";
    // $params[':thana'] = $thana;
}

/* --- DOB (optional) --- */
if ($dob !== '') {
    $where[] = "DateOfBirth = :dob";
    $params[':dob'] = $dob;
}

/* --- NID / Name (optional) --- */
if ($nid !== '') {
    if ($strict) {
        $where[] = "NID = :nid";
        $params[':nid'] = $nid;
    } else {
        $where[] = "NID LIKE :nid";
        $params[':nid'] = "%{$nid}%";
    }
}

if ($name !== '') {
    if ($strict) {
        $where[] = "FullName = :name";
        $params[':name'] = $name;
    } else {
        $where[] = "FullName LIKE :name";
        $params[':name'] = "%{$name}%";
    }
}

$sql = "SELECT CriminalID, FullName, NID, Photo, DateOfBirth, City, Street, Zip, CopID,
               TIMESTAMPDIFF(YEAR, DateOfBirth, CURDATE()) AS Age
          FROM criminal";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

// Prefer exact richer signals first (NID exact, name exact), then recency.
$sql .= " ORDER BY (NID IS NOT NULL) DESC, (Photo IS NOT NULL) DESC, CriminalID DESC
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
