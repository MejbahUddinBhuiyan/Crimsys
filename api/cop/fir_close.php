<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../dbconnect.php';

function ok($d){ echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function bad($m,$c=400){ http_response_code($c); ok(['ok'=>false,'error'=>$m]); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') bad('method_not_allowed',405);

$firId   = isset($_POST['firId']) ? (int)$_POST['firId'] : 0;
$type    = $_POST['type']   ?? '';
$remarks = $_POST['remarks'] ?? '';
$byCopId = isset($_POST['byCopId']) ? (int)$_POST['byCopId'] : 0;

if ($firId <= 0) bad('missing_firId');
if ($type === '') bad('missing_type');
if ($byCopId <= 0) bad('missing_byCopId');

// Insert closure
$stmt = $conn->prepare("INSERT INTO fir_closure (FirID, Type, Remarks, ClosedByCopID) VALUES (?, ?, ?, ?)");
$stmt->bind_param('issi', $firId, $type, $remarks, $byCopId);

if (!$stmt->execute()) {
    bad('db_error: '.$stmt->error, 500);
}
$stmt->close();

// Insert timeline entry
$msg = "Case closed with status: $type";
$tl = $conn->prepare("INSERT INTO firtimeline (FirID, Type, Message, ByCopID) VALUES (?, 'Closure', ?, ?)");
$tl->bind_param('isi', $firId, $msg, $byCopId);
$tl->execute();
$tl->close();

ok(['ok'=>true, 'closed'=>true]);
