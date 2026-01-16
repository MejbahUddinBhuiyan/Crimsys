<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../dbconnect.php';

function ok($d){ echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function bad($m,$c=400){ http_response_code($c); ok(['ok'=>false,'error'=>$m]); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') bad('method_not_allowed',405);

$raw = file_get_contents('php://input');
$jo  = $raw ? json_decode($raw,true) : null;
$firId    = isset($_POST['firId']) ? (int)$_POST['firId'] : (int)($jo['firId'] ?? 0);
$newCopId = isset($_POST['newCopId']) ? (int)$_POST['newCopId'] : (int)($jo['newCopId'] ?? 0);
$reason   = $_POST['reason'] ?? ($jo['reason'] ?? null);
$byCopId  = isset($_POST['byCopId']) ? (int)$_POST['byCopId'] : (int)($jo['byCopId'] ?? 0);

if ($firId<=0) bad('missing_firId');
if ($newCopId<=0) bad('missing_newCopId');

$conn->begin_transaction();
try{
  // get old
  $q = $conn->prepare("SELECT AssignedCopID FROM fir WHERE FirID=? FOR UPDATE");
  $q->bind_param('i',$firId);
  $q->execute();
  $old = $q->get_result()->fetch_assoc();
  $q->close();
  if(!$old){ $conn->rollback(); bad('fir_not_found',404); }

  $oldCop = (int)($old['AssignedCopID'] ?? 0);
  if ($oldCop === $newCopId){
    $conn->commit();
    ok(['ok'=>true,'unchanged'=>true]);
  }

  // update assignment
  $u = $conn->prepare("UPDATE fir SET AssignedCopID=?, UpdatedAt=NOW() WHERE FirID=?");
  $u->bind_param('ii',$newCopId,$firId);
  $u->execute();
  $u->close();

  // history
  $h = $conn->prepare("INSERT INTO fir_assignment_history (FirID, OldCopID, NewCopID, Reason, ByCopID)
                       VALUES (?, ?, ?, ?, ?)");
  $h->bind_param('iiisi',$firId,$oldCop,$newCopId,$reason,$byCopId);
  $h->execute();
  $h->close();

  // timeline
  $msg = "Reassigned from Cop #".($oldCop?:'—')." to Cop #".$newCopId.($reason ? " – ".$conn->real_escape_string($reason) : "");
  $tl  = $conn->prepare("INSERT INTO firtimeline (FirID, Type, Message, ByCopID) VALUES (?, 'Assignment', ?, ?)");
  $tl->bind_param('isi',$firId,$msg,$byCopId);
  $tl->execute();
  $tl->close();

  $conn->commit();
  ok(['ok'=>true]);
}catch(Throwable $e){
  $conn->rollback();
  bad('server_error',500);
}
