<?php
// /Crimsys/api/cop_me.php
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

$sql = "SELECT CopID, MustChangePassword, MustSetPhoto, PhotoPath FROM Cop WHERE CopID=? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) json_fail('sql_prepare_failed', 500, ['detail'=>$conn->error]);
$stmt->bind_param('i', $copid);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();
if (!$row) json_fail('not_found', 404);

json_ok([
  'copid'   => $row['CopID'],
  'mustPass'=> (int)$row['MustChangePassword'],
  'mustPhoto'=> (int)$row['MustSetPhoto'],
  'photo'   => $row['PhotoPath'] ?? ''
]);
