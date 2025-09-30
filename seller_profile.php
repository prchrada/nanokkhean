<?php
require_once 'header.php';

// ตรวจสอบว่าเป็น seller หรือไม่
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] != 'seller') {
    header("location: index.php");
    exit;
}

$seller_id = $_SESSION['id'];
$line_user_id = '';
$payment_details = '';
$current_payment_qr_filename = ''; // Initialize to empty string
$update_success = false; // Initialize success flag

// ดึงข้อมูลปัจจุบัน
$stmt_select = $conn->prepare("SELECT line_user_id, payment_details, payment_qr FROM users WHERE id = ?");
$stmt_select->bind_param("i", $seller_id);
$stmt_select->execute();
$result = $stmt_select->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $line_user_id = $row['line_user_id'];
    $payment_details = $row['payment_details'];
    $current_payment_qr_filename = $row['payment_qr']; // Store just the filename
}
$stmt_select->close();

// For displaying the image in the form
$display_payment_qr_path = !empty($current_payment_qr_filename) ? "uploads/" . htmlspecialchars($current_payment_qr_filename) : 'https://via.placeholder.com/200?text=No+QR';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // อัปเดตข้อมูล
    $new_line_user_id = trim($_POST['line_user_id']);
    $new_payment_details = trim($_POST['payment_details']);
    $target_dir = "uploads/";
    $image_name = $_POST['old_payment_qr_filename'] ?? ''; // Initialize with old filename from hidden input
    if (isset($_FILES["image"]) && !empty($_FILES["image"]["name"]) && $_FILES["image"]["error"] == 0) {
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
                        // Delete old image if it exists and is different from the new one
                        if (!empty($_POST['old_payment_qr_filename']) && file_exists($target_dir . $_POST['old_payment_qr_filename'])) {
                            unlink($target_dir . $_POST['old_payment_qr_filename']);
                        }
                    } else {
                        $image_err = "ขออภัย, เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
                    }
                }
            }
        } else {
            $image_err = "ไฟล์ที่อัปโหลดไม่ใช่รูปภาพ";
        }
    }

    if (empty($image_err)) { // Only proceed if there's no image upload error
        $stmt_update = $conn->prepare("UPDATE users SET line_user_id = ?, payment_details = ?, payment_qr = ? WHERE id = ?");
        $stmt_update->bind_param("sssi", $new_line_user_id, $new_payment_details, $image_name, $seller_id);
        if ($stmt_update->execute()) {
            $update_success = true;
            $line_user_id = $new_line_user_id; // อัปเดตค่าที่แสดงในฟอร์ม
            $payment_details = $new_payment_details;
            $current_payment_qr_filename = $image_name; // Update the variable for display after successful update
            $display_payment_qr_path = !empty($current_payment_qr_filename) ? "uploads/" . htmlspecialchars($current_payment_qr_filename) : 'https://via.placeholder.com/200?text=No+QR';
        } else {
            // Handle DB update error
            echo "Error updating record: " . $conn->error;
        }
        $stmt_update->close();
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0"><i class="bi bi-gear-fill me-2"></i>ตั้งค่าโปรไฟล์ผู้ขาย</h2>
            </div>
            <div class="card-body">
                <?php if ($update_success): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>บันทึกข้อมูลเรียบร้อยแล้ว</div>
                <?php endif; ?>

                <form action="seller_profile.php" method="post" enctype="multipart/form-data">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-line me-2"></i>ตั้งค่าการแจ้งเตือนผ่าน LINE</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">คุณจะได้รับการแจ้งเตือนเมื่อมีคำสั่งซื้อใหม่ผ่าน LINE Official Account ของเรา</p>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                <strong>ขั้นตอนการรับ LINE User ID:</strong>
                                <ol class="mb-0 mt-2">
                                    <li>เพิ่มเพื่อน LINE Official Account ของเรา (ตัวอย่าง: @marketplace)</li>
                                    <li>พิมพ์คำว่า <strong>"myid"</strong> ในห้องแชท</li>
                                    <li>บอทจะตอบกลับ User ID ของคุณ (ขึ้นต้นด้วย U...)</li>
                                    <li>คัดลอก User ID นั้นมากรอกในช่องด้านล่างนี้</li>
                                </ol>
                            </div>
                            <div class="mb-3">
                                <label for="line_user_id" class="form-label fw-bold">LINE User ID ของคุณ:</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                    <input type="text" id="line_user_id" name="line_user_id" class="form-control" value="<?php echo htmlspecialchars($line_user_id); ?>" placeholder="Uxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                </div>
                                <div class="form-text">กรอก LINE User ID ของคุณเพื่อรับการแจ้งเตือน</div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-credit-card-fill me-2"></i>ตั้งค่าการชำระเงิน</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">กรุณากรอกข้อมูลบัญชีธนาคาร หรือ PromptPay เพื่อให้ผู้ซื้อใช้ในการชำระเงิน</p>
                            <div class="mb-3">
                                <label for="payment_details" class="form-label fw-bold">ข้อมูลบัญชี (PromptPay / ธนาคาร):</label>
                                <textarea id="payment_details" name="payment_details" class="form-control" rows="4" placeholder="ตัวอย่าง:&#10;พร้อมเพย์: 08x-xxx-xxxx&#10;ธ.กสิกรไทย: 123-4-56789-0&#10;ชื่อบัญชี: นายมาร์เก็ต เพลซ"><?php echo htmlspecialchars($payment_details); ?></textarea>
                                <div class="form-text">ระบุข้อมูลการชำระเงินของคุณให้ชัดเจน</div>
                            </div>
                            <div class="mb-3">
                                <label for="image" class="form-label fw-bold">รูปภาพ QR Code การชำระเงิน:</label>
                                <div class="mb-2">
                                    <img id="paymentQrPreview" src="<?php echo $display_payment_qr_path; ?>" alt="ตัวอย่าง QR Code" class="img-thumbnail" style="width:200px; height:200px; object-fit: cover;">
                                </div>
                                <input type="file" id="imageInput" name="image" class="form-control <?php echo (!empty($image_err)) ? 'is-invalid' : ''; ?>">
                                <div class="form-text">อัปโหลดรูปภาพ QR Code สำหรับการชำระเงิน (เช่น PromptPay QR)</div>
                                <span class="invalid-feedback"><?php echo $image_err; ?></span>
                                <input type="hidden" name="old_payment_qr_filename" value="<?php echo htmlspecialchars($current_payment_qr_filename); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg fw-bold"><i class="bi bi-save-fill me-2"></i>บันทึกข้อมูล</button>
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
        const preview = document.getElementById("paymentQrPreview"); // Changed ID
        preview.src = e.target.result;
        preview.style.display = "block";
      }
      reader.readAsDataURL(file);
    }
  });
</script>

<?php require_once 'footer.php'; ?>
