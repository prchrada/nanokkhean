<?php
require 'config.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['user_id']) || empty($data['token'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing fields']);
    exit;
}

$user_id = $data['user_id'];
$token = $data['token'];

$sql = "UPDATE users SET fcm_token = ? WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $token, $user_id);

if ($stmt->execute()) {
    echo json_encode(['ok'=>true]);
} else {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$stmt->error]);
}

$stmt->close();
$conn->close();