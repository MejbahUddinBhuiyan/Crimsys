<?php
declare(strict_types=1);

// ---- headers ----
header_remove('X-Powered-By');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// ---- DB ----
// go 2 levels up:  /Crimsys/api/admin  ->  /Crimsys
require_once dirname(__DIR__, 2) . '/dbconnect.php';
$conn->set_charset('utf8mb4');

function fail(string $m, int $code=400, array $ex=[]): void {
  http_response_code($code);
  echo json_encode(['ok'=>false,'error'=>$m] + $ex);
  exit;
}
function ok(array $d=[]): void {
  echo json_encode(['ok'=>true] + $d);
  exit;
}

// ---- input ----
$raw = file_get_contents('php://input');
$in = json_decode($raw ?: '[]', true);
if ($in === null && json_last_error() !== JSON_ERROR_NONE) {
  fail('invalid_json', 422, ['detail'=>json_last_error_msg()]);
}

$name      = trim((string)($in['name'] ?? ''));
$email     = trim((string)($in['email'] ?? ''));
$badge     = trim((string)($in['badge'] ?? ''));
$contact   = trim((string)($in['contact'] ?? ''));
$rank      = trim((string)($in['rank'] ?? ''));
$station   = trim((string)($in['station'] ?? ''));
$present   = trim((string)($in['present'] ?? ''));
$permanent = trim((string)($in['permanent'] ?? ''));

// required
if ($name==='' || $email==='' || $badge==='' || $rank==='' || $station==='') {
  fail('missing_fields', 422, ['need'=>'name,email,badge,rank,station']);
}

// ---- next 8-digit CopID (start from 10000001) ----
$nextId = 10000001;
$res = $conn->query("SELECT MAX(CopID) AS mx FROM Cop");
if ($res) {
  $row = $res->fetch_assoc();
  if ($row && (int)$row['mx'] >= 10000001) {
    $nextId = (int)$row['mx'] + 1;
  }
  $res->free();
}

// ---- simple temp password + hash ----
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789@$#';
$tmpPass = '';
for ($i=0; $i<10; $i++) $tmpPass .= $chars[random_int(0, strlen($chars)-1)];
$passHash = password_hash($tmpPass, PASSWORD_BCRYPT);

// ---- insert ----
// columns in Cop: CopID, Name, Email, BadgeNo, ContactNo, Rank, StationName,
//                 PresentAddress, PermanentAddress, Password, MustChangePassword
$sql = "INSERT INTO Cop
        (CopID, Name, Email, BadgeNo, ContactNo, Rank, StationName,
         PresentAddress, PermanentAddress, Password, MustChangePassword)
        VALUES (?,?,?,?,?,?,?,?,?,?,1)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  fail('db_prepare_failed', 500, ['mysqli'=> $conn->error]);
}

/* types: 1 integer + 9 strings => 'isssssssss' */
$stmt->bind_param(
  'isssssssss',
  $nextId,
  $name,
  $email,
  $badge,
  $contact,
  $rank,
  $station,
  $present,
  $permanent,
  $passHash
);

if (!$stmt->execute()) {
  fail('db_exec_failed', 500, ['mysqli'=>$stmt->error]);
}
$stmt->close();

ok([
  'cop_id'    => $nextId,
  'password'  => $tmpPass
]);
