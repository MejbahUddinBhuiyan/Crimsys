<?php
// C:\xmp\htdocs\Crimsys\api\cop\fir_view.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function json_ok(array $d = [], int $code = 200){ http_response_code($code); echo json_encode(['ok'=>true] + $d); exit; }
function json_fail(string $m='error', int $code=400, array $e=[]){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$m] + $e); exit; }

// Require a logged-in cop (match the rest of your APIs)
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
  ]);
}
if (empty($_SESSION['cop_id'])) json_fail('unauthorized', 401);

// ✅ This is the correct include path for your project
require_once __DIR__ . '/../../dbconnect.php';  // provides $conn (mysqli)

$firId = isset($_GET['firId']) ? (int)$_GET['firId'] : 0;
if ($firId <= 0) json_fail('invalid_firId', 422);

// --- Load FIR + crime type name ------------------------------------------------
$sql = "
  SELECT 
      f.*,
      ct.Name AS CrimeTypeName
  FROM fir f
  LEFT JOIN CrimeType ct ON ct.CrimeTypeID = f.CrimeTypeID
  WHERE f.FirID = ?
  LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) json_fail('sql_prepare_failed', 500, ['detail'=>$conn->error]);
$stmt->bind_param('i', $firId);
$stmt->execute();
$res = $stmt->get_result();
$fir = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$fir) json_fail('not_found', 404);

// --- Load persons from firdetails ---------------------------------------------
$details = [];
$stmt2 = $conn->prepare("SELECT Role, PersonName, ContactInfo FROM firdetails WHERE FirID=? ORDER BY FdetailsID ASC");
if (!$stmt2) json_fail('sql_prepare_failed', 500, ['detail'=>$conn->error]);
$stmt2->bind_param('i', $firId);
$stmt2->execute();
$r2 = $stmt2->get_result();
if ($r2) $details = $r2->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

// Split Victim/Witness vs Suspect/Accused for convenience
$vw = [];
$sus = [];
foreach ($details as $d) {
  $role = isset($d['Role']) ? $d['Role'] : '';
  if ($role === 'Victim' || $role === 'Witness') $vw[] = $d;
  else $sus[] = $d;
}

json_ok([
  'fir'       => $fir,
  'vw'        => $vw,
  'suspects'  => $sus,
  // optional placeholders (you can enrich later)
  'filedByName'   => null,
  'assignedName'  => null,
  'crimeName'     => $fir['CrimeTypeName'] ?? null,
  'timeline'      => []  // reserved for future
]);
