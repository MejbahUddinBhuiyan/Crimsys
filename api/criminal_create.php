<?php
// /Crimsys/api/criminal_create.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start([
    'cookie_httponly'=>true,
    'cookie_samesite'=>'Lax',
  ]);
}
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../dbconnect.php';

function json_ok(array $d=[], int $code=200){ http_response_code($code); echo json_encode(['ok'=>true]+$d); exit; }
function json_fail(string $m='error', int $code=400, array $e=[]){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$m]+$e); exit; }

if (empty($_SESSION['cop_id'])) json_fail('unauthorized', 401);
$copid = (int)$_SESSION['cop_id'];

$fullName = trim($_POST['fullName'] ?? '');
$nid      = trim($_POST['nid'] ?? '');
$dob      = $_POST['dob'] ?? null;
$street   = trim($_POST['street'] ?? '');
$city     = trim($_POST['city'] ?? '');
$zip      = trim($_POST['zip'] ?? '');

if ($fullName === '' || $nid === '') {
  json_fail('missing_fields', 422);
}

/* ---- Generate next 8-digit CriminalID ---- */
$BASE = 10000000;
$next = $BASE;

$res = $conn->query("SELECT MAX(CriminalID) AS mx FROM Criminal WHERE CriminalID >= {$BASE}");
if ($res) {
  $row = $res->fetch_assoc();
  $mx  = (int)($row['mx'] ?? 0);
  $next = ($mx > 0 ? $mx + 1 : $BASE);
  $res->free();
}
if ($next < $BASE) $next = $BASE;

/* In the unlikely case of duplicate (parallel insert), advance once more */
$tries = 2;
while ($tries-- > 0) {
  $chk = $conn->prepare("SELECT 1 FROM Criminal WHERE CriminalID=? LIMIT 1");
  $chk->bind_param('i', $next);
  $chk->execute();
  $has = $chk->get_result()->fetch_row();
  $chk->close();
  if (!$has) break;
  $next++;
}

/* ---- Optional photo upload ---- */
$photoPath = null;
if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
  $tmp  = $_FILES['photo']['tmp_name'];
  $mime = @mime_content_type($tmp) ?: ($_FILES['photo']['type'] ?? '');
  $ext  = '.jpg';
  $map  = [
    'image/jpeg'=>'.jpg','image/pjpeg'=>'.jpg',
    'image/png'=>'.png','image/webp'=>'.webp','image/avif'=>'.avif'
  ];
  if (isset($map[$mime])) $ext = $map[$mime];

  $root = realpath(dirname(__DIR__)); if ($root===false) $root = dirname(__DIR__);
  $destDir = $root . '/img/criminals';
  if (!is_dir($destDir)) @mkdir($destDir, 0777, true);

  // use NID for filename (stable)
  $filename = preg_replace('/\W+/', '', $nid) . $ext;
  $destAbs  = $destDir . '/' . $filename;
  $destRel  = '/img/criminals/' . $filename;

  if (!@move_uploaded_file($tmp, $destAbs)) {
    if (!@rename($tmp, $destAbs) && !@copy($tmp, $destAbs)) {
      json_fail('photo_upload_failed', 500);
    }
  }
  $photoPath = $destRel;
}

/* ---- Insert with explicit 8-digit CriminalID ---- */
$sql = "INSERT INTO Criminal (CriminalID, CopID, FullName, NID, Photo, Zip, Street, City, DateOfBirth)
        VALUES (?,?,?,?,?,?,?,?,?)";
$stmt = $conn->prepare($sql);
if (!$stmt) json_fail('sql_prepare_failed', 500, ['detail'=>$conn->error]);

$stmt->bind_param(
  'iisssssss',
  $next, $copid, $fullName, $nid, $photoPath, $zip, $street, $city, $dob
);

if (!$stmt->execute()) {
  $errCode = $conn->errno;
  $err     = $conn->error;
  $stmt->close();
  if ($errCode == 1062) json_fail('duplicate', 409, ['detail' => $err]);
  json_fail('sql_execute_failed', 500, ['detail' => $err]);
}
$stmt->close();

/* return both raw and formatted 8-digit id */
json_ok([
  'message'     => 'criminal_added',
  'CriminalID'  => $next,
  'CriminalID8' => sprintf('%08d', $next)
]);
