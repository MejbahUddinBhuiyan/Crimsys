<?php
// /Crimsys/api/cop/fir_evidence_delete.php
declare(strict_types=1);
header('Content-Type: application/json');

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
  }

  // --- DB bootstrap (PDO $pdo) --------------------------------------------
  // If you have an include, keep it. Otherwise use this minimal inline PDO.
  // require_once __DIR__ . '/../../inc/db.php';
  if (!isset($pdo)) {
    $dsn  = 'mysql:host=localhost;dbname=crimsys;charset=utf8mb4';
    $user = 'root';
    $pass = '';
    $opts = [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $pdo = new PDO($dsn, $user, $pass, $opts);
  }
  // ------------------------------------------------------------------------

  // Accept x-www-form-urlencoded or JSON
  $evidenceId = null;
  if (isset($_POST['evidenceId'])) {
    $evidenceId = (int) $_POST['evidenceId'];
  } else {
    $raw = file_get_contents('php://input');
    if ($raw) {
      $j = json_decode($raw, true);
      if (isset($j['evidenceId'])) {
        $evidenceId = (int) $j['evidenceId'];
      }
    }
  }

  if (!$evidenceId) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid_evidenceId']);
    exit;
  }

  // 1) Get the file path so we can unlink after deleting
  $stmt = $pdo->prepare("SELECT FilePath FROM firevidence WHERE EvidenceID = :id LIMIT 1");
  $stmt->execute([':id' => $evidenceId]);
  $row = $stmt->fetch();

  if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
  }

  // 2) Delete the record (single row by primary key)
  $del = $pdo->prepare("DELETE FROM firevidence WHERE EvidenceID = :id LIMIT 1");
  $del->execute([':id' => $evidenceId]);

  $deleted = $del->rowCount();

  // 3) Remove the physical file (if present)
  $fileRel = $row['FilePath']; // e.g. /Crimsys/uploads/fir_evidence/11/xxx.jpg
  if ($deleted && $fileRel) {
    // Build absolute path safely
    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'); // C:\xmp\htdocs
    $abs = $docroot . $fileRel;
    // Normalize slashes for Windows and suppress warning if file missing
    $abs = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $abs);
    if (is_file($abs)) {
      @unlink($abs);
    }
  }

  echo json_encode(['ok' => true, 'deleted' => (int)$deleted]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error']);
}
