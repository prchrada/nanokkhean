<link rel="stylesheet" href="css/style.css">

<?php

// index.php - หน้าหลักสำหรับแสดงสินค้าทั้งหมด (Buyer View)
require_once 'header.php';
require_once 'config.php';
// --- SEARCH FUNCTIONALITY ---
$search_term = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = $_GET['search'];
    // Use prepared statements to prevent SQL injection
    // NOTE: แก้ไขโดยการลบเงื่อนไข 'p.stock_quantity > 0' เพื่อให้แสดงสินค้าทั้งหมด
    $sql = "SELECT p.id, p.name, p.price, p.image, u.username as seller_name 
            FROM products p 
            JOIN users u ON p.seller_id = u.id 
            WHERE p.name LIKE ? 
            ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($sql);
    $like_search_term = "%" . $search_term . "%";
    $stmt->bind_param("s", $like_search_term);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Default query without search
    // NOTE: แก้ไขโดยการลบเงื่อนไข 'p.stock_quantity > 0' เพื่อให้แสดงสินค้าทั้งหมด
    $sql = "SELECT p.id, p.name, p.price, p.image, u.username as seller_name 
            FROM products p 
            JOIN users u ON p.seller_id = u.id 
            ORDER BY p.created_at DESC";
    $result = $conn->query($sql);
}
?>

<!-- Hero Section -->
<div class="hero-section">
    <div class="container">
        <h1 class="text-center mb-4">ยินดีต้อนรับสู่ Marketplace</h1>
        <!-- Search Bar -->
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="search-container">
                    <form action="index.php" method="GET" class="d-flex">
                        <input class="form-control me-2" type="search" placeholder="ค้นหาสินค้า..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                        <button class="btn btn-primary" type="submit">ค้นหา</button>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://www.gstatic.com/firebasejs/10.11.0/firebase-app-compat.js"></script>
        <script src="https://www.gstatic.com/firebasejs/10.11.0/firebase-messaging-compat.js"></script>

        <script>
        // Replace with your Firebase web config
        const firebaseConfig = {
            apiKey: "AIzaSyA_Ahn7_7KMXppfJ2wNUWSMxlkcswR17es",
            authDomain: "product-cfe8a.firebaseapp.com",
            projectId: "product-cfe8a",
            storageBucket: "product-cfe8a.firebasestorage.app",
            messagingSenderId: "491590872427",
            appId: "1:491590872427:web:a11d40da5192c28e26b02e",
            measurementId: "G-L57YBWEWCQ"
        };
        firebase.initializeApp(firebaseConfig);
        const messaging = firebase.messaging();

        async function registerFCMToken(userId) {
            try {
            const swRegistration = await navigator.serviceWorker.register('/marketplace/firebase-messaging-sw.js');

            // request notification permission
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                console.log('Notification permission not granted');
                return;
            }

            const token = await messaging.getToken({ 
                serviceWorkerRegistration: swRegistration,
                vapidKey: 'BM1vbxj29QhJ4tLoJ838JDBVN7smoonBwW0x8m1rYhhGgVwaiOGqHlucdQ7O1as9Sq79spMe0nFiDbJ6ltb9gJE' });
            if (!token) {
                console.log('No FCM token available');
                return;
            }
            // send token to your local PHP API
            await fetch('/marketplace/firebase_noti_register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId, token }),
            });
            console.log('Registered token with server');
            } catch (err) {
            console.error('FCM registration error', err);
            }
        }

        navigator.serviceWorker.addEventListener('message', event => {
            if(event.data.type === 'fcm-log'){
                console.log('SW log (background message):', event.data.payload);
            }
        });

        // call registerFCMToken(currentUserId) after user auth
        <?php if (isset($_SESSION['id'])): ?>
            registerFCMToken(<?php echo json_encode($_SESSION['id']); ?>);
        <?php endif; ?>
        </script>
    </div>
</div>

<div class="container">
    <h2 class="page-title"><?php echo !empty($search_term) ? "ผลการค้นหาสำหรับ '" . htmlspecialchars($search_term) . "'" : "สินค้าแนะนำ"; ?></h2>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="col">
                    <div class="card h-100 product-card shadow-sm">
                        <a href="product_detail.php?id=<?php echo $row['id']; ?>" class="text-decoration-none">
                            <img src="uploads/<?php echo htmlspecialchars($row['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($row['name']); ?>" style="height: 200px; object-fit: cover;">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title text-dark"><?php echo htmlspecialchars($row['name']); ?></h5>
                                <p class="card-text text-muted small">ผู้ขาย: <?php echo htmlspecialchars($row['seller_name']); ?></p>
                                <div class="mt-auto">
                                    <span class="price-badge">฿<?php echo number_format($row['price'], 2); ?></span>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-top-0 p-3">
                                <button class="btn btn-primary w-100">ดูรายละเอียด</button>
                            </div>
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-warning text-center" role="alert">
                    <?php echo !empty($search_term) ? "ไม่พบสินค้าที่ตรงกับ '" . htmlspecialchars($search_term) . "'" : "ยังไม่มีสินค้าในระบบ"; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
require_once 'footer.php';
?>

