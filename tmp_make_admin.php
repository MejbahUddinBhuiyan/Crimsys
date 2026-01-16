<?php
/**
 * ONE-TIME HELPER: Force a single admin account.
 * Email: mejbahxyz@gmail.com
 * Pass : admin123
 *
 * After it prints “Done”, DELETE this file.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/dbconnect.php';

/* If your dbconnect.php does not select the DB, select it here */
if (empty(@$conn->query("SELECT DATABASE()")->fetch_row()[0])) {
    $conn->select_db('Crimsys');   // change if your DB name differs
}

/* Ensure Admin table exists (Email UNIQUE) */
$create = "
CREATE TABLE IF NOT EXISTS Admin (
  AdminID  INT AUTO_INCREMENT PRIMARY KEY,
  Name     VARCHAR(100) NOT NULL,
  Email    VARCHAR(150) NOT NULL UNIQUE,
  Password VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if (!$conn->query($create)) {
    die('Failed to ensure Admin table: ' . $conn->error);
}

/* Force only one admin */
$onlyEmail = 'mejbahxyz@gmail.com';
$displayName = 'Mejbah (Super Admin)';
$plain = 'admin123';
$hash  = password_hash($plain, PASSWORD_BCRYPT);

/* Remove everything except this email (so only he has authority) */
$del = $conn->prepare("DELETE FROM Admin WHERE Email <> ?");
$del->bind_param('s', $onlyEmail);
$del->execute();
$del->close();

/* If exists, update. If not, insert. */
$check = $conn->prepare("SELECT AdminID FROM Admin WHERE Email=? LIMIT 1");
$check->bind_param('s', $onlyEmail);
$check->execute();
$res = $check->get_result();
$exists = $res->fetch_assoc();
$check->close();

if ($exists) {
    $upd = $conn->prepare("UPDATE Admin SET Name=?, Password=? WHERE Email=? LIMIT 1");
    if (!$upd) die('Prepare update failed: ' . $conn->error);
    $upd->bind_param('sss', $displayName, $hash, $onlyEmail);
    if (!$upd->execute()) die('Update failed: ' . $upd->error);
    $upd->close();
    echo "Admin updated: $onlyEmail / admin123";
} else {
    $ins = $conn->prepare("INSERT INTO Admin (Name, Email, Password) VALUES (?,?,?)");
    if (!$ins) die('Prepare insert failed: ' . $conn->error);
    $ins->bind_param('sss', $displayName, $onlyEmail, $hash);
    if (!$ins->execute()) die('Insert failed: ' . $ins->error);
    $ins->close();
    echo "Admin created: $onlyEmail / admin123";
}

echo "<br>Done. Please DELETE tmp_make_admin.php now.";
