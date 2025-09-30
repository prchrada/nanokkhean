<?php
require_once 'header.php';
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] != 'seller') {
    header("location: login.php");
    exit;
}

$seller_id = $_SESSION['id'];

// --- NEW: Fetch all products into an array to calculate stats ---
$sql = "SELECT * FROM products WHERE seller_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);

// --- NEW: Calculate dashboard stats ---
$total_products = count($products);
$low_stock_count = 0;
$low_stock_threshold = 5; // กำหนดเกณฑ์สินค้าใกล้หมดที่ 5 ชิ้น

foreach ($products as $product) {
    if ($product['stock_quantity'] > 0 && $product['stock_quantity'] <= $low_stock_threshold) {
        $low_stock_count++;
    }
}

// --- NEW: Query for pending orders count ---
$sql_orders = "SELECT COUNT(order_id) as pending_orders_count 
               FROM order_seller_details 
               WHERE seller_id = ? AND (status = 'paid' OR status = 'cod')";
$stmt_orders = $conn->prepare($sql_orders);
$stmt_orders->bind_param("i", $seller_id);
$stmt_orders->execute();
$orders_result = $stmt_orders->get_result()->fetch_assoc();
$pending_orders_count = $orders_result['pending_orders_count'] ?? 0;

?>

<div class="row justify-content-center">
    <div class="col-lg-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0"><i class="bi bi-shop me-2"></i>จัดการร้านค้า</h1>
            <a href="add_product.php" class="btn btn-success flex-shrink-0"><i class="bi bi-plus-circle-fill me-2"></i> เพิ่มสินค้าใหม่</a>
        </div>

        <!-- NEW: Dashboard Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-primary h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fs-4 fw-bold"><?php echo $total_products; ?></div>
                            <div>สินค้าทั้งหมด</div>
                        </div>
                        <i class="bi bi-box-seam-fill fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-warning h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fs-4 fw-bold"><?php echo $low_stock_count; ?></div>
                            <div>สินค้าใกล้หมด</div>
                        </div>
                        <i class="bi bi-exclamation-triangle-fill fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <a href="manage_orders_seller.php" class="text-decoration-none">
                    <div class="card text-white bg-info h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fs-4 fw-bold"><?php echo $pending_orders_count; ?></div>
                                <div>คำสั่งซื้อที่ต้องจัดการ</div>
                            </div>
                            <i class="bi bi-card-list fs-1 opacity-50"></i>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-bold">
                สินค้าของคุณ
            </div>
            <div class="card-body">
                <?php if (isset($_GET['status']) && $_GET['status'] == 'deleted'): ?>
                    <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>ลบสินค้าเรียบร้อยแล้ว</div>
                <?php endif; ?>
                <?php if (isset($_GET['status']) && $_GET['status'] == 'updated'): ?>
                    <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>อัปเดตข้อมูลสินค้าเรียบร้อยแล้ว</div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width: 120px;">รูปภาพ</th>
                                <th style="width: 40%;">ชื่อสินค้า</th>
                                <th>ราคา</th>
                                <th>สต็อก</th>
                                <th style="width: 120px;">วันที่ลง</th>
                                <th class="text-center text-nowrap">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_products > 0): ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" class="rounded shadow-sm" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100px; height: 100px; object-fit: cover;"></td>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td>฿<?php echo number_format($product['price'], 2); ?></td>
                                        <td>
                                            <?php if ($product['stock_quantity'] == 0): ?>
                                                <span class="badge bg-danger">สินค้าหมด</span>
                                            <?php elseif ($product['stock_quantity'] <= $low_stock_threshold): ?>
                                                <span class="badge bg-warning text-dark"><?php echo $product['stock_quantity']; ?> ชิ้น</span>
                                            <?php else: ?>
                                                <?php echo $product['stock_quantity']; ?> ชิ้น
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap"><?php echo date('d/m/Y', strtotime($product['created_at'])); ?></td>
                                        <td class="text-center text-nowrap">
                                            <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-warning btn-sm" title="แก้ไข">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <a href="delete_product.php?id=<?php echo $product['id']; ?>" class="btn btn-danger btn-sm" title="ลบ" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบสินค้านี้?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <div class="alert alert-info mt-3 mb-3" role="alert">
                                            <i class="bi bi-info-circle-fill me-2"></i>คุณยังไม่มีสินค้าในร้านค้า
                                            <br>
                                            <a href="add_product.php" class="btn btn-primary btn-sm mt-3"><i class="bi bi-plus-circle-fill me-2"></i>เพิ่มสินค้าใหม่</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
