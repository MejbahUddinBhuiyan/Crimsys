<?php
// /Crimsys/api/zone_stats.php
header('Content-Type: application/json');

require_once __DIR__ . '/../dbconnect.php'; // provides $conn (mysqli)

if (!$conn) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'DB connection failed']);
  exit;
}

/*
  Query params:
    metric   = totals | monthly | status | high | age
    district = optional exact match (e.g., "Dhaka")
    thana    = optional exact match (e.g., "Tejgaon")
    from     = optional ISO date (YYYY-MM-DD)
    to       = optional ISO date (YYYY-MM-DD)
*/
$metric   = $_GET['metric']  ?? 'totals';
$district = trim($_GET['district'] ?? '');
$thana    = trim($_GET['thana']    ?? '');
$from     = trim($_GET['from']     ?? '');
$to       = trim($_GET['to']       ?? '');

$where = [];
$types = '';
$args  = [];

// Date range (inclusive)
if ($from !== '') { $where[] = 'f.IncidentDate >= ?';                         $types .= 's'; $args[] = $from; }
if ($to   !== '') { $where[] = 'f.IncidentDate < DATE_ADD(?, INTERVAL 1 DAY)'; $types .= 's'; $args[] = $to; }

// Zone filters
if ($district !== '') { $where[] = 'cz.District = ?'; $types .= 's'; $args[] = $district; }
if ($thana    !== '') { $where[] = 'cz.Thana = ?';    $types .= 's'; $args[] = $thana; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$fromSql = " FROM fir f
  JOIN fir_zone_map m ON m.FirID = f.FirID
  JOIN crimezone   cz ON cz.ZoneID = m.ZoneID ";

switch ($metric) {

  case 'monthly':
    $sql = "SELECT
              DATE_FORMAT(f.IncidentDate, '%Y-%m') AS Month,
              cz.District, cz.Thana,
              COUNT(*) AS FIR_Count
            $fromSql
            $whereSql
            GROUP BY Month, cz.ZoneID
            ORDER BY Month ASC, FIR_Count DESC";
    break;

  case 'status':
    $sql = "SELECT
              cz.District, cz.Thana,
              SUM(f.Status='Open')                    AS OpenCases,
              SUM(f.Status='Under Investigation')     AS UnderInvestigation,
              SUM(f.Status='Resolved')                AS ResolvedCases
            $fromSql
            $whereSql
            GROUP BY cz.ZoneID
            ORDER BY OpenCases DESC";
    break;

  case 'high':
    // Force only High priority
    $whereHigh = $whereSql ? "$whereSql AND f.Priority='High'" : "WHERE f.Priority='High'";
    $sql = "SELECT
              cz.District, cz.Thana,
              COUNT(*) AS HighPriorityCases
            $fromSql
            $whereHigh
            GROUP BY cz.ZoneID
            ORDER BY HighPriorityCases DESC";
    break;

  case 'age':
    $sql = "SELECT
              cz.District, cz.Thana,
              AVG(TIMESTAMPDIFF(DAY, f.IncidentDate, COALESCE(f.UpdatedAt, NOW()))) AS AvgAgeDays,
              COUNT(*) AS Total
            $fromSql
            $whereSql
            GROUP BY cz.ZoneID
            ORDER BY AvgAgeDays DESC";
    break;

  case 'totals':
  default:
    $sql = "SELECT
              cz.ZoneID, cz.District, cz.Thana,
              COUNT(*) AS TotalFIRs
            $fromSql
            $whereSql
            GROUP BY cz.ZoneID
            ORDER BY TotalFIRs DESC";
    break;
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $conn->error]);
  exit;
}
if ($types !== '') { $stmt->bind_param($types, ...$args); }
if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $stmt->error]);
  exit;
}

$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) { $rows[] = $row; }

echo json_encode(['ok' => true, 'metric' => $metric, 'rows' => $rows], JSON_INVALID_UTF8_IGNORE);
