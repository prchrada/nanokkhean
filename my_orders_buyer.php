<?php
require_once 'config.php';
// ตรวจสอบว่าเป็น buyer หรือไม่
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] != 'buyer') {
    header("location: index.php");
    exit;
}

$buyer_id = $_SESSION['id'];

// Upload payment slip logic
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["payment_slip"]) && $_FILES["payment_slip"]["error"] == 0) {
    $order_id_slip = $_POST['order_id'];
    $seller_id_slip = $_POST['seller_id']; // NEW: Get seller_id
    
    $target_dir = "slips/";
    $imageFileType = strtolower(pathinfo(basename($_FILES["payment_slip"]["name"]), PATHINFO_EXTENSION));
    $new_filename = "slip_" . $order_id_slip . "_" . time() . "." . $imageFileType;
    $target_file = $target_dir . $new_filename;

    // Check if image file is a actual image
    $check = getimagesize($_FILES["payment_slip"]["tmp_name"]);
    if($check !== false) {
        if (move_uploaded_file($_FILES["payment_slip"]["tmp_name"], $target_file)) {
            // NEW: Update order_seller_details instead of orders
            $sql_update_slip = "UPDATE order_seller_details osd 
                                JOIN orders o ON osd.order_id = o.id
                                SET osd.payment_slip = ?, osd.status = 'paid', osd.is_read_by_seller = 0 
                                WHERE osd.order_id = ? AND osd.seller_id = ? AND o.buyer_id = ?";
            $stmt_update_slip = $conn->prepare($sql_update_slip);
            $stmt_update_slip->bind_param("siii", $new_filename, $order_id_slip, $seller_id_slip, $buyer_id);
            $stmt_update_slip->execute();
        }
    }
}

// --- UPDATED: Cancel seller-specific part of an order ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_order'])) {
    $order_id_to_cancel = $_POST['order_id'];
    $seller_id_to_cancel = $_POST['seller_id'];

    // Update the status in order_seller_details to 'cancelled'
    $sql_cancel = "UPDATE order_seller_details osd 
                   JOIN orders o ON osd.order_id = o.id
                   SET osd.status = 'cancelled'
                   WHERE osd.order_id = ? AND osd.seller_id = ? AND o.buyer_id = ? AND (osd.status = 'pending_payment' OR osd.status = 'cod')";
    $stmt_cancel = $conn->prepare($sql_cancel);
    $stmt_cancel->bind_param("iii", $order_id_to_cancel, $seller_id_to_cancel, $buyer_id);
    $stmt_cancel->execute();
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
            // --- NEW: Logic for overall status badge ---
            $sql_seller_statuses = "SELECT status FROM order_seller_details WHERE order_id = ?";
            $stmt_statuses = $conn->prepare($sql_seller_statuses);
            $stmt_statuses->bind_param("i", $order['id']);
            $stmt_statuses->execute();
            $statuses_result = $stmt_statuses->get_result();
            $statuses = [];
            while($row = $statuses_result->fetch_assoc()) {
                $statuses[] = $row['status'];
            }
            $stmt_statuses->close();

            $overall_status_class = 'bg-success';
            $overall_status_text = 'สำเร็จ';

            if (in_array('pending_payment', $statuses) || in_array('paid', $statuses) || in_array('cod', $statuses) || in_array('processing', $statuses) || in_array('preparing_shipment', $statuses)) {
                $overall_status_class = 'bg-info text-dark';
                $overall_status_text = 'กำลังดำเนินการ';
            }
            if (count(array_unique($statuses)) === 1 && $statuses[0] === 'cancelled') {
                $overall_status_class = 'bg-danger';
                $overall_status_text = 'ยกเลิกแล้ว';
            }
        ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">รหัสคำสั่งซื้อ #<?php echo $order['id']; ?></h5>
                    <span class="badge <?php echo $overall_status_class; ?>"><?php echo $overall_status_text; ?></span>
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
                    // --- UPDATED: Fetch seller-specific details from order_seller_details ---
                    $sql_items = "SELECT 
                                    osd.seller_id, 
                                    osd.subtotal, 
                                    osd.status, 
                                    osd.tracking_number,
                                    osd.payment_slip,
                                    u.username as seller_name, 
                                    u.payment_details, 
                                    u.payment_qr
                                  FROM order_seller_details osd
                                  JOIN users u ON osd.seller_id = u.id
                                  WHERE osd.order_id = ?";
                    $stmt_items = $conn->prepare($sql_items);
                    $stmt_items->bind_param("i", $order['id']);
                    $stmt_items->execute();
                    $items_result = $stmt_items->get_result();
                    
                    $sellers_in_order = [];
                    while($seller_row = $items_result->fetch_assoc()){
                        $sellers_in_order[$seller_row['seller_id']] = $seller_row;
                    }
                    $stmt_items->close();

                    foreach ($sellers_in_order as $seller_id => &$seller_data):
                        // Fetch items for this specific seller
                        $sql_seller_items = "SELECT oi.quantity, p.name as product_name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ? AND p.seller_id = ?";
                        $stmt_seller_items = $conn->prepare($sql_seller_items);
                        $stmt_seller_items->bind_param("ii", $order['id'], $seller_id);
                        $stmt_seller_items->execute();
                        $seller_items_result = $stmt_seller_items->get_result();
                        $seller_data['items'] = [];
                        while($item_row = $seller_items_result->fetch_assoc()){
                            $seller_data['items'][] = $item_row;
                        } // <-- FIX: Moved closing brace here
                        $stmt_seller_items->close(); 

                        // --- NEW: Status mapping for display ---
                        $status_map = [
                            'pending_payment' => ['class' => 'bg-warning text-dark', 'text' => 'รอชำระเงิน'],
                            'cod' => ['class' => 'bg-primary', 'text' => 'ชำระเงินปลายทาง'],
                            'paid' => ['class' => 'bg-info text-dark', 'text' => 'รอตรวจสอบ'],
                            'processing' => ['class' => 'bg-primary', 'text' => 'กำลังเตรียมจัดส่ง'],
                            'preparing_shipment' => ['class' => 'bg-primary', 'text' => 'กำลังเตรียมจัดส่ง'],
                            'shipped' => ['class' => 'bg-success', 'text' => 'จัดส่งแล้ว'],
                            'completed' => ['class' => 'bg-success', 'text' => 'สำเร็จ'],
                            'cancelled' => ['class' => 'bg-danger', 'text' => 'ยกเลิกแล้ว'],
                        ];
                        $status_display = $status_map[$seller_data['status']] ?? ['class' => 'bg-secondary', 'text' => htmlspecialchars($seller_data['status'])];
                    ?>
                        <div class="border-top mt-3 pt-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-0"><i class="bi bi-shop"></i> ร้านค้า: <?php echo htmlspecialchars($seller_data['seller_name']); ?></h6>
                                <span class="badge <?php echo $status_display['class']; ?>"><?php echo $status_display['text']; ?></span>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-7">
                                    <p class="mb-1"><strong>ยอดรวมร้านนี้:</strong> <span class="fw-bold text-danger">฿<?php echo number_format($seller_data['subtotal'], 2); ?></span></p>
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
                                <?php if ($seller_data['status'] == 'pending_payment'): ?>
                                    <div class="col-md-5 mt-3 mt-md-0">
                                        <strong>ข้อมูลการชำระเงิน:</strong>
                                        <?php if (!empty($seller_data['payment_details'])): ?>
                                            <div class="p-3 rounded mt-1" style="background-color: #eef2f7; border: 1px solid #d0d9e2;">
                                                <div style="white-space: pre-wrap;"><?php echo htmlspecialchars($seller_data['payment_details']); ?></div>
                                                <?php
                                                    $seller_qr_filename = $seller_data['payment_qr'];
                                                    $display_seller_qr_path = !empty($seller_qr_filename) ? "uploads/" . htmlspecialchars($seller_qr_filename) : 'https://via.placeholder.com/100?text=No+QR';
                                                ?>
                                                <img src="<?php echo $display_seller_qr_path; ?>" alt="QR Code" class="img-thumbnail mt-2" style="width:100px; height:100px; object-fit: cover;">
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning p-2 mt-1">ผู้ขายยังไม่ระบุข้อมูลการชำระเงิน</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($seller_data['status'] == 'pending_payment'): ?>
                                <div class="mt-3 d-flex justify-content-between align-items-start flex-wrap">
                                    <div class="me-3 mb-2">
                                        <h6><i class="bi bi-cash-coin"></i> แจ้งชำระเงินสำหรับร้านนี้</h6>
                                        <form action="my_orders_buyer.php" method="post" enctype="multipart/form-data" class="d-flex align-items-center">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <input type="hidden" name="seller_id" value="<?php echo $seller_id; ?>">
                                            <input type="file" name="payment_slip" class="form-control form-control-sm me-2" required>
                                            <button type="submit" class="btn btn-success btn-sm flex-shrink-0">อัปโหลด</button>
                                        </form>
                                    </div>
                                    <form action="my_orders_buyer.php" method="post" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการยกเลิกรายการจากร้านนี้?');">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <input type="hidden" name="seller_id" value="<?php echo $seller_id; ?>">
                                        <button type="submit" name="cancel_order" class="btn btn-danger btn-sm flex-shrink-0">ยกเลิก</button>
                                    </form>
                                </div>
                            <?php elseif ($seller_data['status'] == 'cod'): ?>
                                <div class="mt-3 d-flex justify-content-end">
                                     <form action="my_orders_buyer.php" method="post" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการยกเลิกรายการจากร้านนี้?');">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <input type="hidden" name="seller_id" value="<?php echo $seller_id; ?>">
                                        <button type="submit" name="cancel_order" class="btn btn-danger btn-sm flex-shrink-0">ยกเลิก</button>
                                    </form>
                                </div>
                            <?php elseif ($seller_data['status'] == 'shipped'): ?>
                                <p class="mt-3 text-success"><strong><i class="bi bi-truck"></i> สินค้าถูกจัดส่งแล้ว</strong></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
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