<?php
declare(strict_types=1);

// ---- session with explicit cookie path ----
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_path'     => '/',      // important: visible for all pages
  ]);
}

// JSON headers
header_remove('X-Powered-By');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../dbconnect.php';  // $conn (mysqli)
$conn->set_charset('utf8mb4');

function json_ok(array $d = [], int $code = 200){ http_response_code($code); echo json_encode(['ok'=>true]+$d); exit; }
function json_fail(string $msg='error', int $code=400, array $ex=[]){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]+$ex); exit; }

$in  = json_decode(file_get_contents('php://input') ?: '[]', true);
$email = trim((string)($in['email'] ?? ''));
$pass  = (string)($in['password'] ?? '');

if ($email === '' || $pass === '') {
  json_fail('invalid', 400);
}

// Find admin by email
$sql = "SELECT Email, Password FROM Admin WHERE Email = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
  json_fail('wrong_email', 401);
}

if (!password_verify($pass, (string)$row['Password'])) {
  json_fail('wrong_password', 401);
}

// Success: strengthen session + set cookie for root path
session_regenerate_id(true);
$_SESSION['admin'] = true;
$_SESSION['admin_email'] = (string)$row['Email'];

// (optional, but guarantees cookie path = '/')
setcookie(
  session_name(), session_id(),
  [
    'path'     => '/',         // important
    'httponly' => true,
    'samesite' => 'Lax',
  ]
);

json_ok(['redirect' => '/Crimsys/html/admin_home.html']);
