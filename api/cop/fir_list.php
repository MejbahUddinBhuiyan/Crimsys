<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../dbconnect.php';

function ok($d){ echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function bad($m,$c=400){ http_response_code($c); ok(['ok'=>false,'error'=>$m]); }

// Accept both GET and JSON POST
$raw = file_get_contents('php://input');
$jo  = $raw ? json_decode($raw, true) : [];

$q      = isset($_GET['q']) ? $_GET['q'] : (isset($_POST['q']) ? $_POST['q'] : ($jo['q'] ?? ''));
$status = isset($_GET['status']) ? $_GET['status'] : (isset($_POST['status']) ? $_POST['status'] : ($jo['status'] ?? ''));
$from   = isset($_GET['from']) ? $_GET['from'] : (isset($_POST['from']) ? $_POST['from'] : ($jo['from'] ?? ''));
$to     = isset($_GET['to']) ? $_GET['to'] : (isset($_POST['to']) ? $_POST['to'] : ($jo['to'] ?? ''));
$copId  = isset($_GET['assignedCopId']) ? $_GET['assignedCopId'] : (isset($_POST['assignedCopId']) ? $_POST['assignedCopId'] : ($jo['assignedCopId'] ?? ''));

// Build SQL
$sql = "SELECT FirID, CreatedAt, Status, Location, CrimeTypeID, CopID, AssignedCopID
        FROM fir WHERE 1=1";
$types = '';
$args  = [];

// text search (FirID or Location/Description)
if ($q !== '' && $q !== null) {
  $sql .= " AND (FirID = ? OR Location LIKE CONCAT('%',?,'%') OR Description LIKE CONCAT('%',?,'%'))";
  $types .= 'iss';
  $args[]  = is_numeric($q) ? (int)$q : 0; // exact id match if numeric
  $args[]  = $q;
  $args[]  = $q;
}

// status
if ($status !== '' && $status !== null) {
  $sql .= " AND Status = ?";
  $types .= 's';
  $args[]  = $status;
}

// date range on CreatedAt
if ($from !== '' && $from !== null) {
  $sql .= " AND DATE(CreatedAt) >= ?";
  $types .= 's';
  $args[]  = $from;
}
if ($to !== '' && $to !== null) {
  $sql .= " AND DATE(CreatedAt) <= ?";
  $types .= 's';
  $args[]  = $to;
}

// assigned cop filter
if ($copId !== '' && $copId !== null) {
  $sql .= " AND AssignedCopID = ?";
  $types .= 'i';
  $args[]  = (int)$copId;
}

$sql .= " ORDER BY CreatedAt DESC LIMIT 200";

// Execute
$stmt = $conn->prepare($sql);
if ($types !== '') { $stmt->bind_param($types, ...$args); }
if (!$stmt->execute()) { bad('db_failed', 500); }
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
  $items[] = [
    'FirID'          => (int)$row['FirID'],
    'CreatedAt'      => $row['CreatedAt'],
    'Status'         => $row['Status'],
    // if you don’t have a name table yet, return the ID so the UI can still show something
    'Crime'          => $row['CrimeTypeID'],
    'Location'       => $row['Location'],
    'AssignedCopID'  => $row['AssignedCopID'] ?: $row['CopID'],
  ];
}
$stmt->close();

ok(['ok'=>true, 'items'=>$items]);
