<?php
// C:\xmp\htdocs\Crimsys\api\cop\fir_closure_get.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../dbconnect.php';

function out($d){ echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function bad($m,$c=400){ http_response_code($c); out(['ok'=>false,'error'=>$m]); }

if ($_SERVER['REQUEST_METHOD'] !== 'GET') bad('method_not_allowed',405);

$firId = isset($_GET['firId']) ? (int)$_GET['firId'] : 0;
if ($firId <= 0) bad('missing_firId');

$sql = "SELECT ClosureID, FirID, Type, Remarks, ClosedByCopID, CreatedAt
        FROM fir_closure WHERE FirID = ? ORDER BY ClosureID DESC LIMIT 1";
$st  = $conn->prepare($sql);
$st->bind_param('i', $firId);
$st->execute();
$res = $st->get_result()->fetch_assoc();
$st->close();

out([
  'ok'      => true,
  'closed'  => !!$res,
  'closure' => $res ?: null
]);
