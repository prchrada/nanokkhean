<?php
// --- PART 1: LOGIC ONLY (NO HTML OUTPUT) ---
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบว่า login หรือยัง และเป็น seller หรือไม่
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] != 'seller') {
    header("location: login.php");
    exit;
}

$name = $description = $price = $stock_quantity = "";
$name_err = $price_err = $stock_err = $image_err = "";

// ตรวจสอบและบันทึกข้อมูลเมื่อมีการ POST form
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate name
    if (empty(trim($_POST["name"]))) {
        $name_err = "กรุณาใส่ชื่อสินค้า";
    } else {
        $name = trim($_POST["name"]);
    }

    // Validate description
    $description = trim($_POST["description"]);

    // Validate price
    if (empty(trim($_POST["price"]))) {
        $price_err = "กรุณาใส่ราคาสินค้า";
    } elseif (!is_numeric($_POST["price"])) {
        $price_err = "ราคาต้องเป็นตัวเลขเท่านั้น";
    } else {
        $price = trim($_POST["price"]);
    }

    // Validate stock quantity
    if (empty(trim($_POST["stock_quantity"]))) {
        $stock_err = "กรุณาใส่จำนวนสต็อก";
    } elseif (!ctype_digit($_POST["stock_quantity"])) {
        $stock_err = "จำนวนสต็อกต้องเป็นตัวเลขจำนวนเต็มเท่านั้น";
    } else {
        $stock_quantity = trim($_POST["stock_quantity"]);
    }

    // Validate and process image upload
    $target_dir = "uploads/";
    $image_name = "";
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $target_file = $target_dir . basename($_FILES["image"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $new_filename = time() . "_" . uniqid() . "." . $imageFileType;
        $target_path = $target_dir . $new_filename;
        
        // Check if image file is a actual image or fake image
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if ($check !== false) {
            // Check file size (e.g., 5MB max)
            if ($_FILES["image"]["size"] > 5000000) {
                $image_err = "ขออภัย, ไฟล์ของคุณใหญ่เกินไป";
            } else {
                // Allow certain file formats
                if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
                    $image_err = "ขออภัย, อนุญาตเฉพาะไฟล์ JPG, JPEG, PNG & GIF เท่านั้น";
                } else {
                    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_path)) {
                        $image_name = $new_filename;
                    } else {
                        $image_err = "ขออภัย, เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
                    }
                }
            }
        } else {
            $image_err = "ไฟล์ที่อัปโหลดไม่ใช่รูปภาพ";
        }
    } else {
        $image_err = "กรุณาเลือกรูปภาพสินค้า";
    }


    // Check input errors before inserting in database
    if (empty($name_err) && empty($price_err) && empty($stock_err) && empty($image_err)) {
        $sql = "INSERT INTO products (name, description, price, stock_quantity, image, seller_id) VALUES (?, ?, ?, ?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssdisi", $param_name, $param_description, $param_price, $param_stock, $param_image, $param_seller_id);
            $param_name = $name;
            $param_description = $description;
            $param_price = $price;
            $param_stock = $stock_quantity;
            $param_image = $image_name;
            $param_seller_id = $_SESSION['id'];

            if ($stmt->execute()) {
                header("location: seller_dashboard.php");
                exit();
            } else {
                echo "มีบางอย่างผิดพลาด กรุณาลองใหม่อีกครั้ง";
            }
            $stmt->close();
        }
    }
}

// --- PART 2: DISPLAY (HTML OUTPUT) ---
require_once 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0"><i class="bi bi-plus-circle-fill me-2"></i>เพิ่มสินค้าใหม่</h2>
            </div>
            <div class="card-body">
                <form action="add_product.php" method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-bold">ชื่อสินค้า</label>
                        <input type="text" id="name" name="name" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($name); ?>" placeholder="เช่น เสื้อยืดลายกราฟิก">
                        <span class="invalid-feedback"><?php echo $name_err; ?></span>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label fw-bold">คำอธิบายสินค้า</label>
                        <textarea id="description" name="description" class="form-control" rows="4" placeholder="บอกรายละเอียดเกี่ยวกับสินค้าของคุณ เช่น วัสดุ, ขนาด, คุณสมบัติเด่น"><?php echo htmlspecialchars($description); ?></textarea>
                        <div class="form-text">คุณสามารถจัดรูปแบบข้อความได้โดยการขึ้นบรรทัดใหม่</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="price" class="form-label fw-bold">ราคา</label>
                            <div class="input-group">
                                <span class="input-group-text">฿</span>
                                <input type="number" id="price" name="price" class="form-control <?php echo (!empty($price_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($price); ?>" placeholder="0.00" step="0.01" min="0">
                                <span class="invalid-feedback"><?php echo $price_err; ?></span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="stock_quantity" class="form-label fw-bold">จำนวนสต็อก</label>
                             <div class="input-group">
                                <input type="number" id="stock_quantity" name="stock_quantity" class="form-control <?php echo (!empty($stock_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($stock_quantity); ?>" placeholder="0" min="0">
                                <span class="input-group-text">ชิ้น</span>
                                <span class="invalid-feedback"><?php echo $stock_err; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="image" class="form-label fw-bold">รูปภาพสินค้า</label>
                        <input type="file" id="imageInput" name="image" class="form-control <?php echo (!empty($image_err)) ? 'is-invalid' : ''; ?>">
                        <div class="form-text">แนะนำ: รูปภาพสี่เหลี่ยมจัตุรัส, ขนาดไม่เกิน 5MB</div>
                        <span class="invalid-feedback"><?php echo $image_err; ?></span>
                        <img id="preview" src="#" alt="ตัวอย่างรูปภาพ" class="img-thumbnail mt-3" style="max-width:200px; display: none;">
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg fw-bold"><i class="bi bi-check-circle-fill me-2"></i>บันทึกและเพิ่มสินค้า</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
  document.getElementById("imageInput").addEventListener("change", function(event) {
    const file = event.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function(e) {
        const preview = document.getElementById("preview");
        preview.src = e.target.result;
        preview.style.display = "block";
      }
      reader.readAsDataURL(file);
    }
  });
</script>

<?php require_once 'footer.php'; ?>
