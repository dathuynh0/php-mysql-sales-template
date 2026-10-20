<?php

require_once '/var/www/src/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$sql = "
    SELECT
        CategoryID,
        CategoryName
    FROM categories
    ORDER BY CategoryName
";

$result = $connection->query($sql);

$categories = [];

while ($row = $result->fetch_assoc()) {
    $categories[] = [
        'id' => (int) $row['CategoryID'],
        'name' => $row['CategoryName']
    ];
}

$result->free();
$connection->close();

echo json_encode(
    [
        'success' => true,
        'data' => $categories
    ],
    JSON_UNESCAPED_UNICODE
);