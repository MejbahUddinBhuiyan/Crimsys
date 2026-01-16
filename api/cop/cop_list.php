<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../dbconnect.php';

function out($ok, $data = null, $extra = []) {
    echo json_encode(array_merge(['success' => $ok], $data ? ['data' => $data] : [], $extra));
    exit;
}

try {
    $stmt = $pdo->query("SELECT CopID, FullName FROM cop ORDER BY FullName ASC");
    out(true, $stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    out(false, null, ['error' => $e->getMessage()]);
}
