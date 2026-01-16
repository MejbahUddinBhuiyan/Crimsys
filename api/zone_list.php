<?php
// /Crimsys/api/zone_list.php
header('Content-Type: application/json');

require_once __DIR__ . '/../dbconnect.php';

if (!$conn) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'DB connection failed']);
  exit;
}

$district = trim($_GET['district'] ?? '');

// 1) All (or filtered) zones
if ($district !== '') {
  $zstmt = $conn->prepare("SELECT ZoneID, District, Thana FROM crimezone WHERE District = ? ORDER BY Thana, ZoneID");
  $zstmt->bind_param('s', $district);
} else {
  $zstmt = $conn->prepare("SELECT ZoneID, District, Thana FROM crimezone ORDER BY District, Thana, ZoneID");
}
$zstmt->execute();
$zres = $zstmt->get_result();
$zones = [];
while ($r = $zres->fetch_assoc()) { $zones[] = $r; }

// 2) District list
$dres = $conn->query("SELECT DISTINCT District FROM crimezone ORDER BY District");
$districts = [];
while ($r = $dres->fetch_assoc()) { $districts[] = $r['District']; }

// 3) Thana list (all or by district)
if ($district !== '') {
  $tstmt = $conn->prepare("SELECT DISTINCT Thana FROM crimezone WHERE District = ? ORDER BY Thana");
  $tstmt->bind_param('s', $district);
  $tstmt->execute();
  $tres = $tstmt->get_result();
} else {
  $tres = $conn->query("SELECT DISTINCT Thana FROM crimezone ORDER BY Thana");
}
$thanas = [];
while ($r = $tres->fetch_assoc()) { $thanas[] = $r['Thana']; }

echo json_encode(['ok' => true, 'districts' => $districts, 'thanas' => $thanas, 'zones' => $zones], JSON_INVALID_UTF8_IGNORE);
