<?php
// /Crimsys/api/cop_set_photo.php
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

/* --- validate upload --- */
if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
  json_fail('no_photo', 422, ['php_upload_error'=> $_FILES['photo']['error'] ?? 'missing']);
}
$tmp = $_FILES['photo']['tmp_name'];

/* --- choose extension by mime --- */
$mime = @mime_content_type($tmp) ?: ($_FILES['photo']['type'] ?? '');
$ext  = '.jpg';
$map  = [
  'image/jpeg' => '.jpg',  'image/pjpeg'=> '.jpg',
  'image/png'  => '.png',
  'image/webp' => '.webp'
];
if (isset($map[$mime])) $ext = $map[$mime];

/* --- absolute destination folder (…/Crimsys/img/cops) --- */
$root = realpath(dirname(__DIR__));           // /Crimsys
if ($root === false) $root = dirname(__DIR__); // fallback
$destDir = $root . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'cops';

if (!is_dir($destDir)) {
  @mkdir($destDir, 0777, true);
  if (!is_dir($destDir)) {
    json_fail('mkdir_failed', 500, ['destDir'=>$destDir]);
  }
}

$filename = $copid . $ext;
$destAbs  = $destDir . DIRECTORY_SEPARATOR . $filename;
$destRel  = '/img/cops/' . $filename;         // stored in DB
$ok = false;

/* --- Try with GD re-save (better portability) --- */
if (function_exists('imagecreatefromstring')) {
  $bin = @file_get_contents($tmp);
  if ($bin !== false) {
    $im = @imagecreatefromstring($bin);
    if ($im !== false) {
      if ($ext === '.png') {
        $ok = @imagepng($im, $destAbs, 6);
      } elseif ($ext === '.webp' && function_exists('imagewebp')) {
        $ok = @imagewebp($im, $destAbs, 80);
      } else {
        $ok = @imagejpeg($im, $destAbs, 85);
      }
      @imagedestroy($im);
    }
  }
}

/* --- Fallbacks: move/rename/copy --- */
if (!$ok) {
  $ok = @move_uploaded_file($tmp, $destAbs);
  if (!$ok) $ok = @rename($tmp, $destAbs);
  if (!$ok) $ok = @copy($tmp, $destAbs);
}
if (!$ok || !file_exists($destAbs)) {
  json_fail('file_move_failed', 500, [
    'destAbs'=>$destAbs,
    'mime'=>$mime,
    'tmp'=>$tmp
  ]);
}

/* --- Update DB path and flag --- */
$sql = "UPDATE Cop SET PhotoPath=?, MustSetPhoto=0 WHERE CopID=? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) json_fail('sql_prepare_failed', 500, ['detail'=>$conn->error]);
$stmt->bind_param('si', $destRel, $copid);
if (!$stmt->execute()) { $stmt->close(); json_fail('sql_execute_failed', 500, ['detail'=>$conn->error]); }
$stmt->close();

json_ok([
  'message'=>'photo_saved',
  'photo'  => $destRel,
  'url'    => '/Crimsys' . $destRel
]);
