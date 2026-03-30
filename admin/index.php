<?php
session_start();
require_once '../config.php';

// Chỉ admin mới vào được
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// ── THỐNG KÊ ────────────────────────────────────────
$total_products = $conn->query("SELECT COUNT(*) AS c FROM products")->fetch_assoc()['c'];
$total_orders   = $conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'];
$total_users    = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='customer'")->fetch_assoc()['c'];
$total_revenue  = $conn->query("SELECT SUM(total_price) AS c FROM orders WHERE status='delivered'")->fetch_assoc()['c'] ?? 0;

$pending_orders  = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status='pending'")->fetch_assoc()['c'];
$total_reviews   = $conn->query("SELECT COUNT(*) AS c FROM reviews")->fetch_assoc()['c'];
$total_contacts  = $conn->query("SELECT COUNT(*) AS c FROM contacts WHERE is_read=0")->fetch_assoc()['c'];
$low_stock       = $conn->query("SELECT COUNT(*) AS c FROM products WHERE stock <= 5")->fetch_assoc()['c'];

// Đơn hàng mới nhất
$recent_orders = $conn->query("
    SELECT o.*, u.full_name AS customer_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 8
");

// Sản phẩm bán chạy
$top_products = $conn->query("
    SELECT p.name, p.thumbnail, b.name AS brand_name,
           SUM(oi.quantity) AS sold
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN brands b ON p.brand_id = b.id
    GROUP BY p.id
    ORDER BY sold DESC
    LIMIT 5
");

$status_labels = [
    'pending'   => ['label' => 'Chờ xác nhận', 'color' => '#D97706', 'bg' => '#FFFBEB'],
    'confirmed' => ['label' => 'Đã xác nhận',  'color' => '#2563EB', 'bg' => '#EFF6FF'],
    'shipping'  => ['label' => 'Đang giao',    'color' => '#7C3AED', 'bg' => '#F5F3FF'],
    'delivered' => ['label' => 'Đã giao',      'color' => '#16A34A', 'bg' => '#F0FDF4'],
    'cancelled' => ['label' => 'Đã hủy',       'color' => '#DC2626', 'bg' => '#FEF2F2'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin PhoneStore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #0057FF;
            --primary-dark: #0040CC;
            --dark: #0A0A0A;
            --gray: #6B7280;
            --light: #F8F8F8;
            --border: #E5E7EB;
            --sidebar-w: 240px;
            --font: 'Nunito', sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font); background: #F3F4F6; color: var(--dark); }

        /* ── SIDEBAR ── */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar-w);
            height: 100vh;
            background: #0A0A0A;
            display: flex;
            flex-direction: column;
            z-index: 100;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 20px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-brand a {
            font-size: 1.3rem;
            font-weight: 800;
            color: #fff;
            text-decoration: none;
        }
        .sidebar-brand a span { color: var(--primary); }
        .sidebar-brand-sub {
            font-size: 0.68rem;
            color: rgba(255,255,255,0.3);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 2px;
        }
        .sidebar-nav { padding: 12px 0; flex: 1; }
        .sidebar-section {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.25);
            padding: 14px 20px 6px;
        }
        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            color: rgba(255,255,255,0.55);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .sidebar-item:hover {
            background: rgba(255,255,255,0.05);
            color: #fff;
        }
        .sidebar-item.active {
            background: rgba(0,87,255,0.15);
            color: #fff;
            border-left-color: var(--primary);
        }
        .sidebar-item i { font-size: 1rem; width: 18px; }
        .sidebar-badge {
            margin-left: auto;
            background: #EF4444;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 100px;
        }
        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .sidebar-avatar {
            width: 34px; height: 34px;
            background: rgba(0,87,255,0.3);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #60A5FA;
            font-size: 0.85rem;
            font-weight: 800;
        }
        .sidebar-user-name {
            font-size: 0.82rem;
            font-weight: 700;
            color: #fff;
        }
        .sidebar-user-role {
            font-size: 0.68rem;
            color: rgba(255,255,255,0.35);
        }
        .btn-logout {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.2);
            color: #F87171;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.8rem;
            font-weight: 700;
            font-family: var(--font);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-logout:hover { background: rgba(239,68,68,0.2); color: #FCA5A5; }

        /* ── MAIN ── */
        .main-content {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
        }

        /* Topbar */
        .admin-topbar {
            background: #fff;
            border-bottom: 1px solid var(--border);
            padding: 0 28px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .admin-topbar-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--dark);
        }
        .admin-topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .topbar-link {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82rem;
            color: var(--gray);
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .topbar-link:hover { background: var(--light); color: var(--primary); }

        .page-body { padding: 28px; }

        /* ── STAT CARDS ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            transition: all 0.2s;
        }
        .stat-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            transform: translateY(-1px);
        }
        .stat-info {}
        .stat-label {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .stat-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 4px;
        }
        .stat-sub {
            font-size: 0.75rem;
            color: var(--gray);
        }
        .stat-sub.warning { color: #D97706; font-weight: 700; }
        .stat-sub.danger  { color: #EF4444; font-weight: 700; }
        .stat-icon {
            width: 46px; height: 46px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        /* ── SECTION ── */
        .section-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
        }
        .section-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
        }
        .section-card-title {
            font-weight: 800;
            font-size: 0.95rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-card-title i { color: var(--primary); }
        .btn-view-all {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: flex; align-items: center; gap: 4px;
            padding: 5px 12px;
            border: 1.5px solid #C7D9FF;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .btn-view-all:hover { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* Orders table */
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th {
            padding: 10px 16px;
            text-align: left;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray);
            background: var(--light);
            border-bottom: 1px solid var(--border);
        }
        .admin-table td {
            padding: 12px 16px;
            font-size: 0.85rem;
            border-bottom: 1px solid #F9FAFB;
            color: var(--dark);
        }
        .admin-table tr:last-child td { border-bottom: none; }
        .admin-table tr:hover td { background: #FAFAFA; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 100px;
            border: 1px solid;
            white-space: nowrap;
        }

        /* Top products */
        .top-product-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }
        .top-product-item:last-child { border-bottom: none; }
        .top-product-item:hover { background: #FAFAFA; }
        .top-product-rank {
            width: 24px;
            font-size: 0.78rem;
            font-weight: 800;
            color: var(--gray);
            text-align: center;
            flex-shrink: 0;
        }
        .top-product-rank.gold   { color: #D97706; }
        .top-product-rank.silver { color: #6B7280; }
        .top-product-rank.bronze { color: #92400E; }
        .top-product-img {
            width: 40px; height: 40px;
            border-radius: 8px;
            background: var(--light);
            border: 1px solid var(--border);
            overflow: hidden;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .top-product-img img { width: 100%; height: 100%; object-fit: cover; }
        .top-product-name {
            flex: 1;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--dark);
        }
        .top-product-brand { font-size: 0.72rem; color: var(--gray); font-weight: 600; }
        .top-product-sold {
            font-size: 0.82rem;
            font-weight: 800;
            color: var(--primary);
            white-space: nowrap;
        }

        /* Quick actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            padding: 16px;
        }
        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 700;
            transition: all 0.2s;
            border: 1.5px solid var(--border);
            color: var(--dark);
        }
        .quick-action-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: #EEF4FF;
        }
        .quick-action-btn i { font-size: 1.1rem; color: var(--primary); }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <a href="index.php">Phone<span>Store</span></a>
        <div class="sidebar-brand-sub">Admin Panel</div>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-section">Tổng quan</div>
        <a href="index.php" class="sidebar-item active">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-section">Quản lý</div>
        <a href="manage_products.php" class="sidebar-item">
            <i class="bi bi-phone"></i> Sản phẩm
        </a>
        <a href="manage_banners.php" class="sidebar-item">
            <i class="bi bi-image"></i> Banner
        </a>
        <a href="manage_orders.php" class="sidebar-item">
            <i class="bi bi-bag-check"></i> Đơn hàng
            <?php if ($pending_orders > 0): ?>
                <span class="sidebar-badge"><?= $pending_orders ?></span>
            <?php endif; ?>
        </a>
        <a href="manage_users.php" class="sidebar-item">
            <i class="bi bi-people"></i> Khách hàng
        </a>
        <a href="manage_reviews.php" class="sidebar-item">
            <i class="bi bi-star"></i> Đánh giá
        </a>

        <div class="sidebar-section">Khác</div>
        <a href="../pages/contact.php" class="sidebar-item">
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

<!-- ══ MAIN ══ -->
<div class="main-content">

    <!-- Topbar -->
    <div class="admin-topbar">
        <div class="admin-topbar-title">📊 Dashboard</div>
        <div class="admin-topbar-right">
            <span style="font-size:0.78rem;color:var(--gray)">
                <i class="bi bi-calendar3"></i>
                <?= date('d/m/Y') ?>
            </span>
            <a href="manage_products.php?action=add" class="topbar-link" style="background:var(--primary);color:#fff;font-weight:700">
                <i class="bi bi-plus-lg"></i> Thêm sản phẩm
            </a>
        </div>
    </div>

    <div class="page-body">

        <!-- ══ STAT CARDS ══ -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <div class="stat-label">Doanh thu</div>
                    <div class="stat-value" style="font-size:1.2rem">
                        <?= number_format($total_revenue, 0, ',', '.') ?>đ
                    </div>
                    <div class="stat-sub">Từ đơn đã giao</div>
                </div>
                <div class="stat-icon" style="background:#EEF4FF">💰</div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="stat-label">Đơn hàng</div>
                    <div class="stat-value"><?= $total_orders ?></div>
                    <div class="stat-sub <?= $pending_orders > 0 ? 'warning' : '' ?>">
                        <?= $pending_orders > 0 ? "$pending_orders chờ xác nhận" : 'Không có đơn chờ' ?>
                    </div>
                </div>
                <div class="stat-icon" style="background:#FFFBEB">📦</div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="stat-label">Sản phẩm</div>
                    <div class="stat-value"><?= $total_products ?></div>
                    <div class="stat-sub <?= $low_stock > 0 ? 'danger' : '' ?>">
                        <?= $low_stock > 0 ? "$low_stock sắp hết hàng" : 'Tất cả còn hàng' ?>
                    </div>
                </div>
                <div class="stat-icon" style="background:#F0FDF4">📱</div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="stat-label">Khách hàng</div>
                    <div class="stat-value"><?= $total_users ?></div>
                    <div class="stat-sub"><?= $total_reviews ?> đánh giá sản phẩm</div>
                </div>
                <div class="stat-icon" style="background:#F5F3FF">👥</div>
            </div>
        </div>

        <div class="row g-3">

            <!-- ══ ĐƠN HÀNG MỚI NHẤT ══ -->
            <div class="col-lg-8">
                <div class="section-card">
                    <div class="section-card-header">
                        <div class="section-card-title">
                            <i class="bi bi-bag-check"></i> Đơn hàng mới nhất
                        </div>
                        <a href="manage_orders.php" class="btn-view-all">
                            Xem tất cả <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    <div style="overflow-x:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày đặt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_orders->num_rows > 0): ?>
                                    <?php while ($o = $recent_orders->fetch_assoc()):
                                        $s = $status_labels[$o['status']] ?? $status_labels['pending'];
                                    ?>
                                    <tr>
                                        <td>
                                            <span style="font-family:monospace;font-size:0.78rem;color:var(--primary);font-weight:700">
                                                <?= $o['payment_code'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-weight:700"><?= htmlspecialchars($o['full_name']) ?></div>
                                            <div style="font-size:0.72rem;color:var(--gray)"><?= htmlspecialchars($o['phone']) ?></div>
                                        </td>
                                        <td style="font-weight:800;color:#EF4444">
                                            <?= number_format($o['total_price'],0,',','.') ?>đ
                                        </td>
                                        <td>
                                            <span class="status-badge"
                                                  style="color:<?= $s['color'] ?>;background:<?= $s['bg'] ?>;border-color:<?= $s['color'] ?>22">
                                                <?= $s['label'] ?>
                                            </span>
                                        </td>
                                        <td style="color:var(--gray);font-size:0.78rem">
                                            <?= date('d/m/Y H:i', strtotime($o['created_at'])) ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center;padding:32px;color:var(--gray)">
                                            Chưa có đơn hàng nào
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ══ RIGHT COLUMN ══ -->
            <div class="col-lg-4">

                <!-- Sản phẩm bán chạy -->
                <div class="section-card mb-3">
                    <div class="section-card-header">
                        <div class="section-card-title">
                            <i class="bi bi-fire"></i> Bán chạy nhất
                        </div>
                    </div>
                    <?php
                    $rank_classes = ['gold', 'gold', 'silver', 'bronze', ''];
                    $rank_icons   = ['🥇', '🥈', '🥉', '4', '5'];
                    $i = 0;
                    if ($top_products->num_rows > 0):
                        while ($tp = $top_products->fetch_assoc()):
                    ?>
                    <div class="top-product-item">
                        <div class="top-product-rank <?= $rank_classes[$i] ?? '' ?>">
                            <?= $rank_icons[$i] ?? ($i+1) ?>
                        </div>
                        <div class="top-product-img">
                            <?php if ($tp['thumbnail']): ?>
                                <img src="../assets/images/products/<?= htmlspecialchars($tp['thumbnail']) ?>" alt="">
                            <?php else: ?>📱<?php endif; ?>
                        </div>
                        <div style="flex:1;min-width:0">
                            <div class="top-product-name" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                <?= htmlspecialchars($tp['name']) ?>
                            </div>
                            <div class="top-product-brand"><?= htmlspecialchars($tp['brand_name'] ?? '') ?></div>
                        </div>
                        <div class="top-product-sold"><?= $tp['sold'] ?> đã bán</div>
                    </div>
                    <?php $i++; endwhile;
                    else: ?>
                    <div style="text-align:center;padding:24px;color:var(--gray);font-size:0.85rem">
                        Chưa có dữ liệu bán hàng
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Quick actions -->
                <div class="section-card">
                    <div class="section-card-header">
                        <div class="section-card-title">
                            <i class="bi bi-lightning-fill"></i> Thao tác nhanh
                        </div>
                    </div>
                    <div class="quick-actions">
                        <a href="manage_products.php?action=add" class="quick-action-btn">
                            <i class="bi bi-plus-circle-fill"></i> Thêm sản phẩm
                        </a>
                        <a href="manage_orders.php?status=pending" class="quick-action-btn">
                            <i class="bi bi-clock-fill"></i> Đơn chờ duyệt
                            <?php if ($pending_orders > 0): ?>
                                <span style="margin-left:auto;background:#EF4444;color:#fff;font-size:0.65rem;padding:1px 5px;border-radius:100px"><?= $pending_orders ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="manage_users.php" class="quick-action-btn">
                            <i class="bi bi-person-plus-fill"></i> Khách hàng
                        </a>
                        <a href="manage_reviews.php" class="quick-action-btn">
                            <i class="bi bi-star-fill"></i> Đánh giá
                        </a>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>