<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../dbconnect.php';

function json_ok(array $d=[], int $code=200){ http_response_code($code); echo json_encode(['ok'=>true]+$d); exit; }
function json_fail(string $m='error', int $code=400, array $e=[]){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$m]+$e); exit; }

$copid = $_GET['copid'] ?? '';
$copid = preg_replace('/\D/', '', (string)$copid);
if (strlen($copid) !== 8) json_fail('invalid_copid', 422);

/* Try StationName first, then fallback to Station if your DB uses that */
$sql = "SELECT CopID, 
               UPPER(Name) AS Name, 
               Rank, Email, ContactNo,
               StationName, BadgeNo,
               PresentAddress, PermanentAddress,
               PhotoPath, SuspendedUntil
        FROM Cop
        WHERE CopID=? 
        LIMIT 1";


$stmt = $conn->prepare($sql);
if (!$stmt) {
  // log and show reason (helps during dev)
  json_fail('sql_prepare_failed', 500, ['detail'=> $conn->error]);
}

$stmt->bind_param('i', $copid);
if (!$stmt->execute()){
  $stmt->close();
  json_fail('sql_execute_failed', 500, ['detail'=> $conn->error]);
}

$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) json_fail('not_found', 404);

/* Favor StationName if present, else fallback to Station */
$station = !empty($row['StationName']) ? $row['StationName'] : ($row['Station'] ?? '');

$cop = [
  'CopID'            => $row['CopID'],
  'Name'             => $row['Name'],
  'Rank'             => $row['Rank'],
  'Email'            => $row['Email'],
  'ContactNo'        => $row['ContactNo'],
  'StationName'      => $station,
  'BadgeNo'          => $row['BadgeNo'],
  'PresentAddress'   => $row['PresentAddress'],
  'PermanentAddress' => $row['PermanentAddress'],
  'PhotoPath'        => $row['PhotoPath'],
  'SuspendedUntil'   => $row['SuspendedUntil']
];

json_ok(['cop'=>$cop]);
