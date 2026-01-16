<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Lax']);
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function ok($d=[], $c=200){ http_response_code($c); echo json_encode(['ok'=>true]+$d); exit; }
function fail($m='error',$c=400,$e=[]){ http_response_code($c); echo json_encode(['ok'=>false,'error'=>$m]+$e); exit; }

if (empty($_SESSION['cop_id'])) fail('unauthorized',401);
require_once __DIR__ . '/../../dbconnect.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id<=0) fail('invalid_id',422);

$st = $conn->prepare("SELECT CrimeTypeID, Code, Name, Category, Severity, Bailable, LawSection, Description, IsActive, CreatedAt, UpdatedAt
                      FROM CrimeType WHERE CrimeTypeID=? LIMIT 1");
if(!$st) fail('sql_prepare_failed',500,['detail'=>$conn->error]);
$st->bind_param('i',$id);
$st->execute();
$r = $st->get_result();
$item = $r? $r->fetch_assoc():null;
$st->close();

if(!$item) fail('not_found',404);
ok(['item'=>$item]);
