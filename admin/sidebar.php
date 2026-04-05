<?php

$pending_orders = $conn->query("
    SELECT COUNT(*) AS c FROM orders WHERE status = 'pending'
")->fetch_assoc()['c'];

$total_contacts = $conn->query("
    SELECT COUNT(*) AS c FROM contacts WHERE is_read = 0
")->fetch_assoc()['c'];

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <a href="index.php">Phone<span>Store</span></a>
        <div class="sidebar-brand-sub">Admin Panel</div>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-section">Tổng quan</div>
        <a href="index.php" class="sidebar-item <?= $current_page==='index.php'?'active':'' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-section">Quản lý</div>
        <a href="manage_products.php" class="sidebar-item <?= $current_page==='manage_products.php'?'active':'' ?>">
            <i class="bi bi-phone"></i> Sản phẩm
        </a>
        <a href="manage_banners.php" class="sidebar-item <?= $current_page==='manage_banners.php'?'active':'' ?>">
            <i class="bi bi-image"></i> Banner
        </a>
        <a href="manage_orders.php" class="sidebar-item <?= $current_page==='manage_orders.php'?'active':'' ?>">
            <i class="bi bi-bag-check"></i> Đơn hàng
            <?php if ($pending_orders > 0): ?>
                <span class="sidebar-badge"><?= $pending_orders ?></span>
            <?php endif; ?>
        </a>
        <a href="manage_users.php" class="sidebar-item <?= $current_page==='manage_users.php'?'active':'' ?>">
            <i class="bi bi-people"></i> Khách hàng
        </a>
        <a href="manage_reviews.php" class="sidebar-item <?= $current_page==='manage_reviews.php'?'active':'' ?>">
            <i class="bi bi-star"></i> Đánh giá
        </a>

        <div class="sidebar-section">Khác</div>
        <a href="../pages/contact.php" class="sidebar-item <?= $current_page==='contact.php'?'active':'' ?>">
            <i class="bi bi-envelope"></i> Liên hệ
            <?php if ($total_contacts > 0): ?>
                <span class="sidebar-badge"><?= $total_contacts ?></span>
            <?php endif; ?>
        </a>
        <a href="../index.php" class="sidebar-item" target="_blank">
            <i class="bi bi-box-arrow-up-right"></i> Xem website
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar">
                <?= mb_strtoupper(mb_substr($_SESSION['full_name'], 0, 1)) ?>
            </div>
            <div>
                <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                <div class="sidebar-user-role">Quản trị viên</div>
            </div>
        </div>
        <a href="../auth/logout.php" class="btn-logout">
            <i class="bi bi-box-arrow-right"></i> Đăng xuất
        </a>
    </div>
</aside>