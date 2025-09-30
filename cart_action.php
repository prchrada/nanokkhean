<?php
session_start();

// ตรวจสอบว่ามี action ส่งมาหรือไม่
if (isset($_GET['action'])) {
    
    // กรณี: เพิ่มสินค้าลงตะกร้า
    if ($_GET['action'] == 'add' && isset($_POST['product_id']) && isset($_POST['quantity'])) {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);

        if ($quantity > 0) {
            // ถ้ามีสินค้านี้ในตะกร้าแล้ว, ให้บวกจำนวนเพิ่ม
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id] += $quantity;
            } else {
                // ถ้ายังไม่มี, ให้เพิ่มใหม่
                $_SESSION['cart'][$product_id] = $quantity;
            }
        }
    }

    // กรณี: ลบสินค้าออกจากตะกร้า
    if ($_GET['action'] == 'remove' && isset($_GET['product_id'])) {
        $product_id = intval($_GET['product_id']);

        if (isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
        }
    }
    
    // กรณี: อัปเดตจำนวนสินค้า (ถ้าต้องการทำในอนาคต)
    if ($_GET['action'] == 'update' && isset($_POST['product_id']) && isset($_POST['quantity'])) {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);

        if (isset($_SESSION['cart'][$product_id])) {
            if ($quantity > 0) {
                $_SESSION['cart'][$product_id] = $quantity;
            } else {
                // ถ้าจำนวนเป็น 0 หรือน้อยกว่า, ให้ลบออก
                unset($_SESSION['cart'][$product_id]);
            }
        }
    }
}

// หลังจากจัดการเสร็จ, ให้ redirect กลับไปหน้าตะกร้าสินค้า
header('location: cart.php');
exit;
?>

