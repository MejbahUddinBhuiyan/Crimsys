<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../dbconnect.php';

function ok($data){ echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function bad($msg,$code=400){ http_response_code($code); ok(['ok'=>false,'error'=>$msg]); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') bad('method_not_allowed',405);

// Accept JSON or form
$raw = file_get_contents('php://input');
$jo  = $raw ? json_decode($raw,true) : null;
$firId  = isset($_POST['firId']) ? (int)$_POST['firId'] : (int)($jo['firId'] ?? 0);
$status = $_POST['status'] ?? ($jo['status'] ?? null);
$byCop  = isset($_POST['byCopId']) ? (int)$_POST['byCopId'] : (int)($jo['byCopId'] ?? 0);
$reason = $_POST['reason'] ?? ($jo['reason'] ?? null);

if ($firId<=0)           bad('missing_firId');
if (!$status)            bad('missing_status');

$conn->begin_transaction();
try{
  // old status
  $oldQ = $conn->prepare("SELECT Status FROM fir WHERE FirID=? FOR UPDATE");
  $oldQ->bind_param('i',$firId);
  $oldQ->execute();
  $old = $oldQ->get_result()->fetch_assoc();
  $oldQ->close();
  if(!$old) { $conn->rollback(); bad('fir_not_found',404); }

  // update
  $up = $conn->prepare("UPDATE fir SET Status=?, UpdatedAt=NOW() WHERE FirID=?");
  $up->bind_param('si',$status,$firId);
  $up->execute();
  $up->close();

  // status history
  $sh = $conn->prepare("INSERT INTO fir_status_history (FirID, OldStatus, NewStatus, Reason, ByCopID)
                        VALUES (?, ?, ?, ?, ?)");
  $oldStatus = $old['Status'] ?? null;
  $byCopNull = $byCop ?: null;
  $sh->bind_param('isssi',$firId,$oldStatus,$status,$reason,$byCop);
  $sh->execute();
  $sh->close();

  // timeline
  $msg = "Status changed from ".($oldStatus?:'—')." to ".$status.($reason ? " – ".$conn->real_escape_string($reason) : "");
  $tl  = $conn->prepare("INSERT INTO firtimeline (FirID, Type, Message, ByCopID) VALUES (?, 'Status', ?, ?)");
  $tl->bind_param('isi',$firId,$msg,$byCop);
  $tl->execute();
  $tl->close();

  $conn->commit();
  ok(['ok'=>true,'newStatus'=>$status]);
}catch(Throwable $e){
  $conn->rollback();
  bad('server_error',500);
}
