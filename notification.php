<?php
require_once 'config.php'; 

$user_id = $_SESSION['id'];
$user_type = $_SESSION['user_type'];

if ($user_type == 'buyer') {
    // Fetch notifications for shipped items
    $sql = "SELECT 
                o.id as order_id, o.created_at,
                osd.subtotal, osd.tracking_number,
                u.username as seller_name
            FROM order_seller_details osd
            JOIN orders o ON osd.order_id = o.id
            JOIN users u ON osd.seller_id = u.id
            WHERE o.buyer_id = ? AND osd.status = 'shipped' AND osd.is_read_by_buyer = 0
            ORDER BY o.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notifications = $stmt->get_result();

    // Mark notifications as read for buyer
    $update_sql = "UPDATE order_seller_details osd
                   JOIN orders o ON osd.order_id = o.id
                   SET osd.is_read_by_buyer = 1 
                   WHERE o.buyer_id = ? AND osd.status = 'shipped' AND osd.is_read_by_buyer = 0";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $user_id);
    $update_stmt->execute();

} else if ($user_type == 'seller') {
    // Fetch notifications for new paid or COD orders
    $sql = "SELECT 
                o.id as order_id, o.shipping_address, o.created_at,
                osd.subtotal, osd.status,
                u.username as buyer_name
            FROM order_seller_details osd
            JOIN orders o ON osd.order_id = o.id
            JOIN users u ON o.buyer_id = u.id
            WHERE osd.seller_id = ? AND (osd.status = 'paid' OR osd.status = 'cod') AND osd.is_read_by_seller = 0
            ORDER BY o.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notifications = $stmt->get_result();

    // Mark notifications as read for seller
    $update_sql = "UPDATE order_seller_details 
                   SET is_read_by_seller = 1 
                   WHERE seller_id = ? AND (status = 'paid' OR status = 'cod') AND is_read_by_seller = 0";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $user_id);
    $update_stmt->execute();
}

require_once 'header.php';
?>

<div class="container mt-4">
    <h2 class="text-center mb-4"><i class="bi bi-bell"></i> การแจ้งเตือน</h2>

<?php if (isset($notifications) && $notifications->num_rows > 0): ?>
    <div class="list-group">
    <?php while($noti = $notifications->fetch_assoc()): ?>
        <?php if ($user_type == 'seller'): ?>
            <?php 
                $icon = $noti['status'] == 'cod' ? 'bi-box-seam' : 'bi-credit-card';
                $title = $noti['status'] == 'cod' ? 'มีคำสั่งซื้อใหม่ (COD)' : 'ผู้ซื้อแจ้งชำระเงินแล้ว';
                $body = "จากคุณ: " . htmlspecialchars($noti['buyer_name']) . " (ยอด: " . number_format($noti['subtotal'], 2) . " บาท)";
            ?>
            <a href="manage_orders_seller.php" class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between">
                    <h5 class="mb-1"><i class="bi <?php echo $icon; ?> me-2"></i><?php echo $title; ?> - Order #<?php echo $noti['order_id']; ?></h5>
                    <small><?php echo date('d/m/Y H:i', strtotime($noti['created_at'])); ?></small>
                </div>
                <p class="mb-1"><?php echo $body; ?></p>
                <small>คลิกเพื่อไปที่หน้าจัดการคำสั่งซื้อ</small>
            </a>
        <?php elseif ($user_type == 'buyer'): ?>
            <?php
                $title = "สินค้าถูกจัดส่งแล้ว - Order #" . $noti['order_id'];
                $body = "ร้านค้า " . htmlspecialchars($noti['seller_name']) . " ได้จัดส่งสินค้าให้คุณแล้ว";
            ?>
            <a href="my_orders_buyer.php" class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between">
                    <h5 class="mb-1"><i class="bi bi-truck me-2"></i><?php echo $title; ?></h5>
                    <small><?php echo date('d/m/Y H:i', strtotime($noti['created_at'])); ?></small>
                </div>
                <p class="mb-1"><?php echo $body; ?></p>
                <small>คลิกเพื่อไปที่หน้าคำสั่งซื้อของฉัน</small>
            </a>
        <?php endif; ?>
    <?php endwhile; ?>
    </div>
<?php else: ?>
    <div class="alert alert-info text-center">
        <i class="bi bi-info-circle-fill me-2"></i>ไม่มีการแจ้งเตือนใหม่
        <a href="index.php" class="btn btn-primary">กลับสู่หน้าแรก</a>
    </div>
<?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>