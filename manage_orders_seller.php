<?php
require_once 'config.php';

// ตรวจสอบว่าเป็น seller หรือไม่
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] != 'seller') {
    header("location: index.php");
    exit;
}

$seller_id = $_SESSION['id'];

// --- Logic to handle form submissions ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $order_id = $_POST['order_id'];
    $action = $_POST['action'];

    // Action: Confirm Payment
    if ($action == 'confirm_payment') {
        $sql = "UPDATE order_seller_details SET status = 'processing', is_read_by_seller = 1 WHERE order_id = ? AND seller_id = ? AND status = 'paid'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $order_id, $seller_id);
        $stmt->execute();
    }

    // Action: Ship Order (and add tracking number)
    if ($action == 'ship_order') {
        $sql = "UPDATE order_seller_details SET status = 'shipped', is_read_by_buyer = 0, is_read_by_seller = 1 WHERE order_id = ? AND seller_id = ? AND (status = 'processing' OR status = 'cod')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $order_id, $seller_id);
        $stmt->execute();
    }

    // Action: Mark as read (for new COD orders)
    if ($action == 'confirm_cod') {
        $sql = "UPDATE order_seller_details SET status = 'processing', is_read_by_seller = 1 WHERE order_id = ? AND seller_id = ? AND status = 'cod'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $order_id, $seller_id);
        $stmt->execute();
    }

    // Redirect to avoid form resubmission
    header("location: manage_orders_seller.php");
    exit;
}

// --- Fetch orders for this seller ---
$sql_orders = "SELECT 
                    o.id as order_id,
                    o.shipping_address,
                    o.created_at,
                    osd.subtotal,
                    osd.status,
                    osd.payment_slip,
                    osd.tracking_number,
                    u.username as buyer_name
                FROM orders o
                JOIN order_seller_details osd ON o.id = osd.order_id
                JOIN users u ON o.buyer_id = u.id
                WHERE osd.seller_id = ?
                ORDER BY o.created_at DESC";

$stmt_orders = $conn->prepare($sql_orders);
$stmt_orders->bind_param("i", $seller_id);
$stmt_orders->execute();
$orders_result = $stmt_orders->get_result();

$orders_data = [];
while ($row = $orders_result->fetch_assoc()) {
    $orders_data[] = $row;
}
$stmt_orders->close();

require_once 'header.php';
?>

<div class="container mt-4">
    <h2 class="text-center mb-4"><i class="bi bi-card-list"></i> จัดการคำสั่งซื้อ</h2>

    <?php if (count($orders_data) > 0): ?>
        <?php foreach ($orders_data as $order): 
            // --- Status mapping for display ---
            $status_map = [
                'pending_payment' => ['class' => 'bg-secondary', 'text' => 'รอชำระเงิน'],
                'cod' => ['class' => 'bg-primary', 'text' => 'ใหม่ (COD)'],
                'paid' => ['class' => 'bg-warning text-dark', 'text' => 'รอตรวจสอบ'],
                'processing' => ['class' => 'bg-info text-dark', 'text' => 'กำลังเตรียมจัดส่ง'],
                'shipped' => ['class' => 'bg-success', 'text' => 'จัดส่งแล้ว'],
                'completed' => ['class' => 'bg-success', 'text' => 'สำเร็จ'],
                'cancelled' => ['class' => 'bg-danger', 'text' => 'ยกเลิกแล้ว'],
            ];
            $status_display = $status_map[$order['status']] ?? ['class' => 'bg-light text-dark', 'text' => htmlspecialchars($order['status'])];
        ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">รหัสคำสั่งซื้อ #<?php echo $order['order_id']; ?></h5>
                    <span class="badge <?php echo $status_display['class']; ?>"><?php echo $status_display['text']; ?></span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-person-circle"></i> ข้อมูลผู้ซื้อ</h6>
                            <p class="mb-1"><strong>ชื่อ:</strong> <?php echo htmlspecialchars($order['buyer_name']); ?></p>
                            <p class="mb-1"><strong>วันที่สั่ง:</strong> <?php echo $order['created_at']; ?></p>
                            <p class="mb-1"><strong>ยอดรวม (ร้านของคุณ):</strong> <span class="fw-bold text-danger">฿<?php echo number_format($order['subtotal'], 2); ?></span></p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-truck"></i> ที่อยู่สำหรับจัดส่ง</h6>
                            <p class="mb-1" style="white-space: pre-wrap;"><?php echo htmlspecialchars($order['shipping_address']); ?></p>
                        </div>
                    </div>

                    <div class="border-top mt-3 pt-3">
                        <h6><i class="bi bi-box-seam"></i> รายการสินค้า</h6>
                        <ul class="list-group list-group-flush">
                            <?php
                            // Fetch items for this specific order part
                            $sql_items = "SELECT oi.quantity, p.name as product_name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ? AND p.seller_id = ?";
                            $stmt_items = $conn->prepare($sql_items);
                            $stmt_items->bind_param("ii", $order['order_id'], $seller_id);
                            $stmt_items->execute();
                            $items_result = $stmt_items->get_result();
                            while($item = $items_result->fetch_assoc()):
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center ps-0">
                                <span><?php echo htmlspecialchars($item['product_name']); ?></span>
                                <span class="badge bg-secondary rounded-pill">จำนวน: <?php echo $item['quantity']; ?></span>
                            </li>
                            <?php endwhile; $stmt_items->close(); ?>
                        </ul>
                    </div>

                    <!-- Actions for Seller -->
                    <div class="border-top mt-3 pt-3">
                        <h6 class="fw-bold">จัดการคำสั่งซื้อ</h6>
                        <?php if ($order['status'] == 'paid'): ?>
                            <div class="d-flex align-items-center">
                                <?php if (!empty($order['payment_slip'])): ?>
                                    <a href="slips/<?php echo htmlspecialchars($order['payment_slip']); ?>" target="_blank" class="btn btn-outline-primary btn-sm me-3">
                                        <i class="bi bi-receipt"></i> ดูสลิป
                                    </a>
                                <?php endif; ?>
                                <form action="manage_orders_seller.php" method="post" onsubmit="return confirm('ยืนยันการชำระเงินและเตรียมจัดส่ง?');">
                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                    <input type="hidden" name="action" value="confirm_payment">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="bi bi-check-circle-fill"></i> ยืนยันการชำระเงิน
                                    </button>
                                </form>
                            </div>
                        <?php elseif ($order['status'] == 'cod'): ?>
                             <form action="manage_orders_seller.php" method="post" onsubmit="return confirm('ยืนยันรับออเดอร์และเตรียมจัดส่ง?');">
                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                <input type="hidden" name="action" value="confirm_cod">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="bi bi-box-arrow-in-right"></i> ยืนยันและเตรียมจัดส่ง (COD)
                                </button>
                            </form>
                        <?php elseif ($order['status'] == 'processing'): ?>
                            <form action="manage_orders_seller.php" method="post" onsubmit="return confirm('ยืนยันการจัดส่งสินค้าสำหรับออเดอร์นี้?');">
                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                <input type="hidden" name="action" value="ship_order">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-send-fill"></i> ยืนยันการจัดส่ง
                                </button>
                            </form>
                        <?php elseif ($order['status'] == 'shipped'): ?>
                            <p class="text-success mb-0"><i class="bi bi-check-circle-fill"></i> จัดส่งสินค้าเรียบร้อยแล้ว</p>
                        <?php elseif ($order['status'] == 'cancelled'): ?>
                            <p class="text-danger mb-0"><i class="bi bi-x-circle-fill"></i> รายการนี้ถูกยกเลิกโดยผู้ซื้อ</p>
                        <?php else: ?>
                            <p class="text-muted mb-0">ไม่มีรายการที่ต้องจัดการในขณะนี้</p>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="text-center">
            <p>ยังไม่มีคำสั่งซื้อเข้ามา</p>
            <a href="seller_dashboard.php" class="btn btn-primary">กลับสู่แดชบอร์ด</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
