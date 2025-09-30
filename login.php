<?php
// login.php
require_once "config.php";

$username = $password = "";
$username_err = $password_err = $login_err = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    $sql = "SELECT id, username, password, user_type FROM users WHERE username = ?";
    
    if($stmt = $conn->prepare($sql)){
        $stmt->bind_param("s", $param_username);
        $param_username = $username;
        
        if($stmt->execute()){
            $stmt->store_result();
            
            if($stmt->num_rows == 1){                    
                $stmt->bind_result($id, $username, $hashed_password, $user_type);
                if($stmt->fetch()){
                    if(password_verify($password, $hashed_password)){
                        // Password is correct. Regenerate session ID to prevent session fixation.
                        session_regenerate_id(true);
                        
                        // Store data in session variables
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id"] = $id;
                        $_SESSION["username"] = $username;                            
                        $_SESSION["user_type"] = $user_type;

                        // Redirect user based on user type
                        if($user_type == 'seller'){
                            header("location: seller_dashboard.php");
                        } else {
                            header("location: index.php");
                        }
                    } else{
                        $login_err = "Invalid username or password.";
                    }
                }
            } else{
                $login_err = "Invalid username or password.";
            }
        } else{
            echo "Oops! Something went wrong. Please try again later.";
        }
        $stmt->close();
    }
}
?>

<?php require_once 'header.php'; ?>

<style>
    /* Custom styles for login page */
    .login-container {
        min-height: 70vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .login-card {
        max-width: 450px;
        width: 100%;
        padding: 2rem;
        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        border: none;
        border-radius: 1rem;
    }
    .login-icon {
        font-size: 3rem;
        color: var(--theme-primary);
    }
</style>

<div class="login-container">
    <div class="card login-card">
        <div class="card-body p-lg-4">
            <div class="text-center mb-4">
                <i class="bi bi-key-fill login-icon"></i>
                <h2 class="mt-2">เข้าสู่ระบบ</h2>
                <p class="text-muted">กรุณากรอกข้อมูลเพื่อเข้าใช้งาน</p>
            </div>
            <?php if(!empty($login_err)){ echo '<div class="alert alert-danger text-center"><i class="bi bi-exclamation-triangle-fill"></i> ' . $login_err . '</div>'; } ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                    <input type="text" name="username" class="form-control form-control-lg" placeholder="Username" required>
                </div>    
                <div class="input-group mb-4">
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" name="password" class="form-control form-control-lg" placeholder="Password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg fw-bold">เข้าสู่ระบบ</button>
                </div>
                <p class="mt-4 text-center">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิกที่นี่</a>.</p>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
