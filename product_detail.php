<?php
require_once 'header.php';

$product_id = $_GET['id'] ?? null;
if (!$product_id) {
    header("location: index.php");
    exit;
}

$sql = "SELECT p.*, u.username as seller_name FROM products p JOIN users u ON p.seller_id = u.id WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    echo "<div class='alert alert-danger'>ไม่พบสินค้านี้</div>";
    require_once 'footer.php';
    exit;
}
$product = $result->fetch_assoc();
?>

<div class="card p-4">
    <div class="row g-5">
        <!-- ส่วนรูปภาพสินค้าหลัก -->
        <div class="col-lg-6">
            <img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" class="img-fluid rounded shadow-lg product-detail-main-img" alt="<?php echo htmlspecialchars($product['name']); ?>">
        </div>

        <!-- ส่วนรายละเอียดสินค้า -->
        <div class="col-lg-6 d-flex flex-column">
            <h1 class="fw-bold display-5"><?php echo htmlspecialchars($product['name']); ?></h1>
            <p class="text-muted fs-5">ขายโดย: <span class="fw-bold text-primary"><?php echo htmlspecialchars($product['seller_name']); ?></span></p>
            
            <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded mb-3">
                <h2 class="text-danger fw-bolder m-0">฿<?php echo number_format($product['price'], 2); ?></h2>
                <span class="badge bg-success p-2 fs-6">
                    <i class="bi bi-box-seam"></i> คงเหลือ: <?php echo $product['stock_quantity']; ?> ชิ้น
                </span>
            </div>

            <h5 class="mt-3">คำอธิบายสินค้า</h5>
            <p class="text-secondary" style="white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            
            <div class="mt-auto">
                <?php
                $is_any_seller = (isset($_SESSION['loggedin']) && $_SESSION['user_type'] == 'seller');
                ?>

                <?php if ($is_any_seller): ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong>คุณอยู่ในระบบผู้ขาย</strong> จึงไม่สามารถสั่งซื้อสินค้าได้
                    </div>
                <?php elseif ($product['stock_quantity'] > 0): ?>
                    <form action="cart_action.php?action=add" method="post" class="mt-3">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <div class="mb-3">
                            <label for="quantity" class="form-label"><strong>จำนวน:</strong></label>
                            <input type="number" id="quantity" name="quantity" class="form-control form-control-lg" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>" style="max-width: 150px;">
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg fw-bold shadow-sm"><i class="bi bi-cart-plus-fill me-2"></i> เพิ่มลงตะกร้า</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="d-grid">
                        <button class="btn btn-secondary btn-lg" disabled>สินค้าหมด</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Add a simple zoom effect on image hover
    const productImage = document.querySelector('.img-fluid');
    productImage.addEventListener('mouseover', () => {
        productImage.style.transform = 'scale(1.03)';
    });
    productImage.addEventListener('mouseout', () => {
        productImage.style.transform = 'scale(1)';
    });
</script>

<?php require_once 'footer.php'; ?>
