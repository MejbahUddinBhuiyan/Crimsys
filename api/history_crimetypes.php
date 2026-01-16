<?php
header("Content-Type: application/json");
require_once "../dbconnect.php";

try {
    $query = "SELECT CrimeTypeID, Name FROM crimetype WHERE 1=1";
    $params = [];

    if (!empty($_GET['q'])) {
        $query .= " AND Name LIKE :q";
        $params[':q'] = "%" . $_GET['q'] . "%";
    }

    if (!empty($_GET['limit'])) {
        $query .= " LIMIT " . intval($_GET['limit']);
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "data" => $result]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
