<?php
// C:\xmp\htdocs\Crimsys\api\cop\fir_evidence_list.php
header('Content-Type: application/json');

// ---- DB bootstrap (expects $pdo) ----
try {
  if (!isset($pdo)) {
    $dbPath = __DIR__ . '/../../inc/db.php';
    if (file_exists($dbPath)) {
      require_once $dbPath; // sets $pdo
    } else {
      $pdo = new PDO('mysql:host=127.0.0.1;dbname=crimsys;charset=utf8mb4', 'root', '');
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'db_connect_failed']);
  exit;
}

$firId = isset($_GET['firId']) ? (int)$_GET['firId'] : 0;
if ($firId <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'invalid_firId']);
  exit;
}

try {
  $sql = "SELECT EvidenceID, FirID, Caption, FilePath, UploadedByCopID, CreatedAt
          FROM firevidence
          WHERE FirID = :id
          ORDER BY EvidenceID DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id'=>$firId]);
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true, 'items'=>$items]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'db_query_failed']);
}
