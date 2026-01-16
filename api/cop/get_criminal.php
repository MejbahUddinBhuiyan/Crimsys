<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../dbconnect.php'; // gives $conn

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
  echo json_encode(['ok' => false, 'error' => 'Invalid id']);
  exit;
}

$sql = "SELECT CriminalID, FullName, NID, Photo, Zip, Street, City, DateOfBirth, CreatedAt
        FROM criminal WHERE CriminalID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
  echo json_encode(['ok' => false, 'error' => 'Not found']);
  exit;
}

$row = $res->fetch_assoc();
$item = [
  'criminalId'  => (int)$row['CriminalID'],
  'fullName'    => $row['FullName'],
  'nid'         => $row['NID'],
  'photo'       => $row['Photo'], // stored like "/img/criminals/xxx.jpg"
  'zip'         => $row['Zip'],
  'street'      => $row['Street'],
  'city'        => $row['City'],
  'dateOfBirth' => $row['DateOfBirth'],
  'createdAt'   => $row['CreatedAt'],
];

// If you want FIRs, query involves/FIR table here
$firs = [];

echo json_encode(['ok' => true, 'item' => $item, 'firs' => $firs]);
