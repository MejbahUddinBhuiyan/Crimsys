<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../dbconnect.php';

function ok($d){ echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function bad($m,$c=400){ http_response_code($c); ok(['ok'=>false,'error'=>$m]); }

$firId = isset($_GET['firId']) ? (int)$_GET['firId'] : 0;
if ($firId<=0) bad('missing_firId');

$sql = "SELECT TimelineID, FirID, Type, Message, ByCopID, CreatedAt
        FROM firtimeline WHERE FirID=? ORDER BY TimelineID DESC";
$st = $conn->prepare($sql);
$st->bind_param('i',$firId);
$st->execute();
$rs = $st->get_result();
$out = [];
while($r = $rs->fetch_assoc()) $out[] = $r;
$st->close();

ok(['ok'=>true,'items'=>$out]);
