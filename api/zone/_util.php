<?php
// Common util for zone endpoints
header('Content-Type: application/json');

require_once __DIR__ . '/../../dbconnect.php';  // <-- your working connector

function build_filters(&$types, &$params) {
  $where = [];
  $from = $_GET['from'] ?? null;     // YYYY-MM-DD
  $to   = $_GET['to']   ?? null;     // YYYY-MM-DD
  $district = $_GET['district'] ?? null;
  $thana    = $_GET['thana']    ?? null;

  if ($from) { $where[] = "f.IncidentDate >= ?"; $types .= "s"; $params[] = $from; }
  if ($to)   { $where[] = "f.IncidentDate < DATE_ADD(?, INTERVAL 1 DAY)"; $types .= "s"; $params[] = $to; }
  if ($district) { $where[] = "cz.District = ?"; $types .= "s"; $params[] = $district; }
  if ($thana)    { $where[] = "cz.Thana    = ?"; $types .= "s"; $params[] = $thana;   }

  return $where ? ("WHERE " . implode(" AND ", $where)) : "";
}

function run_query($mysqli, $sql, $types = "", $params = []) {
  $stmt = $mysqli->prepare($sql);
  if (!$stmt) { http_response_code(500); echo json_encode(["ok"=>false, "error"=>$mysqli->error]); exit; }
  if ($types) { $stmt->bind_param($types, ...$params); }
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  $stmt->close();
  return $rows;
}
