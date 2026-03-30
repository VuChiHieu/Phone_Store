<?php
    session_start();
    require_once '../config.php';

    // ── GET PRODUCT ─────────────────────────────────────
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { header('Location: products.php'); exit; }

    $stmt = $conn->prepare("
        SELECT p.*, b.name AS brand_name, c.name AS cat_name, c.slug AS cat_slug
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    if (!$p) { header('Location: products.php'); exit; }

    // ── PRODUCT IMAGES ──────────────────────────────────
    $images = $conn->query("SELECT * FROM product_images WHERE product_id = $id ORDER BY sort_order ASC");

    // ── REVIEWS ─────────────────────────────────────────
    $reviews = $conn->query("
        SELECT r.*, u.full_name
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.product_id = $id
        ORDER BY r.created_at DESC
    ");
    $review_count = $reviews->num_rows;
    $avg_rating   = 0;
    $star_counts  = [5=>0, 4=>0, 3=>0, 2=>0, 1=>0];
    $review_list  = [];
    while ($rv = $reviews->fetch_assoc()) {
        $review_list[]               = $rv;
        $avg_rating                 += $rv['rating'];
        $star_counts[$rv['rating']] = ($star_counts[$rv['rating']] ?? 0) + 1;
    }
    if ($review_count > 0) $avg_rating = round($avg_rating / $review_count, 1);

    // ── HANDLE SUBMIT REVIEW ────────────────────────────
    $review_error   = '';
    $review_success = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ../auth/login.php');
            exit;
        }
        $rating  = (int)$_POST['rating'];
        $comment = trim($_POST['comment']);
        $uid     = $_SESSION['user_id'];

        if ($rating < 1 || $rating > 5) {
            $review_error = 'Vui lòng chọn số sao!';
        } elseif (empty($comment)) {
            $review_error = 'Vui lòng nhập nội dung đánh giá!';
        } else {
            // Kiểm tra đã đánh giá chưa
            $check = $conn->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
            $check->bind_param("ii", $id, $uid);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $review_error = 'Bạn đã đánh giá sản phẩm này rồi!';
            } else {
                $ins = $conn->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?,?,?,?)");
                $ins->bind_param("iiis", $id, $uid, $rating, $comment);
                if ($ins->execute()) {
                    $review_success = 'Cảm ơn bạn đã đánh giá!';
                    header("Location: product_detail.php?id=$id&reviewed=1");
                    exit;
                }
            }
        }
    }
    if (isset($_GET['reviewed'])) $review_success = 'Cảm ơn bạn đã đánh giá sản phẩm!';

    // ── RELATED PRODUCTS ────────────────────────────────
    $related = $conn->prepare("
        SELECT p.*, b.name AS brand_name
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.category_id = ? AND p.id != ?
        ORDER BY RAND()
        LIMIT 4
    ");
    $related->bind_param("ii", $p['category_id'], $id);
    $related->execute();
    $related_products = $related->get_result();

    // Đếm giỏ hàng
    $cart_count = 0;
    if (isset($_SESSION['user_id'])) {
        $uid = $_SESSION['user_id'];
        $r   = $conn->query("SELECT SUM(quantity) AS total FROM cart WHERE user_id = $uid");
        $cart_count = $r->fetch_assoc()['total'] ?? 0;
    }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($p['name']) ?> - Phone Store</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .detail-wrap {
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px;
        }

        /* ── BREADCRUMB ── */
        .breadcrumb-bar {
            background: #fff;
            border-bottom: 1px solid var(--border);
            padding: 10px 0;
            font-size: 0.82rem;
            color: var(--gray);
        }
        .breadcrumb-bar a { color: var(--gray); text-decoration: none; }
        .breadcrumb-bar a:hover { color: var(--primary); }

        /* ── MAIN DETAIL ── */
        .detail-main {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 24px;
        }

        /* LEFT — Images */
        .detail-img-main {
            width: 100%;
            aspect-ratio: 1;
            background: var(--light);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8rem;
            margin-bottom: 12px;
            cursor: zoom-in;
            position: relative;
        }
        .detail-img-main img {
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        .detail-img-main:hover img { transform: scale(1.05); }

        .detail-img-thumbs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .thumb-item {
            width: 68px; height: 68px;
            border: 2px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            background: var(--light);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem;
            transition: border-color 0.2s;
            flex-shrink: 0;
        }
        .thumb-item img { width: 100%; height: 100%; object-fit: cover; }
        .thumb-item.active { border-color: var(--primary); }
        .thumb-item:hover { border-color: var(--primary); }

        /* RIGHT — Info */
        .detail-brand {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
        }
        .detail-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1.3;
            margin-bottom: 12px;
        }

        /* Rating summary */
        .detail-rating {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
        .stars { color: #F59E0B; font-size: 0.9rem; }
        .rating-num { font-weight: 700; color: var(--dark); font-size: 0.9rem; }
        .rating-count { color: var(--gray); font-size: 0.85rem; }

        /* Price */
        .detail-price-wrap {
            margin-bottom: 20px;
        }
        .detail-price {
            font-size: 1.8rem;
            font-weight: 800;
            color: #EF4444;
            display: block;
            line-height: 1;
            margin-bottom: 6px;
        }
        .detail-price-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .detail-old-price {
            font-size: 1rem;
            color: var(--gray);
            text-decoration: line-through;
        }
        .detail-discount-badge {
            background: #FEF2F2;
            color: #EF4444;
            border: 1px solid #FECACA;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 100px;
        }

        /* Variants */
        .detail-variants { margin-bottom: 20px; }
        .variant-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }
        .variant-options { display: flex; gap: 8px; flex-wrap: wrap; }
        .variant-btn {
            padding: 6px 14px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--dark);
            background: #fff;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Nunito', sans-serif;
        }
        .variant-btn:hover,
        .variant-btn.active {
            border-color: var(--primary);
            color: var(--primary);
            background: #EEF4FF;
        }

        /* Quantity */
        .qty-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .qty-label { font-size: 0.82rem; font-weight: 700; color: var(--dark); }
        .qty-control {
            display: flex;
            align-items: center;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
        }
        .qty-btn {
            width: 36px; height: 36px;
            display: flex; align-items: center; justify-content: center;
            background: var(--light);
            border: none; cursor: pointer;
            font-size: 1.1rem; color: var(--dark);
            transition: background 0.2s;
        }
        .qty-btn:hover { background: #e0e0e0; }
        .qty-input {
            width: 48px; height: 36px;
            border: none; border-left: 1.5px solid var(--border); border-right: 1.5px solid var(--border);
            text-align: center;
            font-size: 0.9rem; font-weight: 700;
            font-family: 'Nunito', sans-serif;
            outline: none;
        }
        .qty-stock { font-size: 0.8rem; color: var(--gray); }
        .qty-stock span { color: #16A34A; font-weight: 700; }

        /* Action buttons */
        .detail-actions { display: flex; gap: 10px; margin-bottom: 20px; }
        .btn-buy-now {
            flex: 1;
            background: #EF4444;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 13px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: 'Nunito', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .btn-buy-now:hover { background: #DC2626; color: #fff; transform: translateY(-1px); }
        .btn-add-cart-detail {
            flex: 1;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 13px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: 'Nunito', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .btn-add-cart-detail:hover { background: var(--primary-dark); color: #fff; transform: translateY(-1px); }

        /* Guarantees */
        .detail-guarantees {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .guarantee-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--light);
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--dark);
        }
        .guarantee-item i { color: #16A34A; font-size: 1rem; }

        /* ── TABS ── */
        .detail-tabs {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 24px;
        }
        .tab-nav {
            display: flex;
            border-bottom: 1px solid var(--border);
        }
        .tab-btn {
            padding: 16px 28px;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--gray);
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            font-family: 'Nunito', sans-serif;
            transition: all 0.2s;
            margin-bottom: -1px;
        }
        .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
        .tab-btn:hover { color: var(--primary); }
        .tab-content { padding: 28px; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        .tab-pane p { font-size: 0.9rem; line-height: 1.8; color: #374151; margin-bottom: 12px; }

        /* Specs table */
        .specs-table { width: 100%; border-collapse: collapse; }
        .specs-table tr:nth-child(even) { background: var(--light); }
        .specs-table td {
            padding: 10px 14px;
            font-size: 0.875rem;
            border: 1px solid var(--border);
        }
        .specs-table td:first-child {
            font-weight: 700;
            color: var(--dark);
            width: 35%;
            background: #F9FAFB;
        }
        .specs-table td:last-child { color: #374151; }

        /* ── REVIEWS ── */
        .reviews-section {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 24px;
        }
        .reviews-section-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .reviews-section-title::before {
            content: '';
            display: inline-block;
            width: 4px; height: 18px;
            background: var(--primary);
            border-radius: 2px;
        }

        /* Rating summary */
        .rating-summary {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 24px;
            align-items: center;
            padding: 20px;
            background: var(--light);
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .rating-big {
            text-align: center;
            padding-right: 24px;
            border-right: 1px solid var(--border);
        }
        .rating-big-num {
            font-size: 3rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
        }
        .rating-big-stars { color: #F59E0B; font-size: 1.1rem; margin: 6px 0; }
        .rating-big-count { font-size: 0.8rem; color: var(--gray); }
        .rating-bars { display: flex; flex-direction: column; gap: 6px; }
        .rating-bar-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
        }
        .rating-bar-label { color: var(--gray); white-space: nowrap; width: 24px; text-align: right; }
        .rating-bar-track {
            flex: 1;
            height: 6px;
            background: var(--border);
            border-radius: 3px;
            overflow: hidden;
        }
        .rating-bar-fill {
            height: 100%;
            background: #F59E0B;
            border-radius: 3px;
        }
        .rating-bar-count { color: var(--gray); width: 20px; }

        /* Review form */
        .review-form-wrap {
            background: var(--light);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .review-form-title {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--dark);
            margin-bottom: 14px;
        }
        .star-picker { display: flex; gap: 4px; margin-bottom: 14px; }
        .star-picker i {
            font-size: 1.6rem;
            color: var(--border);
            cursor: pointer;
            transition: color 0.15s;
        }
        .star-picker i.active,
        .star-picker i:hover { color: #F59E0B; }
        .review-textarea {
            width: 100%;
            background: #fff;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            font-size: 0.875rem;
            font-family: 'Nunito', sans-serif;
            resize: vertical;
            min-height: 100px;
            outline: none;
            transition: border-color 0.2s;
        }
        .review-textarea:focus { border-color: var(--primary); }
        .btn-review-submit {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 24px;
            font-size: 0.875rem;
            font-weight: 700;
            font-family: 'Nunito', sans-serif;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.2s;
        }
        .btn-review-submit:hover { background: var(--primary-dark); }

        /* Review items */
        .review-item {
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
        }
        .review-item:last-child { border-bottom: none; }
        .review-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }
        .reviewer-avatar {
            width: 36px; height: 36px;
            background: #EEF4FF;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--primary);
            flex-shrink: 0;
        }
        .reviewer-name { font-weight: 700; font-size: 0.875rem; color: var(--dark); }
        .review-date { font-size: 0.75rem; color: var(--gray); margin-left: auto; }
        .review-stars { color: #F59E0B; font-size: 0.82rem; margin-bottom: 6px; }
        .review-comment { font-size: 0.875rem; color: #374151; line-height: 1.6; }

        /* ── RELATED ── */
        .related-section {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
        }
        .related-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-top: 20px;
        }
        .r-card {
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .r-card:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,87,255,0.08); }
        .r-img {
            width: 100%; aspect-ratio: 1;
            background: var(--light);
            display: flex; align-items: center; justify-content: center;
            font-size: 3rem; overflow: hidden;
        }
        .r-img img { width: 100%; height: 100%; object-fit: cover; }
        .r-body { padding: 10px; }
        .r-name { font-size: 0.82rem; font-weight: 700; color: var(--dark); margin-bottom: 4px;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .r-price { font-size: 0.9rem; font-weight: 800; color: #EF4444; }

        /* Alert */
        .alert-success-sm {
            background: #F0FDF4; border: 1px solid #BBF7D0; color: #16A34A;
            border-radius: 8px; padding: 10px 14px; font-size: 0.85rem;
            margin-bottom: 14px; display: flex; align-items: center; gap: 8px;
        }
        .alert-error-sm {
            background: #FEF2F2; border: 1px solid #FECACA; color: #DC2626;
            border-radius: 8px; padding: 10px 14px; font-size: 0.85rem;
            margin-bottom: 14px; display: flex; align-items: center; gap: 8px;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .detail-main { grid-template-columns: 1fr; gap: 24px; }
            .related-grid { grid-template-columns: repeat(2, 1fr); }
            .detail-guarantees { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 600px) {
            .detail-wrap { padding: 12px; }
            .detail-main { padding: 16px; }
            .detail-name { font-size: 1.2rem; }
            .detail-price { font-size: 1.4rem; }
            .detail-actions { flex-direction: column; }
            .rating-summary { grid-template-columns: 1fr; }
            .rating-big { border-right: none; border-bottom: 1px solid var(--border); padding-right: 0; padding-bottom: 16px; }
            .tab-btn { padding: 12px 16px; font-size: 0.82rem; }
        }
    </style>
</head>
    <body>

    <!-- ══ TOPBAR ══ -->
    <div class="topbar">
        <div class="topbar-inner">
            <span class="topbar-item"><i class="bi bi-shield-check"></i> Hàng chính hãng 100%</span>
            <span class="topbar-item"><i class="bi bi-truck"></i> Miễn phí ship đơn từ 500K</span>
            <span class="topbar-item"><i class="bi bi-arrow-repeat"></i> Đổi trả trong 30 ngày</span>
            <span class="topbar-item"><i class="bi bi-headset"></i> Hotline: 1800 2097</span>
        </div>
    </div>

    <!-- ══ NAVBAR ══ -->
    <nav class="navbar">
        <div class="navbar-inner">
            <a href="../index.php" class="navbar-brand">Phone<span>Store</span></a>
            <div class="search-wrap">
                <input type="text" id="searchInput" placeholder="Bạn muốn tìm gì hôm nay?"
                    onkeydown="if(event.key==='Enter') window.location='products.php?q='+encodeURIComponent(this.value)">
                <button class="search-btn" onclick="window.location='products.php?q='+encodeURIComponent(document.getElementById('searchInput').value)">
                    <i class="bi bi-search"></i>
                </button>
            </div>
            <ul class="nav-links">
                <li><a href="products.php"><i class="bi bi-phone"></i> Sản phẩm</a></li>
                <li><a href="contact.php"><i class="bi bi-headset"></i> Liên hệ</a></li>
            </ul>
            <a href="cart.php" class="cart-link">
                <i class="bi bi-bag" style="font-size:1.1rem"></i> Giỏ hàng
                <?php if ($cart_count > 0): ?>
                    <span class="cart-badge"><?= $cart_count ?></span>
                <?php endif; ?>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="user-dropdown">
                <button class="btn-login user-dropdown-btn">
                    <i class="bi bi-person-circle"></i>
                    <?= htmlspecialchars($_SESSION['full_name']) ?>
                    <i class="bi bi-chevron-down" style="font-size:0.65rem"></i>
                </button>
                <div class="user-dropdown-menu">
                    <div class="user-dropdown-header">
                        <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
                        <div>
                            <div class="user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                            <div class="user-role"><?= $_SESSION['role'] === 'admin' ? 'Quản trị viên' : 'Khách hàng' ?></div>
                        </div>
                    </div>
                    <div class="user-dropdown-divider"></div>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="../admin/index.php" class="user-dropdown-item"><i class="bi bi-speedometer2"></i> Trang quản trị</a>
                    <?php endif; ?>
                    <a href="orders.php" class="user-dropdown-item"><i class="bi bi-bag-check"></i> Đơn hàng của tôi</a>
                    <a href="profile.php" class="user-dropdown-item"><i class="bi bi-gear"></i> Cài đặt tài khoản</a>
                    <div class="user-dropdown-divider"></div>
                    <a href="../auth/logout.php" class="user-dropdown-item user-dropdown-logout"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
                </div>
            </div>
            <?php else: ?>
                <a href="../auth/login.php" class="btn-login"><i class="bi bi-person"></i> Đăng nhập</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- ══ BREADCRUMB ══ -->
    <div class="breadcrumb-bar">
        <div style="max-width:1280px;margin:0 auto;padding:0 24px;">
            <a href="../index.php">Trang chủ</a>
            <i class="bi bi-chevron-right" style="font-size:0.7rem;margin:0 6px;"></i>
            <a href="products.php">Sản phẩm</a>
            <?php if ($p['cat_name']): ?>
            <i class="bi bi-chevron-right" style="font-size:0.7rem;margin:0 6px;"></i>
            <a href="products.php?category=<?= $p['cat_slug'] ?>"><?= htmlspecialchars($p['cat_name']) ?></a>
            <?php endif; ?>
            <i class="bi bi-chevron-right" style="font-size:0.7rem;margin:0 6px;"></i>
            <span style="color:var(--dark);font-weight:600;"><?= htmlspecialchars($p['name']) ?></span>
        </div>
    </div>

    <div class="detail-wrap">

        <!-- ══════ MAIN DETAIL ══════ -->
        <div class="detail-main">

            <!-- LEFT — Images -->
            <div>
                <div class="detail-img-main" id="mainImg">
                    <?php if ($p['thumbnail']): ?>
                        <img src="../assets/images/products/<?= htmlspecialchars($p['thumbnail']) ?>"
                            alt="<?= htmlspecialchars($p['name']) ?>" id="mainImgEl">
                    <?php else: ?>
                        📱
                    <?php endif; ?>
                </div>

                <div class="detail-img-thumbs">
                    <!-- Thumbnail chính -->
                    <?php if ($p['thumbnail']): ?>
                    <div class="thumb-item active" onclick="switchImg(this, '../assets/images/products/<?= htmlspecialchars($p['thumbnail']) ?>')">
                        <img src="../assets/images/products/<?= htmlspecialchars($p['thumbnail']) ?>" alt="">
                    </div>
                    <?php endif; ?>
                    <!-- Ảnh phụ từ product_images -->
                    <?php while ($img = $images->fetch_assoc()): ?>
                    <div class="thumb-item" onclick="switchImg(this, '../assets/images/products/<?= htmlspecialchars($img['image_url']) ?>')">
                        <img src="../assets/images/products/<?= htmlspecialchars($img['image_url']) ?>" alt="">
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- RIGHT — Info -->
            <div>
                <div class="detail-brand"><?= htmlspecialchars($p['brand_name'] ?? '') ?></div>
                <h1 class="detail-name"><?= htmlspecialchars($p['name']) ?></h1>

                <!-- Rating -->
                <div class="detail-rating">
                    <div class="stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star<?= $i <= round($avg_rating) ? '-fill' : ($i - $avg_rating < 1 ? '-half' : '') ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="rating-num"><?= $avg_rating ?: 'Chưa có' ?></span>
                    <span class="rating-count">(<?= $review_count ?> đánh giá)</span>
                    <?php if ($p['stock'] > 0): ?>
                        <span style="margin-left:auto;background:#F0FDF4;color:#16A34A;border:1px solid #BBF7D0;font-size:0.75rem;font-weight:700;padding:3px 10px;border-radius:100px;">
                            <i class="bi bi-check-circle-fill"></i> Còn hàng
                        </span>
                    <?php else: ?>
                        <span style="margin-left:auto;background:#FEF2F2;color:#EF4444;border:1px solid #FECACA;font-size:0.75rem;font-weight:700;padding:3px 10px;border-radius:100px;">
                            Hết hàng
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Price -->
                <div class="detail-price-wrap">
                    <span class="detail-price"><?= number_format($p['price'], 0, ',', '.') ?>đ</span>
                    <?php if ($p['old_price']): ?>
                    <div class="detail-price-row">
                        <span class="detail-old-price"><?= number_format($p['old_price'], 0, ',', '.') ?>đ</span>
                        <?php if ($p['discount_percent'] > 0): ?>
                        <span class="detail-discount-badge">Tiết kiệm <?= $p['discount_percent'] ?>%</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- RAM variants -->
                <?php if ($p['ram']): ?>
                <div class="detail-variants">
                    <div class="variant-label">RAM</div>
                    <div class="variant-options">
                        <button class="variant-btn active"><?= htmlspecialchars($p['ram']) ?></button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Storage variants -->
                <?php if ($p['storage']): ?>
                <div class="detail-variants">
                    <div class="variant-label">Bộ nhớ</div>
                    <div class="variant-options">
                        <button class="variant-btn active"><?= htmlspecialchars($p['storage']) ?></button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quantity -->
                <div class="qty-wrap">
                    <span class="qty-label">Số lượng</span>
                    <div class="qty-control">
                        <button class="qty-btn" onclick="changeQty(-1)">−</button>
                        <input type="number" class="qty-input" id="qtyInput" value="1" min="1" max="<?= $p['stock'] ?>">
                        <button class="qty-btn" onclick="changeQty(1)">+</button>
                    </div>
                    <span class="qty-stock">Kho: <span><?= $p['stock'] ?></span></span>
                </div>

                <!-- Actions -->
                <div class="detail-actions">
                    <a href="../api/add_to_cart.php?product_id=<?= $p['id'] ?>&qty=1" class="btn-add-cart-detail"
                    id="btnAddCart">
                        <i class="bi bi-bag-plus"></i> Thêm vào giỏ
                    </a>
                    <a href="../pages/checkout.php?buy_now=<?= $p['id'] ?>" class="btn-buy-now">
                        <i class="bi bi-lightning-fill"></i> Mua ngay
                    </a>
                </div>

                <!-- Guarantees -->
                <div class="detail-guarantees">
                    <div class="guarantee-item"><i class="bi bi-patch-check-fill"></i> Hàng chính hãng 100%</div>
                    <div class="guarantee-item"><i class="bi bi-shield-fill-check"></i> Bảo hành 12 tháng</div>
                    <div class="guarantee-item"><i class="bi bi-arrow-repeat"></i> Đổi trả trong 30 ngày</div>
                    <div class="guarantee-item"><i class="bi bi-truck"></i> Miễn phí vận chuyển</div>
                </div>
            </div>

        </div>

        <!-- ══════ TABS: Mô tả / Thông số ══════ -->
        <div class="detail-tabs">
            <div class="tab-nav">
                <button class="tab-btn active" onclick="switchTab(this,'tab-desc')">
                    <i class="bi bi-file-text"></i> Mô tả sản phẩm
                </button>
                <button class="tab-btn" onclick="switchTab(this,'tab-specs')">
                    <i class="bi bi-list-columns"></i> Thông số kỹ thuật
                </button>
            </div>
            <div class="tab-content">

                <!-- Tab Mô tả -->
                <div class="tab-pane active" id="tab-desc">
                    <?php if ($p['description']): ?>
                        <?= nl2br(htmlspecialchars($p['description'])) ?>
                    <?php else: ?>
                        <p style="color:var(--gray);">Chưa có mô tả cho sản phẩm này.</p>
                    <?php endif; ?>
                </div>

                <!-- Tab Thông số -->
                <div class="tab-pane" id="tab-specs">
                    <table class="specs-table">
                        <tr><td>Thương hiệu</td><td><?= htmlspecialchars($p['brand_name'] ?? '—') ?></td></tr>
                        <tr><td>Danh mục</td><td><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td></tr>
                        <?php if ($p['ram']): ?>
                        <tr><td>RAM</td><td><?= htmlspecialchars($p['ram']) ?></td></tr>
                        <?php endif; ?>
                        <?php if ($p['storage']): ?>
                        <tr><td>Bộ nhớ trong</td><td><?= htmlspecialchars($p['storage']) ?></td></tr>
                        <?php endif; ?>
                        <tr><td>Tình trạng</td><td><?= $p['stock'] > 0 ? "<span style='color:#16A34A;font-weight:700'>Còn hàng ({$p['stock']})</span>" : "<span style='color:#EF4444;font-weight:700'>Hết hàng</span>" ?></td></tr>
                        <tr><td>Giá bán</td><td><strong style="color:#EF4444"><?= number_format($p['price'],0,',','.') ?>đ</strong></td></tr>
                        <?php if ($p['old_price']): ?>
                        <tr><td>Giá gốc</td><td><s><?= number_format($p['old_price'],0,',','.') ?>đ</s></td></tr>
                        <?php endif; ?>
                    </table>
                </div>

            </div>
        </div>

        <!-- ══════ ĐÁNH GIÁ & BÌNH LUẬN ══════ -->
        <div class="reviews-section">
            <div class="reviews-section-title">Đánh giá & Bình luận</div>

            <!-- Rating summary -->
            <div class="rating-summary">
                <div class="rating-big">
                    <div class="rating-big-num"><?= $avg_rating ?: '0' ?></div>
                    <div class="rating-big-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star<?= $i <= round($avg_rating) ? '-fill' : '' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="rating-big-count"><?= $review_count ?> đánh giá</div>
                </div>
                <div class="rating-bars">
                    <?php for ($star = 5; $star >= 1; $star--): ?>
                    <?php $pct = $review_count > 0 ? round(($star_counts[$star] / $review_count) * 100) : 0; ?>
                    <div class="rating-bar-row">
                        <span class="rating-bar-label"><?= $star ?>★</span>
                        <div class="rating-bar-track">
                            <div class="rating-bar-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span class="rating-bar-count"><?= $star_counts[$star] ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Form đánh giá -->
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="review-form-wrap">
                <div class="review-form-title">✍️ Viết đánh giá của bạn</div>

                <?php if ($review_error): ?>
                <div class="alert-error-sm"><i class="bi bi-exclamation-circle-fill"></i> <?= $review_error ?></div>
                <?php endif; ?>
                <?php if ($review_success): ?>
                <div class="alert-success-sm"><i class="bi bi-check-circle-fill"></i> <?= $review_success ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div style="font-size:0.82rem;font-weight:700;color:var(--dark);margin-bottom:6px;">Chọn số sao</div>
                    <div class="star-picker" id="starPicker">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star-fill" data-star="<?= $i ?>" onclick="setStar(<?= $i ?>)"></i>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" value="0">
                    <textarea name="comment" class="review-textarea"
                            placeholder="Chia sẻ trải nghiệm của bạn về sản phẩm này..."></textarea>
                    <button type="submit" name="submit_review" class="btn-review-submit">
                        <i class="bi bi-send-fill"></i> Gửi đánh giá
                    </button>
                </form>
            </div>
            <?php else: ?>
            <div style="background:var(--light);border-radius:10px;padding:16px;margin-bottom:20px;text-align:center;font-size:0.875rem;color:var(--gray);">
                <a href="../auth/login.php" style="color:var(--primary);font-weight:700;">Đăng nhập</a>
                để viết đánh giá sản phẩm
            </div>
            <?php endif; ?>

            <!-- Danh sách đánh giá -->
            <?php if (!empty($review_list)): ?>
            <div>
                <?php foreach ($review_list as $rv): ?>
                <div class="review-item">
                    <div class="review-header">
                        <div class="reviewer-avatar">
                            <?= mb_strtoupper(mb_substr($rv['full_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="reviewer-name"><?= htmlspecialchars($rv['full_name']) ?></div>
                            <div class="review-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star<?= $i <= $rv['rating'] ? '-fill' : '' ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="review-date">
                            <?= date('d/m/Y', strtotime($rv['created_at'])) ?>
                        </div>
                    </div>
                    <div class="review-comment"><?= nl2br(htmlspecialchars($rv['comment'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="text-align:center;padding:32px;color:var(--gray);">
                <div style="font-size:2.5rem;margin-bottom:8px;">💬</div>
                <p>Chưa có đánh giá nào. Hãy là người đầu tiên!</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- ══════ SẢN PHẨM LIÊN QUAN ══════ -->
        <?php if ($related_products->num_rows > 0): ?>
        <div class="related-section">
            <div class="reviews-section-title">Sản phẩm liên quan</div>
            <div class="related-grid">
                <?php while ($rp = $related_products->fetch_assoc()): ?>
                <a href="product_detail.php?id=<?= $rp['id'] ?>" class="r-card">
                    <div class="r-img">
                        <?php if ($rp['thumbnail']): ?>
                            <img src="../assets/images/products/<?= htmlspecialchars($rp['thumbnail']) ?>"
                                alt="<?= htmlspecialchars($rp['name']) ?>">
                        <?php else: ?>
                            📱
                        <?php endif; ?>
                    </div>
                    <div class="r-body">
                        <div class="r-name"><?= htmlspecialchars($rp['name']) ?></div>
                        <div class="r-price"><?= number_format($rp['price'],0,',','.') ?>đ</div>
                    </div>
                </a>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ══ FOOTER ══ -->
    <footer style="margin-top:48px">
        <div class="container-main">
            <div class="footer-bottom" style="border-top:none;padding-top:0">
                <span>© 2024 PhoneStore. All rights reserved.</span>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Switch ảnh thumbnail
    function switchImg(el, src) {
        document.querySelectorAll('.thumb-item').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
        const main = document.getElementById('mainImgEl');
        if (main) main.src = src;
    }

    // Tab
    function switchTab(btn, tabId) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(tabId).classList.add('active');
    }

    // Star picker
    function setStar(n) {
        document.getElementById('ratingInput').value = n;
        document.querySelectorAll('#starPicker i').forEach((el, i) => {
            el.classList.toggle('active', i < n);
        });
    }

    // Quantity
    function changeQty(delta) {
        const input = document.getElementById('qtyInput');
        const max   = parseInt(input.max) || 99;
        let val = parseInt(input.value) + delta;
        val = Math.max(1, Math.min(max, val));
        input.value = val;
        // Cập nhật link thêm vào giỏ
        const btn = document.getElementById('btnAddCart');
        if (btn) {
            const url = new URL(btn.href);
            url.searchParams.set('qty', val);
            btn.href = url.toString();
        }
    }

    // User dropdown
    document.querySelector('.user-dropdown-btn')?.addEventListener('click', function(e) {
        e.stopPropagation();
        document.querySelector('.user-dropdown-menu').classList.toggle('show');
    });
    document.addEventListener('click', function() {
        document.querySelector('.user-dropdown-menu')?.classList.remove('show');
    });
    </script>
    </body>
</html>