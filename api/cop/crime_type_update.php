<?php
// C:\xmp\htdocs\Crimsys\api\cop\crime_type_update.php
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

$raw = file_get_contents('php://input');
$in  = json_decode($raw ?: '[]', true);

$id     = (int)($in['id'] ?? 0);
$action = trim((string)($in['action'] ?? ''));

if ($id <= 0) json_fail('invalid_id', 422);

if ($action === 'toggle') {
  $isActive = isset($in['isActive']) ? (int)$in['isActive'] : null;
  if ($isActive === null || ($isActive !== 0 && $isActive !== 1)) json_fail('invalid_isActive', 422);

  $stmt = $conn->prepare("UPDATE CrimeType SET IsActive=? WHERE CrimeTypeID=? LIMIT 1");
  if (!$stmt) json_fail('sql_prepare_failed', 500, ['detail'=>$conn->error]);
  $stmt->bind_param('ii', $isActive, $id);
  if (!$stmt->execute()) { $stmt->close(); json_fail('sql_execute_failed', 500, ['detail'=>$conn->error]); }
  $stmt->close();
  json_ok(['message'=>'updated']);

} elseif ($action === 'rename') {
  $name = trim((string)($in['name'] ?? ''));
  if ($name === '' || mb_strlen($name) < 2) json_fail('invalid_name', 422);

  // Check duplicate
  $chk = $conn->prepare("SELECT CrimeTypeID FROM CrimeType WHERE Name=? AND CrimeTypeID<>? LIMIT 1");
  if (!$chk) json_fail('sql_prepare_failed', 500, ['detail'=>$conn->error]);
  $chk->bind_param('si', $name, $id);
  $chk->execute();
  $r = $chk->get_result();
  $dup = ($r && $r->num_rows > 0);
  $chk->close();
  if ($dup) json_fail('duplicate_name', 409);

  $stmt = $conn->prepare("UPDATE CrimeType SET Name=? WHERE CrimeTypeID=? LIMIT 1");
  if (!$stmt) json_fail('sql_prepare_failed', 500, ['detail'=>$conn->error]);
  $stmt->bind_param('si', $name, $id);
  if (!$stmt->execute()) { $stmt->close(); json_fail('sql_execute_failed', 500, ['detail'=>$conn->error]); }
  $stmt->close();
  json_ok(['message'=>'renamed']);
}

json_fail('invalid_action', 422);
