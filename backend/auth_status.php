<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

$response = [
    'logged_in' => isset($_SESSION['user_id']),
    'name' => $_SESSION['user_name'] ?? '',
    'email' => $_SESSION['user_email'] ?? '',
    'is_admin' => isset($_SESSION['is_admin']) ? (int) $_SESSION['is_admin'] : 0,
];

echo json_encode($response);
