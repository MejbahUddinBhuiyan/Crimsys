<?php
// C:\xmp\htdocs\Crimsys\api\cop\fir_evidence_upload.php
// Accepts multipart/form-data POST: firId, caption?, file

header('Content-Type: application/json');

// ---- DB bootstrap (expects $pdo) ----
try {
  if (!isset($pdo)) {
    // Use your central db.php if you have it:
    $dbPath = __DIR__ . '/../../inc/db.php';
    if (file_exists($dbPath)) {
      require_once $dbPath; // must set $pdo
    } else {
      // Fallback inline (adjust credentials)
      $pdo = new PDO('mysql:host=127.0.0.1;dbname=crimsys;charset=utf8mb4', 'root', '');
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'db_connect_failed']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
  exit;
}

$firId   = isset($_POST['firId']) ? (int)$_POST['firId'] : 0;
$caption = isset($_POST['caption']) ? trim($_POST['caption']) : '';

if ($firId <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'invalid_firId']);
  exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'file_missing_or_error', 'phpErr'=>($_FILES['file']['error'] ?? null)]);
  exit;
}

// optional: ensure FIR exists
try {
  $stmt = $pdo->prepare("SELECT FirID FROM fir WHERE FirID = :id LIMIT 1");
  $stmt->execute([':id'=>$firId]);
  if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'fir_not_found']);
    exit;
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'db_error_check_fir']);
  exit;
}

// Validate file
$allowedExt  = ['jpg','jpeg','png','gif','pdf','mp4'];
$allowedMime = [
  'image/jpeg','image/png','image/gif','application/pdf','video/mp4'
];

$origName = $_FILES['file']['name'];
$tmpPath  = $_FILES['file']['tmp_name'];
$size     = (int)$_FILES['file']['size'];

$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

// MIME check (best effort)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $tmpPath);
finfo_close($finfo);

if (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMime, true)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'unsupported_type','ext'=>$ext,'mime'=>$mime]);
  exit;
}

// size cap 50 MB (adjust as desired; also check php.ini upload_max_filesize & post_max_size)
$maxBytes = 50 * 1024 * 1024;
if ($size > $maxBytes) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'file_too_large','max'=>$maxBytes]);
  exit;
}

// Ensure directory
$baseDir = realpath(__DIR__ . '/../../uploads');
if ($baseDir === false) {
  // create base uploads if missing
  $baseBase = realpath(__DIR__ . '/../../');
  $baseDir = $baseBase . DIRECTORY_SEPARATOR . 'uploads';
  if (!is_dir($baseDir)) {
    @mkdir($baseDir, 0775, true);
  }
}
$evDir = $baseDir . DIRECTORY_SEPARATOR . 'fir_evidence' . DIRECTORY_SEPARATOR . $firId;
if (!is_dir($evDir)) {
  @mkdir($evDir, 0775, true);
}

// Build a safe filename
$slugCaption = preg_replace('/[^a-z0-9\-]+/i', '-', $caption);
$slugCaption = trim($slugCaption, '-');
$filename = time() . '-' . ($slugCaption ?: 'file') . '.' . $ext;

$destAbs = $evDir . DIRECTORY_SEPARATOR . $filename;

// Move upload
if (!move_uploaded_file($tmpPath, $destAbs)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'move_failed']);
  exit;
}

// Build DB relative path (web-servable). Assuming /Crimsys is your URL base.
$relative = '/Crimsys/uploads/fir_evidence/' . $firId . '/' . $filename;

// Who uploaded? If you have a session with CopID, take it; else default.
$uploadedBy = 10000003; // TODO: replace with $_SESSION['CopID'] when auth is wired

try {
  $stmt = $pdo->prepare("
    INSERT INTO firevidence (FirID, Caption, FilePath, UploadedByCopID, CreatedAt)
    VALUES (:fir, :cap, :path, :cop, NOW())
  ");
  $stmt->execute([
    ':fir'  => $firId,
    ':cap'  => $caption !== '' ? $caption : null,
    ':path' => $relative,
    ':cop'  => $uploadedBy,
  ]);

  $evidenceId = (int)$pdo->lastInsertId();

  echo json_encode([
    'ok' => true,
    'evidenceId' => $evidenceId,
    'file' => [
      'name' => $origName,
      'mime' => $mime,
      'size' => $size,
      'path' => $relative
    ]
  ]);
} catch (Exception $e) {
  // best effort: remove the file we just wrote
  @unlink($destAbs);
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'db_insert_failed']);
  exit;
}
