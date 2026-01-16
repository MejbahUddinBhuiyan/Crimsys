<?php
// /Crimsys/api/cop_whoami.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
  ]);
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (empty($_SESSION['cop_id'])) {
  // Do NOT redirect; only say unauthorized
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']);
  exit;
}

require_once __DIR__ . '/../dbconnect.php';

$copid = (int)$_SESSION['cop_id'];

// fetch only safe info needed for header/profile
$sql = "SELECT CopID, Name, Rank, BadgeNo, StationName, ContactNo, Email, PhotoPath, SuspendedUntil
        FROM Cop
        WHERE CopID=? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $copid);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
  echo json_encode(['ok'=>false,'error'=>'not_found']);
  exit;
}

// Build web path for photo if it exists; else placeholder
$photo = '/Crimsys/img/cops/_placeholder.png';
if (!empty($row['PhotoPath'])) {
  $p = trim((string)$row['PhotoPath']);
  if ($p !== '') {
    if ($p[0] !== '/') $p = '/' . $p;
    $photo = '/Crimsys' . $p;
  }
}

// Basic status
$isSuspended = false;
if (!empty($row['SuspendedUntil'])) {
  $until = strtotime($row['SuspendedUntil']);
  if ($until && $until > time()) $isSuspended = true;
}

echo json_encode([
  'ok'   => true,
  'cop'  => [
    'CopID'      => (int)$row['CopID'],
    'Name'       => (string)$row['Name'],
    'Rank'       => (string)$row['Rank'],
    'BadgeNo'    => (string)($row['BadgeNo'] ?? ''),
    'Station'    => (string)($row['StationName'] ?? ''),
    'ContactNo'  => (string)($row['ContactNo'] ?? ''),
    'Email'      => (string)($row['Email'] ?? ''),
    'PhotoURL'   => $photo,
    'Suspended'  => $isSuspended
  ]
]);
