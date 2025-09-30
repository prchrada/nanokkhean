<?php
// เพิ่ม $conn เป็นพารามิเตอร์สำหรับรับการเชื่อมต่อฐานข้อมูล
function sendLinePushMessage($conn, $recipientUserId, $messageText) {
    
    // ดึง Token จากฐานข้อมูล
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'line_channel_access_token'");
    $stmt->execute();
    $result = $stmt->get_result();
    $setting = $result->fetch_assoc();
    $channelAccessToken = $setting['setting_value'] ?? '';

    if (empty($channelAccessToken) || empty($recipientUserId)) {
        // ไม่ต้องทำอะไรถ้าไม่มี Token หรือไม่มี User ID ผู้รับ
        return;
    }

    $url = 'https://api.line.me/v2/bot/message/push';

    $headers = array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $channelAccessToken
    );

    $message = [
        'type' => 'text',
        'text' => $messageText
    ];

    $body = json_encode([
        'to' => $recipientUserId,
        'messages' => [$message]
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}
?>

