<?php
// --- Fixed: Start session only if not already started ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    $user_id = $_SESSION['id'];

    if ($_SESSION["user_type"] == 'buyer') {
        // Count unread 'shipped' notifications for the buyer
        $sql = "SELECT COUNT(osd.order_id) as noti_count 
                FROM order_seller_details osd
                JOIN orders o ON osd.order_id = o.id
                WHERE o.buyer_id = ? AND osd.status = 'shipped' AND osd.is_read_by_buyer = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $noti_count = $row['noti_count'] ?? 0;
        
    } else if ($_SESSION["user_type"] == 'seller') {
        // Count unread 'paid' or 'cod' notifications for the seller
        $sql = "SELECT COUNT(order_id) as noti_count 
                FROM order_seller_details 
                WHERE seller_id = ? AND (status = 'paid' OR status = 'cod') AND is_read_by_seller = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $noti_count = $row['noti_count'] ?? 0;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Marketplace</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-shop-window"></i> My Marketplace</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Left Aligned Nav -->
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">หน้าแรก</a>
                    </li>
                    <?php if (isset($_SESSION["user_type"]) && $_SESSION["user_type"] == 'seller'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="seller_dashboard.php">แดชบอร์ดผู้ขาย</a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <!-- Right Aligned Nav -->
                <ul class="navbar-nav align-items-center">
                    <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                        <?php if ($_SESSION["user_type"] == 'buyer'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="cart.php" title="ตะกร้าสินค้า">
                                    <i class="bi bi-cart-fill fs-5"></i>
                                    <?php echo isset($_SESSION['cart']) && count($_SESSION['cart']) > 0 ? '<span class="badge bg-danger rounded-pill ms-1">'.count($_SESSION['cart']).'</span>' : ''; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item ms-2">
                            <a class="nav-link position-relative" href="notification.php" title="การแจ้งเตือน">
                                <i class="bi bi-bell-fill fs-5"></i>
                                <?php if (isset($noti_count) && $noti_count > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7em; padding: .3em .5em;"><?php echo $noti_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item dropdown ms-2">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-person-circle fs-5"></i> <span class="d-none d-lg-inline">สวัสดี, <?php echo htmlspecialchars($_SESSION["username"]); ?></span></a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="edit_profile.php"><i class="bi bi-person-lines-fill me-2"></i>แก้ไขข้อมูลผู้ใช้งาน</a></li>
                                <?php if ($_SESSION["user_type"] == 'buyer'): ?>
                                    <li><a class="dropdown-item" href="my_orders_buyer.php"><i class="bi bi-box-seam-fill me-2"></i>คำสั่งซื้อของฉัน</a></li>
                                <?php endif; ?>
                                <?php if ($_SESSION["user_type"] == 'seller'): ?>
                                    <li><a class="dropdown-item" href="manage_orders_seller.php"><i class="bi bi-card-list me-2"></i>จัดการคำสั่งซื้อ</a></li>
                                    <li><a class="dropdown-item" href="seller_profile.php"><i class="bi bi-gear-fill me-2"></i>ตั้งค่าร้านค้า</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">เข้าสู่ระบบ</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">สมัครสมาชิก</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container my-5">
