<?php
require_once 'config.php'; // Use config for session and DB connection
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] != 'seller') { header("location: login.php"); exit; }

// --- ACTION HANDLERS ---

// Handle Confirm Payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_payment'])) {
    $order_id = $_POST['order_id'];
    
    // Security check: ensure this seller is associated with this order
    $verify_sql = "SELECT o.id FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE p.seller_id = ? AND o.id = ?";
    $stmt = $conn->prepare($verify_sql);
    $stmt->bind_param("ii", $_SESSION['id'], $order_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $update_sql = "UPDATE orders SET order_status = 'preparing_shipment', status = 'preparing_shipment' WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $order_id);
        $update_stmt->execute();
    }
}

// Handle Tracking Number Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tracking_number'])) {
    $order_id = $_POST['order_id'];
    $tracking_number = trim($_POST['tracking_number']);
    
    $verify_sql = "SELECT o.id FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE p.seller_id = ? AND o.id = ?";
    $stmt = $conn->prepare($verify_sql);
    $stmt->bind_param("ii", $_SESSION['id'], $order_id);
    $stmt->execute();
    if($stmt->get_result()->num_rows > 0){
        $sql = "UPDATE orders SET tracking_number = ?, status = 'shipped', order_status = 'shipped', is_read_by_buyer = 0 WHERE id = ?";
        $stmt_update = $conn->prepare($sql);
        $stmt_update->bind_param("si", $tracking_number, $order_id);
        $stmt_update->execute();

        $update_sql = "UPDATE products p JOIN order_items oi ON p.id = oi.product_id SET p.stock_quantity = p.stock_quantity - oi.quantity WHERE oi.order_id = ? AND p.seller_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $order_id, $_SESSION['id']);
        $update_stmt->execute();
    }
        // Update stock quantities
    
}


// --- DATA FETCHING ---
$sql = "SELECT DISTINCT o.* FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE p.seller_id = ? ORDER BY o.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$orders = $stmt->get_result();

// Function to get badge color based on status
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'processing': return 'bg-warning text-dark';
        case 'preparing_shipment': return 'bg-info text-dark';
        case 'shipped': return 'bg-success';
        case 'pending_payment': return 'bg-secondary';
        default: return 'bg-light text-dark';
    }
}

// All logic is done, now we can include the header and start the HTML output
require_once 'header.php';
?>

<h1>จัดการคำสั่งซื้อ</h1>
<?php while($order = $orders->fetch_assoc()): ?>
<div class="card mb-3">
     <div class="card-header d-flex justify-content-between align-items-center">
        <span>รหัสคำสั่งซื้อ #<?php echo $order['id']; ?></span>
        <span class="badge <?php echo getStatusBadgeClass($order['order_status']); ?>"><?php echo htmlspecialchars($order['order_status']); ?></span>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h5>รายละเอียดคำสั่งซื้อ</h5>
                <p><strong>วันที่สั่ง:</strong> <?php echo $order['created_at']; ?></p>
                <p><strong>ยอดรวม:</strong> ฿<?php echo number_format($order['total_price'], 2); ?></p>
                <?php if (!empty($order['payment_slip'])): ?>
                    <p><strong>สลิปชำระเงิน:</strong> <a href="slips/<?php echo $order['payment_slip']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">ดูสลิป</a></p>
                <?php else: ?>
                    <p><strong>สลิปชำระเงิน:</strong> <span class="text-muted">ผู้ซื้อยังไม่แจ้งชำระเงิน</span></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h5>ที่อยู่สำหรับจัดส่ง</h5>
                <p style="white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
            </div>
        </div>
        
        <?php if ($order['order_status'] == 'processing'): ?>
            <hr>
            <form action="" method="post" class="text-end">
                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                <button type="submit" name="confirm_payment" class="btn btn-success">ยืนยันการชำระเงิน</button>
            </form>
        <?php elseif ($order['order_status'] == 'preparing_shipment'): ?>
             <hr>
            <h5>กรอกเลขพัสดุ</h5>
            <form action="" method="post">
                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                <div class="input-group">
                    <input type="text" name="tracking_number" class="form-control" placeholder="กรอกเลขพัสดุที่นี่" required>
                    <button type="submit" class="btn btn-primary">ยืนยันการจัดส่ง</button>
                </div>
            </form>
        <?php elseif ($order['order_status'] == 'shipped'): ?>
             <hr>
            <p class="fw-bold text-success"><strong>เลขพัสดุที่จัดส่งแล้ว:</strong> <?php echo htmlspecialchars($order['tracking_number']); ?></p>
        <?php endif; ?>
    </div>
</div>
<?php endwhile; ?>

<?php
if ($orders->num_rows == 0) {
    echo '<div class="alert alert-info">ยังไม่มีคำสั่งซื้อเข้ามา</div>';
}
?>

<?php require_once 'footer.php'; ?>
