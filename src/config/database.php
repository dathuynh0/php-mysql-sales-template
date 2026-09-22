<?php

$host = 'db';
$database = getenv('MYSQL_DATABASE');
$username = getenv('MYSQL_USER');
$password = getenv('MYSQL_PASSWORD');

$connection = new mysqli(
    $host,
    $username,
    $password,
    $database
);

if ($connection->connect_error) {
    die('Kết nối cơ sở dữ liệu thất bại: ' . $conn->connect_error);
}

$connection->set_charset('utf8mb4');