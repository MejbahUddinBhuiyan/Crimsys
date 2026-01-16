<?php
// /Crimsys/api/common/zones.php
header('Content-Type: application/json');

require_once __DIR__ . '/../../dbconnect.php';
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server', 'detail' => 'DB not ready']);
    exit;
}

$action   = $_GET['action'] ?? '';
$district = trim($_GET['district'] ?? '');

try {
    if ($action === 'districts') {
        $rows = $pdo->query("SELECT DISTINCT District FROM crimezone ORDER BY District")->fetchAll();
        echo json_encode(['ok' => true, 'data' => array_column($rows, 'District')]);
        exit;
    }

    if ($action === 'thanas') {
        if ($district === '' || strtolower($district) === 'all') {
            echo json_encode(['ok' => true, 'data' => []]);
            exit;
        }
        $stmt = $pdo->prepare(
            "SELECT DISTINCT Thana
               FROM crimezone
              WHERE District = :d
              ORDER BY Thana"
        );
        $stmt->execute([':d' => $district]);
        $rows = $stmt->fetchAll();
        echo json_encode(['ok' => true, 'data' => array_column($rows, 'Thana')]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server', 'detail' => $e->getMessage()]);
}
