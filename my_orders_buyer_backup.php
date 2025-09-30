<?php
require_once 'config.php'; // ใช้ config.php เพื่อเริ่ม session และเชื่อมต่อ DB ก่อน

// ตรวจสอบว่าเป็น buyer หรือไม่
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] != 'buyer') {
    header("location: index.php");
    exit;
}

$buyer_id = $_SESSION['id'];

// Upload payment slip logic
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["payment_slip"]) && $_FILES["payment_slip"]["error"] == 0) {
    $order_id_slip = $_POST['order_id'];
    
    $target_dir = "slips/";
    $imageFileType = strtolower(pathinfo(basename($_FILES["payment_slip"]["name"]), PATHINFO_EXTENSION));
    $new_filename = "slip_" . $order_id_slip . "_" . time() . "." . $imageFileType;
    $target_file = $target_dir . $new_filename;

    // Check if image file is a actual image
    $check = getimagesize($_FILES["payment_slip"]["tmp_name"]);
    if($check !== false) {
        if (move_uploaded_file($_FILES["payment_slip"]["tmp_name"], $target_file)) {
            // Update order status and slip filename in database
            $sql_update_slip = "UPDATE orders SET payment_slip = ?, status = 'paid', order_status = 'processing', is_read_by_seller = 0 WHERE id = ? AND buyer_id = ?";
            $stmt_update_slip = $conn->prepare($sql_update_slip);
            $stmt_update_slip->bind_param("sii", $new_filename, $order_id_slip, $buyer_id);
            $stmt_update_slip->execute();
        }
    }
}

// --- NEW: Cancel order logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_order'])) {
    $order_id_to_cancel = $_POST['order_id'];

    // Use a transaction to ensure both deletions succeed or fail together
    $conn->begin_transaction();
    try {
        // 1. Delete items from order_items table
        $sql_delete_items = "DELETE FROM order_items WHERE order_id = ?";
        $stmt_delete_items = $conn->prepare($sql_delete_items);
        $stmt_delete_items->bind_param("i", $order_id_to_cancel);
        $stmt_delete_items->execute();

        // 2. Delete the order from orders table (only if it belongs to the user and is pending payment)
        $sql_delete_order = "DELETE FROM orders WHERE id = ? AND buyer_id = ? AND status = 'pending_payment'";
        $stmt_delete_order = $conn->prepare($sql_delete_order);
        $stmt_delete_order->bind_param("ii", $order_id_to_cancel, $buyer_id);
        $stmt_delete_order->execute();

        $conn->commit();
    } catch (Exception $e) {
        // If any error occurs, roll back the transaction
        $conn->rollback();
        // Optional: Log the error or display a generic error message
        // For now, we'll just let the page reload. In a production environment, logging is recommended.
    }
}

// ดึงข้อมูลคำสั่งซื้อทั้งหมดของผู้ซื้อคนนี้
$sql_orders = "SELECT * FROM orders WHERE buyer_id = ? ORDER BY created_at DESC";
$stmt_orders = $conn->prepare($sql_orders);
$stmt_orders->bind_param("i", $buyer_id);
$stmt_orders->execute();
$orders_result = $stmt_orders->get_result();

// --- ส่วน Logic ทำงานเสร็จแล้ว ถึงตรงนี้จึงจะเริ่มแสดงผล HTML ---
require_once 'header.php';
?>

<div class="container mt-4">
    <h2 class="text-center mb-4">คำสั่งซื้อของฉัน</h2>

    <?php if ($orders_result->num_rows > 0): ?>
        <?php while ($order = $orders_result->fetch_assoc()): 
            // --- NEW: Logic for status colors ---
            $status_map = [
                'pending_payment' => ['class' => 'bg-warning text-dark', 'text' => 'รอชำระเงิน'],
                'paid' => ['class' => 'bg-info text-dark', 'text' => 'รอตรวจสอบ'],
                'preparing_shipment' => ['class' => 'bg-primary', 'text' => 'กำลังเตรียมจัดส่ง'],
                'shipped' => ['class' => 'bg-success', 'text' => 'จัดส่งแล้ว'],
            ];
            $status_display = $status_map[$order['status']] ?? ['class' => 'bg-secondary', 'text' => htmlspecialchars($order['status'])];
        ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">รหัสคำสั่งซื้อ #<?php echo $order['id']; ?></h5>
                    <span class="badge <?php echo $status_display['class']; ?>"><?php echo $status_display['text']; ?></span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <h6>รายละเอียดคำสั่งซื้อ</h6>
                            <p class="mb-1"><strong>วันที่สั่ง:</strong> <?php echo $order['created_at']; ?></p>
                            <p class="mb-1"><strong>ยอดรวม:</strong> <span class="fw-bold text-danger">฿<?php echo number_format($order['total_price'], 2); ?></span></p>
                        </div>
                        <div class="col-md-6">
                            <h6>ที่อยู่สำหรับจัดส่ง</h6>
                            <p class="mb-1" style="white-space: pre-wrap;"><?php echo htmlspecialchars($order['shipping_address']); ?></p>
                        </div>
                    </div>
                    
                    <?php
                    // ดึงรายการสินค้าและข้อมูลผู้ขายในแต่ละออเดอร์
                    $sql_items = "SELECT oi.*, p.name as product_name, p.seller_id, u.username as seller_name, u.payment_details, u.payment_qr 
                                  FROM order_items oi 
                                  JOIN products p ON oi.product_id = p.id
                                  JOIN users u ON p.seller_id = u.id
                                  WHERE oi.order_id = ?";
                    $stmt_items = $conn->prepare($sql_items);
                    $stmt_items->bind_param("i", $order['id']);
                    $stmt_items->execute();
                    $items_result = $stmt_items->get_result();
                    
                    $sellers_in_order = [];
                    while($item = $items_result->fetch_assoc()){
                        $sellers_in_order[$item['seller_id']]['details'] = [
                            'name' => $item['seller_name'],
                            'payment' => $item['payment_details'],
                            'payment_qr' => $item['payment_qr']  // Add this line
                        ];
                        $sellers_in_order[$item['seller_id']]['items'][] = $item;
                    }
                    $stmt_items->close(); // ปิด statement เพื่อคืน resource และป้องกันข้อมูลรั่วไหลข้าม loop

                    foreach ($sellers_in_order as $seller_id => $seller_data):
                    ?>
                        <div class="border-top mt-3 pt-3">
                             <h6 class="fw-bold"><i class="bi bi-shop"></i> ร้านค้า: <?php echo htmlspecialchars($seller_data['details']['name']); ?></h6>
                            <div class="row mt-2">
                                <div class="col-md-7">
                                    <strong>รายการสินค้า:</strong>
                                    <ul class="list-group list-group-flush">
                                    <?php foreach ($seller_data['items'] as $product_item): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center ps-0">
                                            <span><i class="bi bi-box-seam"></i> <?php echo htmlspecialchars($product_item['product_name']); ?></span>
                                            <span class="badge bg-secondary rounded-pill">จำนวน: <?php echo $product_item['quantity']; ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                    </ul>
                                </div>
                                <div class="col-md-5 mt-3 mt-md-0">
                                    <strong>ข้อมูลการชำระเงิน:</strong>
                                    <?php if (!empty($seller_data['details']['payment'])): ?>
                                         <div class="p-3 rounded mt-1" style="background-color: #eef2f7; border: 1px solid #d0d9e2;">
                                             <div style="white-space: pre-wrap;"><?php echo htmlspecialchars($seller_data['details']['payment']); ?></div>
                                             <?php
                                                $seller_qr_filename = $seller_data['details']['payment_qr'];
                                                $display_seller_qr_path = !empty($seller_qr_filename) ? "uploads/" . htmlspecialchars($seller_qr_filename) : 'https://via.placeholder.com/100?text=No+QR';
                                             ?>
                                             <img src="<?php echo $display_seller_qr_path; ?>" alt="QR Code" class="img-thumbnail mt-2" style="width:100px; height:100px; object-fit: cover;">
                                         </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning p-2 mt-1">ผู้ขายยังไม่ระบุข้อมูลการชำระเงิน</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($order['status'] == 'pending_payment'): ?>
                        <hr>
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6><i class="bi bi-cash-coin"></i> แจ้งชำระเงิน</h6>
                                <form action="my_orders_buyer.php" method="post" enctype="multipart/form-data" class="d-flex align-items-center">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <input type="file" name="payment_slip" class="form-control me-2" required>
                                    <button type="submit" class="btn btn-success flex-shrink-0">อัปโหลดสลิป</button>
                                </form>
                                <div class="form-text">กรุณาอัปโหลดหลักฐานการชำระเงิน</div>
                            </div>
                            <form action="my_orders_buyer.php" method="post" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการยกเลิกคำสั่งซื้อนี้?');">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <button type="submit" name="cancel_order" class="btn btn-danger flex-shrink-0 ms-3">ยกเลิกคำสั่งซื้อ</button>
                            </form>
                        </div>
                    <?php elseif ($order['status'] == 'shipped' && !empty($order['tracking_number'])): ?>
                         <hr>
                        <p class="mt-3"><strong><i class="bi bi-truck"></i> เลขพัสดุ:</strong> <span class="badge bg-info text-dark fs-6"><?php echo htmlspecialchars($order['tracking_number']); ?></span></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="text-center">
            <p>คุณยังไม่มีคำสั่งซื้อ</p>
            <a href="index.php" class="btn btn-primary">ไปเลือกซื้อสินค้า</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
