<?php
function sendLineNotify($token, $message) {
    if (empty($token)) {
        return; // ไม่ต้องทำอะไรถ้าไม่มี token
    }
    
    $line_api = 'https://notify-api.line.me/api/notify';
    $headers = array(
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Bearer ' . $token
    );
    $data = http_build_query(array('message' => $message));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $line_api);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}
?>
