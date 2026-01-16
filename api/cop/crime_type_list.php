<?php
// C:\xmp\htdocs\Crimsys\api\cop\crime_type_list.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
  ]);
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function json_ok(array $d=[], int $code=200){ http_response_code($code); echo json_encode(['ok'=>true]+$d); exit; }
function json_fail(string $m='error', int $code=400, array $e=[]){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$m]+$e); exit; }

if (empty($_SESSION['cop_id'])) json_fail('unauthorized', 401);

require_once __DIR__ . '/../../dbconnect.php';

// filter
$active = $_GET['active'] ?? null;
if ($active !== null) {
  $active = ($active === '1') ? 1 : (($active === '0') ? 0 : null);
}

$sql = "SELECT CrimeTypeID, Name, IsActive, CreatedByCopID, CreatedAt FROM CrimeType";
if ($active !== null) $sql .= " WHERE IsActive = ?";
$sql .= " ORDER BY Name ASC";

if ($active !== null) {
  $stmt = $conn->prepare($sql);
  if (!$stmt) json_fail('sql_prepare_failed', 500, ['detail'=>$conn->error]);
  $stmt->bind_param('i', $active);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  $stmt->close();
} else {
  $res = $conn->query($sql);
  $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

json_ok(['items'=>$rows]);
