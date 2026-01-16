<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../dbconnect.php';

    if (!isset($_GET['criminalId'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing criminalId']);
        exit;
    }

    $criminalId = (int)$_GET['criminalId'];

    $sql = "
        SELECT f.FirID, f.CreatedAt, i.RoleInCase
        FROM involves i
        JOIN fir f ON i.FirID = f.FirID
        WHERE i.CriminalID = :criminalId
        ORDER BY f.CreatedAt DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':criminalId', $criminalId, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
