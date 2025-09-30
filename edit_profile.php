<?php
// register.php
require_once 'config.php';

if (!isset($_SESSION["loggedin"])) {
    header("location: index.php");
    exit;
}

$full_name = $phone = $address = $city = $zip_code = "";
$image_err = "";

$seller_id = $_SESSION['id'];
$user_image = '';
$stmt_select = $conn->prepare("SELECT user_image, full_name, phone, address, city, zip_code FROM users WHERE id = ?");
$stmt_select->bind_param("i", $seller_id);
$stmt_select->execute();
$result = $stmt_select->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_image = $row['user_image'];
    $full_name = $row['full_name'];
    $phone = $row['phone'];
    $address = $row['address'];
    $city = $row['city'];
    $zip_code = $row['zip_code'];
}
$stmt_select->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate username, email, password, user_type... (โค้ดส่วนนี้ยาว จึงขอละไว้ แต่หลักการคือตรวจสอบค่าว่างและ format)
    // ...

    $full_name = trim($_POST["full_name"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);
    $city = trim($_POST["city"]);
    $zip_code = trim($_POST["zip_code"]);

    // --- IMPROVED: Image Handling ---
    $image_name = $_POST['old_image']; // Start with the old image name
    $target_dir = "uploads/";
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
                        if (!empty($_POST['old_image']) && file_exists($target_dir . $_POST['old_image'])) {
                            unlink($target_dir . $_POST['old_image']);
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

    $sql = "UPDATE users SET full_name = ?, phone = ?, address = ?, city = ?, zip_code = ?, user_image = ? WHERE id = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssssssi", $param_user_image, $param_full_name, $param_phone, $param_address, $param_city, $param_zip_code, $param_seller_id);
        
        $param_user_image = $image_name;
        $param_full_name = $full_name;
        $param_phone = $phone; 
        $param_address = $address;
        $param_city = $city;
        $param_zip_code = $zip_code;
        $param_seller_id = $seller_id;

        if ($stmt->execute()) {
            $_SESSION['update_success'] = "บันทึกข้อมูลเรียบร้อยแล้ว";
            header("location: edit_profile.php");
        } else {
            echo "Something went wrong. Please try again later.";
        }
        $stmt->close();
    }
    $conn->close();
}
?>

<?php require_once 'header.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0"><i class="bi bi-pencil-square me-2"></i>แก้ไขข้อมูลผู้ใช้งาน</h2>
            </div>
            <div class="card-body">
                <?php
                if (isset($_SESSION['update_success'])) {
                    echo '<div class="alert alert-success">' . $_SESSION['update_success'] . '</div>';
                    unset($_SESSION['update_success']);
                }
                ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post"  enctype="multipart/form-data">
                    <h5 class="mb-3 fw-bold">ข้อมูลส่วนตัวและที่อยู่</h5>
                    <div class="mb-3">
                        <label for="image" class="form-label">รูปโปรไฟล์</label>
                        <div class="mb-2">
                            <img id="preview" src="<?php echo !empty($user_image) ? 'uploads/' . htmlspecialchars($user_image) : 'https://via.placeholder.com/150'; ?>" alt="ตัวอย่างรูปภาพ" class="img-thumbnail rounded-circle" style="width:150px; height:150px; object-fit: cover;">
                        </div>
                        <input type="file" id="imageInput" name="image" class="form-control <?php echo (!empty($image_err)) ? 'is-invalid' : ''; ?>">
                        <div class="form-text">อัปโหลดรูปภาพใหม่เพื่อเปลี่ยนรูปโปรไฟล์ของคุณ (ถ้ามี)</div>
                        <span class="invalid-feedback"><?php echo $image_err; ?></span>
                        <input type="hidden" name="old_image" value="<?php echo htmlspecialchars($user_image); ?>">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">ชื่อ-สกุล</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($full_name); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone-fill"></i></span>
                                <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">ที่อยู่ (บ้านเลขที่, หมู่, ถนน, ตำบล/แขวง, อำเภอ/เขต)</label>
                        <textarea id="address" name="address" class="form-control" rows="3" required><?php echo htmlspecialchars($address); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label">จังหวัด</label>
                            <input type="text" id="city" name="city" class="form-control" value="<?php echo htmlspecialchars($city); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="zip_code" class="form-label">รหัสไปรษณีย์</label>
                            <input type="text" id="zip_code" name="zip_code" class="form-control" value="<?php echo htmlspecialchars($zip_code); ?>" required>
                        </div>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg fw-bold"><i class="bi bi-save-fill me-2"></i>บันทึกการเปลี่ยนแปลง</button>
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
        const preview = document.getElementById("preview"); // Corrected ID
        preview.src = e.target.result;
        preview.style.display = "block"; // show image
      }
      reader.readAsDataURL(file); // convert file to base64 URL
    }
  });
</script>

<?php require_once 'footer.php'; ?>
