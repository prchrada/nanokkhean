<?php
// register.php
require_once 'config.php';

$username = $password = $confirm_password = $email = $user_type = $full_name = $phone = $address = $city = $zip_code = "";
$image_err = "";
$username_err = $password_err = $confirm_password_err = $email_err = $user_type_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate username, email, password, user_type... (โค้ดส่วนนี้ยาว จึงขอละไว้ แต่หลักการคือตรวจสอบค่าว่างและ format)
    // ...

    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $user_type = $_POST["user_type"];
    $full_name = trim($_POST["full_name"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);
    $city = trim($_POST["city"]);
    $zip_code = trim($_POST["zip_code"]);
    

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


    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (username, password, email, user_type, user_image, full_name, phone, address, city, zip_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssssssssss", $param_username, $param_password, $param_email, $param_user_type, $param_user_image, $param_full_name, $param_phone, $param_address, $param_city, $param_zip_code);
        
        $param_username = $username;
        $param_password = $hashed_password;
        $param_email = $email;
        $param_user_type = $user_type;
        $param_user_image = $image_name;
        $param_full_name = $full_name;
        $param_phone = $phone;
        $param_address = $address;
        $param_city = $city;
        $param_zip_code = $zip_code;


        if ($stmt->execute()) {
            header("location: login.php");
        } else {
            echo "Something went wrong. Please try again later.";
        }
        $stmt->close();
    }
}
?>

<?php require_once 'header.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0"><i class="bi bi-person-plus-fill me-2"></i>สร้างบัญชีผู้ใช้ใหม่</h2>
            </div>
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                    
                    <h5 class="mb-3 fw-bold">ข้อมูลบัญชี</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                                <input type="text" id="username" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>" required>
                                <span class="invalid-feedback"><?php echo $username_err; ?></span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                                <input type="email" id="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" required>
                                <span class="invalid-feedback"><?php echo $email_err; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                             <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" id="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" required>
                            <span class="invalid-feedback"><?php echo $password_err; ?></span>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h5 class="mb-3 fw-bold">ข้อมูลส่วนตัวและที่อยู่</h5>
                    <div class="mb-3">
                        <label for="image" class="form-label">รูปโปรไฟล์</label>
                        <input type="file" id="imageInput" name="image" class="form-control <?php echo (!empty($image_err)) ? 'is-invalid' : ''; ?>">
                        <div class="form-text">อัปโหลดรูปภาพสำหรับโปรไฟล์ของคุณ (ถ้ามี)</div>
                        <span class="invalid-feedback"><?php echo $image_err; ?></span>
                        <img id="preview" src="#" alt="ตัวอย่างรูปภาพ" class="img-thumbnail rounded-circle mt-3" style="width:150px; height:150px; object-fit: cover; display: none;">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">ชื่อ-สกุล</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($full_name); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                            <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" required>
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

                    <hr class="my-4">

                    <div class="mb-4">
                        <h5 class="mb-3 fw-bold">ประเภทบัญชี</h5>
                        <div class="form-check form-check-inline p-0">
                            <input type="radio" class="btn-check" name="user_type" id="buyer" value="buyer" autocomplete="off" checked>
                            <label class="btn btn-outline-primary" for="buyer"><i class="bi bi-bag-heart-fill me-2"></i>สมัครเป็นผู้ซื้อ</label>
                        </div>
                        <div class="form-check form-check-inline p-0">
                            <input type="radio" class="btn-check" name="user_type" id="seller" value="seller" autocomplete="off">
                            <label class="btn btn-outline-primary" for="seller"><i class="bi bi-shop me-2"></i>สมัครเป็นผู้ขาย</label>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg fw-bold">สมัครสมาชิก</button>
                    </div>
                    <p class="mt-4 text-center text-muted">มีบัญชีอยู่แล้ว? <a href="login.php" class="fw-bold">เข้าสู่ระบบที่นี่</a></p>
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
