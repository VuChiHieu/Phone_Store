<?php
require_once '../config.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo '[]'; exit; }

$s    = $conn->real_escape_string($q);
$rows = $conn->query("
    SELECT id, name, price, thumbnail
    FROM products
    WHERE name LIKE '%$s%' OR description LIKE '%$s%'
    ORDER BY is_featured DESC, created_at DESC
    LIMIT 6
");

$result = [];
while ($r = $rows->fetch_assoc()) {
    $result[] = $r;
}
echo json_encode($result);