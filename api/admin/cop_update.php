<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../dbconnect.php';

function json_ok(array $d=[], int $code=200){ http_response_code($code); echo json_encode(['ok'=>true]+$d); exit; }
function json_fail(string $m='error', int $code=400, array $e=[]){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$m]+$e); exit; }

/* ---- read JSON safely ---- */
$raw = file_get_contents('php://input');
$in  = json_decode($raw ?: '[]', true);
if ($in === null && json_last_error() !== JSON_ERROR_NONE){
  json_fail('invalid_json', 422, ['detail'=>json_last_error_msg()]);
}

$copid   = isset($in['copid'])   ? preg_replace('/\D/','', (string)$in['copid']) : '';
$email   = isset($in['email'])   ? trim((string)$in['email'])   : '';
$contact = isset($in['contact']) ? preg_replace('/\D/','', (string)$in['contact']) : '';
$rank    = isset($in['rank'])    ? trim((string)$in['rank'])    : '';
$station = isset($in['station']) ? trim((string)$in['station']) : '';
$present = isset($in['present']) ? trim((string)$in['present']) : '';

if (strlen($copid) !== 8)            json_fail('invalid_copid', 422);
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
                                    json_fail('invalid_email', 422);
if ($rank === '' || $station === '') json_fail('missing_fields', 422);

/* Make sure the row exists first */
$check = $conn->prepare("SELECT CopID FROM Cop WHERE CopID=? LIMIT 1");
if (!$check) json_fail('sql_prepare_failed',500,['detail'=>$conn->error]);
$ci = (int)$copid;
$check->bind_param('i', $ci);
$check->execute();
$res = $check->get_result();
if (!$res || !$res->fetch_assoc()){
  $check->close();
  json_fail('not_found',404);
}
$check->close();

/* Update editable fields */
$sql = "UPDATE Cop
        SET Email=?, ContactNo=?, Rank=?, StationName=?, PresentAddress=?
        WHERE CopID=? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) json_fail('sql_prepare_failed',500,['detail'=>$conn->error]);

$stmt->bind_param('sssssi', $email, $contact, $rank, $station, $present, $ci);
$ok = $stmt->execute();
if (!$ok){
  $err = $conn->errno === 1062 ? 'email_already_exists' : 'sql_execute_failed';
  $detail = $conn->error;
  $stmt->close();
  json_fail($err, 500, ['detail'=>$detail]);
}
$stmt->close();

json_ok(['updated'=>true]);
