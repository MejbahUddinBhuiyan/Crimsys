<?php
// C:\xmp\htdocs\Crimsys\api\cop\crime_type_create.php
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
$copid = (int)$_SESSION['cop_id'];

require_once __DIR__ . '/../../dbconnect.php';



$raw = file_get_contents('php://input');
$in  = json_decode($raw ?: '[]', true);

$name = trim((string)($in['name'] ?? ''));
if ($name === '' || mb_strlen($name) < 2) json_fail('invalid_name', 422);

// ensure unique
$chk = $conn->prepare("SELECT CrimeTypeID FROM CrimeType WHERE Name=? LIMIT 1");
if (!$chk) json_fail('sql_prepare_failed', 500, ['detail'=>$conn->error]);
$chk->bind_param('s', $name);
$chk->execute();
$res = $chk->get_result();
$exists = ($res && $res->num_rows > 0);
$chk->close();
if ($exists) json_fail('duplicate_name', 409);

$sql = "INSERT INTO CrimeType (Name, IsActive, CreatedByCopID, CreatedAt) VALUES (?, 1, ?, NOW())";
$stmt = $conn->prepare($sql);
if (!$stmt) json_fail('sql_prepare_failed', 500, ['detail'=>$conn->error]);
$stmt->bind_param('si', $name, $copid);
if (!$stmt->execute()) { $stmt->close(); json_fail('sql_execute_failed', 500, ['detail'=>$conn->error]); }
$id = $stmt->insert_id;
$stmt->close();

json_ok([
  'item'=>[
    'CrimeTypeID'=>$id,
    'Name'=>$name,
    'IsActive'=>1,
    'CreatedByCopID'=>$copid
  ]
], 201);
