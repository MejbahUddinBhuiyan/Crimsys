<?php
// /Crimsys/api/cop/firdetails_create.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start(['cookie_httponly'=>true, 'cookie_samesite'=>'Lax']);
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function ok($data=[], int $code=200){ http_response_code($code); echo json_encode(['ok'=>true]+$data); exit; }
function fail($m='error', int $code=400, $extra=[]){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$m]+(array)$extra); exit; }

if (empty($_SESSION['cop_id'])) fail('unauthorized', 401);

require_once __DIR__ . '/../../dbconnect.php';

$in = json_decode(file_get_contents('php://input') ?: '[]', true);
$firId  = (int)($in['firId'] ?? 0);
$people = $in['people'] ?? [];

if ($firId <= 0) fail('invalid_firId', 422);
if (!is_array($people)) fail('invalid_people', 422);
if (count($people) === 0) ok(['message'=>'nothing_to_create']); // not an error

// Prepare insert
$sql = "INSERT INTO firdetails (Role, PersonName, ContactInfo, FirID, CreatedAt) VALUES (?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
if (!$stmt) fail('sql_prepare_failed', 500, ['detail'=>$conn->error]);

$allowedRoles = ['Victim','Witness','Suspect','Accused'];

$created = 0;
foreach ($people as $p) {
  $role = trim((string)($p['role'] ?? ''));
  $name = trim((string)($p['personName'] ?? ''));
  $contact = trim((string)($p['contactInfo'] ?? ''));

  if ($name === '' || !in_array($role, $allowedRoles, true)) continue;

  $stmt->bind_param('sssi', $role, $name, $contact, $firId);
  if ($stmt->execute()) $created++;
}

$stmt->close();
ok(['created'=>$created]);
