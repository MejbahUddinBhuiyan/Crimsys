<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../dbconnect.php';

function ok($data){ echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function bad($msg,$code=400){ http_response_code($code); ok(['ok'=>false,'error'=>$msg]); }

$firId = isset($_GET['firId']) ? (int)$_GET['firId'] : 0;
if ($firId <= 0) bad('missing_firId');

$stmt = $conn->prepare("SELECT Status FROM fir WHERE FirID=?");
$stmt->bind_param('i',$firId);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$res) bad('fir_not_found',404);
ok(['ok'=>true,'status'=>$res['Status'] ?? 'Open']);
