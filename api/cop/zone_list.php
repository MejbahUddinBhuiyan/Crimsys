<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../dbconnect.php';

if (!isset($db)) {
  if (isset($conn) && $conn instanceof mysqli) $db = $conn;
  elseif (isset($mysqli) && $mysqli instanceof mysqli) $db = $mysqli;
}

try {
  if (!$db instanceof mysqli) throw new Exception('DB not available');
  $res = $db->query("SELECT ZoneID, District, Thana FROM crimezone ORDER BY District, Thana");
  if (!$res) throw new Exception($db->error);

  $items = [];
  while ($r = $res->fetch_assoc()) $items[] = $r;
  echo json_encode(['ok'=>true, 'items'=>$items]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
