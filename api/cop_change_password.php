<?php
// /Crimsys/api/cop_change_password.php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start([
    'cookie_httponly'=>true,
    'cookie_samesite'=>'Lax',
  ]);
}
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../dbconnect.php';

function json_ok(array $d=[], int $code=200){ http_response_code($code); echo json_encode(['ok'=>true]+$d); exit; }
function json_fail(string $m='error', int $code=400, array $e=[]){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$m]+$e); exit; }

if (empty($_SESSION['cop_id'])) json_fail('unauthorized', 401);
$copid = (int)$_SESSION['cop_id'];

$raw = file_get_contents('php://input');
$in  = json_decode($raw ?: '[]', true);
$new = (string)($in['newpass'] ?? '');

if (strlen($new) < 6) json_fail('weak_password', 422);
$hash = password_hash($new, PASSWORD_BCRYPT);

$sql = "UPDATE Cop SET Password=?, MustChangePassword=0 WHERE CopID=? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) json_fail('sql_prepare_failed', 500, ['detail'=>$conn->error]);
$stmt->bind_param('si', $hash, $copid);
if (!$stmt->execute()) { $stmt->close(); json_fail('sql_execute_failed', 500, ['detail'=>$conn->error]); }
$stmt->close();

json_ok(['message'=>'password_changed']);
