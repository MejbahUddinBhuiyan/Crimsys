<?php
// /Crimsys/api/cop_login.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
  ]);
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../dbconnect.php';

function json_ok(array $d=[], int $code=200){ http_response_code($code); echo json_encode(['ok'=>true]+$d); exit; }
function json_fail(string $m='error', int $code=400, array $e=[]){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$m]+$e); exit; }

$raw = file_get_contents('php://input');
$in  = json_decode($raw ?: '[]', true);

$copid = preg_replace('/\D/', '', (string)($in['copid'] ?? ''));
$pass  = (string)($in['password'] ?? '');

if (strlen($copid) !== 8 || $pass === '') {
  json_fail('invalid', 422);
}

$sql = "SELECT CopID, Password, MustChangePassword, MustSetPhoto, SuspendedUntil 
        FROM Cop WHERE CopID=? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) json_fail('sql_prepare_failed', 500, ['detail'=>$conn->error]);

$stmt->bind_param('i', $copid);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) json_fail('wrong_id', 401);

if (!password_verify($pass, (string)$row['Password'])) {
  json_fail('wrong_password', 401);
}

// check suspension
if (!empty($row['SuspendedUntil'])) {
  $until = strtotime($row['SuspendedUntil']);
  if ($until && $until > time()) {
    $msg = 'Suspended until ' . date('Y-m-d H:i', $until);
    json_fail($msg, 403, ['suspended_until'=>$row['SuspendedUntil']]);
  }
}

$_SESSION['cop_id'] = (int)$row['CopID'];

$mustPass  = (int)($row['MustChangePassword'] ?? 0);
$mustPhoto = (int)($row['MustSetPhoto'] ?? 0);

if ($mustPass === 1 || $mustPhoto === 1) {
  json_ok(['next'=>'onboarding','redirect'=>'/Crimsys/html/cop_setup.html']);
}

json_ok(['next'=>'home','redirect'=>'/Crimsys/html/cop_home.html']);
