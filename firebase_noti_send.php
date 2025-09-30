<?php
require 'config.php';
require __DIR__ . '/vendor/autoload.php';
header('Content-Type: application/json');

use Google\Auth\Credentials\ServiceAccountCredentials;

// Path to your Firebase service account JSON
$serviceAccountPath = __DIR__ . '/service-account.json';

// Scope required for FCM API
$scopes = ['https://www.googleapis.com/auth/firebase.messaging'];

// Create credentials object
$creds = new ServiceAccountCredentials($scopes, $serviceAccountPath);

// Get access token
$accessTokenArray = $creds->fetchAuthToken();
if (!isset($accessTokenArray['access_token'])) {
    die("Failed to get access token");
}

$accessToken = $accessTokenArray['access_token'];

// parse JSON input
$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['target_user_id']) || empty($data['title']) || empty($data['body'])) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'missing fields']);
    exit;
}

$target_user_id = $data['target_user_id'];
$title = $data['title'];
$body  = $data['body'];
$extraData = isset($data['data']) ? $data['data'] : [];

// fetch tokens for this user
$sql = "SELECT fcm_token FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $target_user_id);
$stmt->execute();
$result = $stmt->get_result();

$tokens = [];
while ($row = $result->fetch_assoc()) {
    $tokens[] = $row['fcm_token'];
}
$stmt->close();
$conn->close();

if (empty($tokens)) {
    echo json_encode(['ok'=>true,'sent'=>0,'info'=>'no tokens']);
    exit;
}

foreach ($tokens as $token) {
    $payload = [
        'message' => [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body
            ],
        ]
    ];

    $ch = curl_init("https://fcm.googleapis.com/v1/projects/product-cfe8a/messages:send");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $accessToken,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $response = curl_exec($ch);
    curl_close($ch);
}

// send to FCM


// return result
echo json_encode(['ok'=>true, 'response'=>json_decode($response, true)]);
