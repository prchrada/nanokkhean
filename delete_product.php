<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a seller
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] != 'seller') {
    header("location: login.php");
    exit;
}

$product_id = $_GET['id'] ?? null;
if (!$product_id) {
    header("location: seller_dashboard.php");
    exit;
}

// Security: Verify the product belongs to the logged-in seller before deleting
$sql = "SELECT image FROM products WHERE id = ? AND seller_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $product_id, $_SESSION['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $product = $result->fetch_assoc();
    
    // Delete the product image file from server
    if (file_exists("uploads/" . $product['image'])) {
        unlink("uploads/" . $product['image']);
    }

    // Delete the product record from database
    $sql_delete = "DELETE FROM products WHERE id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $product_id);
    $stmt_delete->execute();
    
    header("location: seller_dashboard.php?status=deleted");
    exit;

} else {
    // If not the owner or product doesn't exist, redirect
    header("location: seller_dashboard.php");
    exit;
}
?>
