<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

date_default_timezone_set('Asia/Dhaka');

require_once __DIR__ . '/../../dbconnect.php';

function json_ok(array $d=[], int $code=200){ http_response_code($code); echo json_encode(['ok'=>true]+$d); exit; }
function json_fail(string $m='error', int $code=400, array $e=[]){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$m]+$e); exit; }

/* read JSON body */
$raw = file_get_contents('php://input');
$in  = json_decode($raw ?: '[]', true);
if ($in === null && json_last_error() !== JSON_ERROR_NONE){
  json_fail('invalid_json', 422, ['detail'=>json_last_error_msg()]);
}

$copid = isset($in['copid']) ? preg_replace('/\D/','', (string)$in['copid']) : '';
if (strlen($copid) !== 8) json_fail('invalid_copid', 422);

$unsuspend = !empty($in['unsuspend']);
$untilIso  = isset($in['until']) ? trim((string)$in['until']) : '';

/* ensure cop exists */
$ci = (int)$copid;
$has = $conn->prepare("SELECT CopID FROM Cop WHERE CopID=? LIMIT 1");
if (!$has) json_fail('sql_prepare_failed',500,['detail'=>$conn->error]);
$has->bind_param('i',$ci);
$has->execute();
$r = $has->get_result();
if (!$r || !$r->fetch_assoc()){ $has->close(); json_fail('not_found',404); }
$has->close();

if ($unsuspend){
  $q = $conn->prepare("UPDATE Cop SET SuspendedUntil=NULL WHERE CopID=? LIMIT 1");
  if (!$q) json_fail('sql_prepare_failed',500,['detail'=>$conn->error]);
  $q->bind_param('i',$ci);
  if (!$q->execute()){ $q->close(); json_fail('sql_execute_failed',500,['detail'=>$conn->error]); }
  $q->close();
  json_ok(['unsuspended'=>true]);
}

/* suspend until date is required */
if ($untilIso === '') json_fail('missing_until',422);

/* validate datetime */
$ts = strtotime($untilIso);
if ($ts === false) json_fail('invalid_until',422);

/* store as Y-m-d H:i:s (Asia/Dhaka) */
$untilLocal = date('Y-m-d H:i:s', $ts);

$q = $conn->prepare("UPDATE Cop SET SuspendedUntil=? WHERE CopID=? LIMIT 1");
if (!$q) json_fail('sql_prepare_failed',500,['detail'=>$conn->error]);
$q->bind_param('si', $untilLocal, $ci);
if (!$q->execute()){ $q->close(); json_fail('sql_execute_failed',500,['detail'=>$conn->error]); }
$q->close();

json_ok(['suspended'=>true,'until'=>$untilLocal]);
