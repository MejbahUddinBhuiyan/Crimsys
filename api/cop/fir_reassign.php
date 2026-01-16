<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Lax']);
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function ok($d=[], $code=200){ http_response_code($code); echo json_encode(['ok'=>true]+$d); exit; }
function fail($m='error', $code=400, $e=[]){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$m]+$e); exit; }

if (empty($_SESSION['cop_id'])) fail('unauthorized', 401);
$byCop = (int)$_SESSION['cop_id'];

require_once __DIR__ . '/../../dbconnect.php';

$in = json_decode(file_get_contents('php://input') ?: '[]', true);
$firId = (int)($in['firId'] ?? 0);
$assignedCopId = (int)($in['assignedCopId'] ?? 0);
if ($firId <= 0) fail('invalid_firId', 422);
if ($assignedCopId <= 0) fail('invalid_assignedCopId', 422);

/* Optional: validate cop exists */
$stmt0 = $conn->prepare("SELECT CopID, FullName FROM cop WHERE CopID=? LIMIT 1");
if(!$stmt0) fail('sql_prepare_failed', 500, ['detail'=>$conn->error]);
$stmt0->bind_param('i', $assignedCopId);
$stmt0->execute();
$res0 = $stmt0->get_result();
if(!$res0 || $res0->num_rows === 0){ $stmt0->close(); fail('cop_not_found', 404); }
$rowCop = $res0->fetch_assoc();
$stmt0->close();

$conn->begin_transaction();
try {
  $stmt = $conn->prepare("UPDATE fir SET AssignedCopID=?, UpdatedAt=NOW() WHERE FirID=? LIMIT 1");
  if(!$stmt) fail('sql_prepare_failed', 500, ['detail'=>$conn->error]);
  $stmt->bind_param('ii', $assignedCopId, $firId);
  if(!$stmt->execute()){ throw new Exception($conn->error); }
  $stmt->close();

  $msg = "Assigned to Cop #{$assignedCopId}".($rowCop['FullName'] ? " ({$rowCop['FullName']})" : '');
  $stmt2 = $conn->prepare("INSERT INTO firtimeline (FirID, Type, Message, ByCopID) VALUES (?, 'Assignment', ?, ?)");
  if(!$stmt2) fail('sql_prepare_failed', 500, ['detail'=>$conn->error]);
  $stmt2->bind_param('isi', $firId, $msg, $byCop);
  if(!$stmt2->execute()){ throw new Exception($conn->error); }
  $stmt2->close();

  $conn->commit();
  ok(['assignedName'=> $rowCop['FullName'] ? "Cop #{$assignedCopId} ({$rowCop['FullName']})" : null ]);
} catch(Exception $e){
  $conn->rollback();
  fail('sql_execute_failed', 500, ['detail'=>$e->getMessage()]);
}
