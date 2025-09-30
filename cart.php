<?php
require_once 'header.php';

// ดึงข้อมูลสินค้าจาก session cart
$cart_items = [];
$total_price = 0;

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    // สร้าง placeholder สำหรับ bind_param
    $placeholders = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
    
    $sql = "SELECT * FROM products WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);

    // สร้าง array ของ types 'i' สำหรับ bind_param
    $types = str_repeat('i', count($_SESSION['cart']));
    $product_ids = array_keys($_SESSION['cart']);
    $stmt->bind_param($types, ...$product_ids);
    
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $quantity = $_SESSION['cart'][$row['id']];
        $row['quantity'] = $quantity;
        $row['subtotal'] = $row['price'] * $quantity;
        $cart_items[] = $row;
        $total_price += $row['subtotal'];
    }
}
?>

<div class="card p-4 mx-auto" style="max-width: 900px;">
    <h1 class="text-center mb-4">ตะกร้าสินค้า</h1>
    
    <?php if (empty($cart_items)): ?>
        <div class="alert alert-info text-center">
            <p class="fs-4 mb-2"><i class="bi bi-cart-x-fill"></i></p>
            <p class="fs-5">ตะกร้าสินค้าของคุณว่างเปล่า</p>
            <a href="index.php" class="btn btn-primary">ไปเลือกซื้อสินค้า</a>
        </div>
    <?php else: ?>
        <table class="table align-middle">
            <thead>
                <tr>
                    <th style="width: 50%;">สินค้า</th>
                    <th class="text-end">ราคา</th>
                    <th class="text-center">จำนวน</th>
                    <th class="text-end">ราคารวม</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cart_items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td class="text-end">฿<?php echo number_format($item['price'], 2); ?></td>
                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                        <td class="text-end">฿<?php echo number_format($item['subtotal'], 2); ?></td>
                        <td>
                            <a href="cart_action.php?action=remove&product_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <hr>
        
        <div class="text-end">
            <h3 class="fw-bold">ยอดรวมทั้งหมด: <span class="text-danger">฿<?php echo number_format($total_price, 2); ?></span></h3>
        </div>

        <!-- Check if user is logged in -->
        <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
            <div class="d-flex justify-content-end mt-4">
                 <a href="index.php" class="btn btn-outline-secondary me-2">เลือกซื้อสินค้าต่อ</a>
                <a href="checkout.php" class="btn btn-success btn-lg">ดำเนินการสั่งซื้อ <i class="bi bi-arrow-right"></i></a>
            </div>
        <?php else: ?>
            <div class="alert alert-warning mt-4 text-center">
                <i class="bi bi-exclamation-triangle-fill"></i> <strong>กรุณาเข้าสู่ระบบ</strong> เพื่อดำเนินการสั่งซื้อสินค้า
            </div>
             <div class="d-flex justify-content-end mt-2">
                <a href="index.php" class="btn btn-outline-secondary me-2">เลือกซื้อสินค้าต่อ</a>
                <a href="login.php" class="btn btn-primary btn-lg">ไปหน้าเข้าสู่ระบบ</a>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>

