<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] != 'seller') {
    header("location: login.php");
    exit;
}

$product_id = $_GET['id'] ?? null;
if (!$product_id) {
    header("location: seller_dashboard.php");
    exit;
}

// Fetch product details and verify ownership
$sql = "SELECT * FROM products WHERE id = ? AND seller_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $product_id, $_SESSION['id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows != 1) {
    // Product not found or doesn't belong to this seller
    header("location: seller_dashboard.php");
    exit;
}
$product = $result->fetch_assoc();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $price = trim($_POST["price"]);
    $stock = trim($_POST["stock_quantity"]);
    
    $sql_update = "UPDATE products SET name = ?, description = ?, price = ?, stock_quantity = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ssdii", $name, $description, $price, $stock, $product_id);
    
    if ($stmt_update->execute()) {
        header("location: seller_dashboard.php?status=updated");
        exit;
    }
}

require_once 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>แก้ไขสินค้า</h3>
            </div>
            <div class="card-body">
                <form action="edit_product.php?id=<?php echo $product['id']; ?>" method="post">
                    <div class="mb-3">
                        <label for="name" class="form-label">ชื่อสินค้า</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">คำอธิบาย</label>
                        <textarea name="description" class="form-control" rows="5" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">ราคา</label>
                        <input type="number" name="price" step="0.01" class="form-control" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="stock_quantity" class="form-label">จำนวนสต็อก</label>
                        <input type="number" name="stock_quantity" class="form-control" value="<?php echo htmlspecialchars($product['stock_quantity']); ?>" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">บันทึกการเปลี่ยนแปลง</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
