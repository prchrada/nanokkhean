<?php
// --- PART 1: LOGIC ONLY (NO HTML OUTPUT) ---
require_once 'config.php';
require_once 'line_messaging_api_function.php'; 

// ตรวจสอบและเริ่ม session เพียงครั้งเดียว
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ตรวจสอบเงื่อนไขก่อนแสดงผล ถ้าไม่ผ่านให้ redirect ทันที
if (!isset($_SESSION["loggedin"]) || empty($_SESSION["cart"])) {
    header("location: cart.php");
    exit;
}

$seller_id = $_SESSION['id'];

$stmt_select = $conn->prepare("SELECT full_name, phone, address, city, zip_code FROM users WHERE id = ?");
$stmt_select->bind_param("i", $seller_id);
$stmt_select->execute();
$result = $stmt_select->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $full_name = $row['full_name'];
    $phone = $row['phone'];
    $address = $row['address'];
    $city = $row['city'];
    $zip_code = $row['zip_code'];
}
$stmt_select->close();

$buyer_id = $_SESSION['id'];
$cart_items = $_SESSION['cart'];
$total_price = 0;
$order_success = false;
$order_id = null;

// 2. คำนวณราคาสินค้า (ยังไม่มีการแสดงผล)
$product_ids = array_keys($cart_items);
if (!empty($product_ids)) {
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $sql = "SELECT * FROM products WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('i', count($product_ids));
    $stmt->bind_param($types, ...$product_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[$row['id']] = $row;
    }

    foreach ($cart_items as $product_id => $quantity) {
        if (isset($products[$product_id])) {
            $total_price += $products[$product_id]['price'] * $quantity;
        }
    }
}


// 3. ตรวจสอบและบันทึกข้อมูลการสั่งซื้อ (ยังไม่มีการแสดงผล)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['full_name'])) {
    $shipping_address = trim($_POST['full_name']) . "\n" .
                        "เบอร์โทรศัพท์: " . trim($_POST['phone']) . "\n" .
                        "ที่อยู่: " . trim($_POST['address']) . "\n" .
                        "จังหวัด: " . trim($_POST['city']) . "\n" .
                        "รหัสไปรษณีย์: " . trim($_POST['zip_code']);

    if (!empty(trim($_POST['full_name']))) {
        $conn->begin_transaction();
        try {
            $sql_order = "INSERT INTO orders (buyer_id, total_price, shipping_address, status) VALUES (?, ?, ?, 'pending_payment')";
            $stmt_order = $conn->prepare($sql_order);
            // --- FIX: Changed "idss" to "ids" ---
            $stmt_order->bind_param("ids", $buyer_id, $total_price, $shipping_address);
            $stmt_order->execute();
            $order_id = $stmt_order->insert_id;

            $sql_item = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
            $stmt_item = $conn->prepare($sql_item);

            $seller_notifications = [];
            foreach ($cart_items as $product_id => $quantity) {
                $product = $products[$product_id];
                $stmt_item->bind_param("iiid", $order_id, $product_id, $quantity, $product['price']);
                $stmt_item->execute();
                
                $seller_id = $product['seller_id'];
                if (!isset($seller_notifications[$seller_id])) {
                    $seller_notifications[$seller_id] = [
                        'user_id' => '',
                        'message' => "🔔 มีคำสั่งซื้อใหม่!\nหมายเลข: #{$order_id}\n\nรายการสินค้า:\n"
                    ];
                }
                $seller_notifications[$seller_id]['message'] .= "- {$product['name']} (จำนวน: {$quantity})\n";
            }
            
            foreach($seller_notifications as $seller_id => &$notification) {
                $stmt_uid = $conn->prepare("SELECT line_user_id FROM users WHERE id = ?");
                $stmt_uid->bind_param("i", $seller_id);
                $stmt_uid->execute();
                $user_result = $stmt_uid->get_result()->fetch_assoc();
                
                if ($user_result && !empty($user_result['line_user_id'])) {
                    $notification['user_id'] = $user_result['line_user_id'];
                    $notification['message'] .= "\nยอดรวม (ของร้านคุณ): [โปรดตรวจสอบในระบบ]\nกรุณาตรวจสอบและยืนยันการชำระเงิน";
                    sendLinePushMessage($conn, $notification['user_id'], $notification['message']);
                }
            }

            unset($_SESSION['cart']);
            $conn->commit();
            
            $url = "http://127.0.0.1/marketplace/firebase_noti_send.php";

            $payload = [
                "target_user_id" => $buyer_id,
                "title" => "สั่งซื้อสำเร็จ!",
                "body" => "รหัสคำสั่งซื้อของคุณคือ #{$order_id}. กรุณาไปที่หน้า 'คำสั่งซื้อของฉัน' เพื่อชำระเงิน",
                "data" => ["order_id" => $order_id]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);   
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $response = curl_exec($ch);
            curl_close($ch);

            $order_success = true;
        } catch (Exception $e) {
            $conn->rollback();
            // --- DEBUGGING CODE ADDED ---
            // This will show the exact database error.
            // In a real application, you should log this error instead of showing it.
            die("เกิดข้อผิดพลาดในการบันทึกคำสั่งซื้อ: " . $e->getMessage());
        }
    }
}

// --- PART 2: DISPLAY (HTML OUTPUT) ---
// เมื่อ Logic ทั้งหมดทำงานเสร็จแล้ว จึงค่อยเรียก header.php เพื่อแสดงผล
require_once 'header.php'; 
?>

<div class="card p-4 mx-auto" style="max-width: 700px;">
    <?php if ($order_success): ?>
        <div class="alert alert-success text-center">
            <h4 class="alert-heading">สั่งซื้อสำเร็จ!</h4>
            <p>รหัสคำสั่งซื้อของคุณคือ #<?php echo $order_id; ?>. กรุณาไปที่หน้า 'คำสั่งซื้อของฉัน' เพื่อชำระเงิน</p>
            <a href="my_orders_buyer.php" class="btn btn-primary">ไปที่คำสั่งซื้อของฉัน</a>
        </div>
    <?php else: ?>
        <h2 class="text-center mb-4">ยืนยันการสั่งซื้อ</h2>
        <form action="checkout.php" method="post">
            <h5 class="mb-3">ที่อยู่สำหรับจัดส่ง</h5>
            <div class="mb-3">
                <label for="full_name" class="form-label">ชื่อ-สกุล ผู้รับ</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($full_name); ?>" required>
            </div>
            <div class="mb-3">
                <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" required>
            </div>
            <div class="mb-3">
                <label for="address" class="form-label">ที่อยู่ (บ้านเลขที่, หมู่, ถนน, ตำบล/แขวง, อำเภอ/เขต)</label>
                <textarea name="address" class="form-control" rows="3" required><?php echo htmlspecialchars($address); ?></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="city" class="form-label">จังหวัด</label>
                    <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($city); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="zip_code" class="form-label">รหัสไปรษณีย์</label>
                    <input type="text" name="zip_code" class="form-control" value="<?php echo htmlspecialchars($zip_code); ?>" required>
                </div>
            </div>
            <hr>
            <div class="d-flex justify-content-between fs-4 fw-bold">
                <span>ยอดรวมทั้งหมด:</span>
                <span class="text-danger">฿<?php echo number_format($total_price, 2); ?></span>
            </div>
            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-success btn-lg">ยืนยันการสั่งซื้อ</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>

