<?php
// config.php
// ไฟล์ตั้งค่าการเชื่อมต่อฐานข้อมูล

// เริ่ม session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// รายละเอียดการเชื่อมต่อฐานข้อมูล
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // <-- เปลี่ยนเป็น username ของคุณ
define('DB_PASSWORD', ''); // <-- เปลี่ยนเป็น password ของคุณ
define('DB_NAME', 'marketplace_db');

// สร้างการเชื่อมต่อ
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ตั้งค่า character set เป็น utf8
$conn->set_charset("utf8");

?>
