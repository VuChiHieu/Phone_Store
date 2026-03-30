<?php
    session_start();
    require_once '../config.php';

    // ── PARAMS ──────────────────────────────────────────
    $q         = trim($_GET['q'] ?? '');
    $category  = trim($_GET['category'] ?? '');
    $brands    = $_GET['brand'] ?? [];          // array
    $price_min = (int)($_GET['price_min'] ?? 0);
    $price_max = (int)($_GET['price_max'] ?? 0);
    $rams      = $_GET['ram'] ?? [];            // array
    $storages  = $_GET['storage'] ?? [];        // array
    $sort      = $_GET['sort'] ?? 'newest';
    $featured  = $_GET['featured'] ?? '';
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $per_page  = 12;

    // ── BUILD QUERY ─────────────────────────────────────
    $where  = ["1=1"];
    $params = [];
    $types  = "";

    if ($q !== '') {
        $where[]  = "(p.name LIKE ? OR b.name LIKE ?)";
        $like     = "%$q%";
        $params[] = $like; $params[] = $like;
        $types   .= "ss";
    }
    if ($category !== '') {
        $where[]  = "c.slug = ?";
        $params[] = $category;
        $types   .= "s";
    }
    if ($featured === '1') {
        $where[] = "p.is_featured = 1";
    }
    if (!empty($brands)) {
        $ph       = implode(',', array_fill(0, count($brands), '?'));
        $where[]  = "b.name IN ($ph)";
        foreach ($brands as $br) { $params[] = $br; $types .= "s"; }
    }
    if ($price_min > 0) {
        $where[]  = "p.price >= ?";
        $params[] = $price_min;
        $types   .= "i";
    }
    if ($price_max > 0) {
        $where[]  = "p.price <= ?";
        $params[] = $price_max;
        $types   .= "i";
    }
    if (!empty($rams)) {
        $ph       = implode(',', array_fill(0, count($rams), '?'));
        $where[]  = "p.ram IN ($ph)";
        foreach ($rams as $r) { $params[] = $r; $types .= "s"; }
    }
    if (!empty($storages)) {
        $ph       = implode(',', array_fill(0, count($storages), '?'));
        $where[]  = "p.storage IN ($ph)";
        foreach ($storages as $s) { $params[] = $s; $types .= "s"; }
    }

    $where_sql = implode(' AND ', $where);

    $order_sql = match($sort) {
        'price_asc'  => "p.price ASC",
        'price_desc' => "p.price DESC",
        'discount'   => "p.discount_percent DESC",
        default      => "p.created_at DESC",
    };

    // Đếm tổng
    $count_sql = "SELECT COUNT(*) as total
                FROM products p
                LEFT JOIN brands b ON p.brand_id = b.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE $where_sql";
    $stmt = $conn->prepare($count_sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total_rows  = $stmt->get_result()->fetch_assoc()['total'];
    $total_pages = max(1, ceil($total_rows / $per_page));
    $page        = min($page, $total_pages);
    $offset      = ($page - 1) * $per_page;

    // Lấy sản phẩm
    $data_sql = "SELECT p.*, b.name AS brand_name, c.name AS cat_name, c.slug AS cat_slug
                FROM products p
                LEFT JOIN brands b ON p.brand_id = b.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE $where_sql
                ORDER BY $order_sql
                LIMIT ? OFFSET ?";
    $all_params   = $params;
    $all_params[] = $per_page;
    $all_params[] = $offset;
    $all_types    = $types . "ii";

    $stmt = $conn->prepare($data_sql);
    $stmt->bind_param($all_types, ...$all_params);
    $stmt->execute();
    $products = $stmt->get_result();

    // ── SIDEBAR DATA ────────────────────────────────────
    $all_brands     = $conn->query("SELECT DISTINCT b.name FROM brands b INNER JOIN products p ON p.brand_id = b.id ORDER BY b.name");
    $all_categories = $conn->query("SELECT * FROM categories ORDER BY id");
    $all_rams       = $conn->query("SELECT DISTINCT ram FROM products WHERE ram IS NOT NULL AND ram != '' ORDER BY ram");
    $all_storages   = $conn->query("SELECT DISTINCT storage FROM products WHERE storage IS NOT NULL AND storage != '' ORDER BY storage");

    // Đếm giỏ hàng
    $cart_count = 0;
    if (isset($_SESSION['user_id'])) {
        $uid = $_SESSION['user_id'];
        $r   = $conn->query("SELECT SUM(quantity) AS total FROM cart WHERE user_id = $uid");
        $cart_count = $r->fetch_assoc()['total'] ?? 0;
    }

    // Helper: giữ params URL khi chuyển trang
    function build_url($extra = []) {
        $params = array_merge($_GET, $extra);
        unset($params['page']);
        $base = '?' . http_build_query($params);
        return $base;
    }
?>
<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sản phẩm - Phone Store</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
        <link rel="stylesheet" href="../assets/css/style.css">
        <style>
            /* ── PAGE LAYOUT ── */
            .products-page {
                max-width: 1280px;
                margin: 0 auto;
                padding: 24px 24px;
                display: grid;
                grid-template-columns: 260px 1fr;
                gap: 24px;
                align-items: start;
            }

            /* ── SIDEBAR ── */
            .sidebar {
                position: sticky;
                top: 88px;
            }
            .filter-card {
                background: #fff;
                border: 1px solid var(--border);
                border-radius: 14px;
                overflow: hidden;
                margin-bottom: 14px;
            }
            .filter-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 14px 16px;
                border-bottom: 1px solid var(--border);
                cursor: pointer;
            }
            .filter-header-title {
                font-weight: 700;
                font-size: 0.875rem;
                color: var(--dark);
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .filter-header-title i { color: var(--primary); }
            .filter-body { padding: 14px 16px; }

            .filter-check {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 5px 0;
                cursor: pointer;
                font-size: 0.875rem;
                color: var(--dark);
            }
            .filter-check input[type="checkbox"],
            .filter-check input[type="radio"] {
                width: 16px; height: 16px;
                accent-color: var(--primary);
                cursor: pointer;
                flex-shrink: 0;
            }
            .filter-check:hover { color: var(--primary); }

            .price-inputs {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                margin-top: 4px;
            }
            .price-input {
                background: var(--light);
                border: 1.5px solid var(--border);
                border-radius: 8px;
                padding: 8px 10px;
                font-size: 0.8rem;
                font-family: 'Nunito', sans-serif;
                width: 100%;
                outline: none;
                transition: border-color 0.2s;
            }
            .price-input:focus { border-color: var(--primary); }

            .btn-filter-apply {
                width: 100%;
                background: var(--primary);
                color: #fff;
                border: none;
                border-radius: 10px;
                padding: 10px;
                font-size: 0.875rem;
                font-weight: 700;
                font-family: 'Nunito', sans-serif;
                cursor: pointer;
                transition: background 0.2s;
                margin-top: 4px;
            }
            .btn-filter-apply:hover { background: var(--primary-dark); }
            .btn-filter-clear {
                width: 100%;
                background: transparent;
                color: var(--gray);
                border: 1.5px solid var(--border);
                border-radius: 10px;
                padding: 9px;
                font-size: 0.85rem;
                font-weight: 600;
                font-family: 'Nunito', sans-serif;
                cursor: pointer;
                transition: all 0.2s;
                margin-top: 8px;
                text-decoration: none;
                display: block;
                text-align: center;
            }
            .btn-filter-clear:hover { border-color: var(--danger); color: var(--danger); }

            /* ── MAIN CONTENT ── */
            .products-main {}

            .products-toolbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: #fff;
                border: 1px solid var(--border);
                border-radius: 12px;
                padding: 12px 16px;
                margin-bottom: 20px;
                gap: 12px;
                flex-wrap: wrap;
            }
            .products-count {
                font-size: 0.875rem;
                color: var(--gray);
            }
            .products-count strong { color: var(--dark); }

            /* Active filters */
            .active-filters {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                flex: 1;
            }
            .filter-tag {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                background: #EEF4FF;
                color: var(--primary);
                border: 1px solid #C7D9FF;
                border-radius: 100px;
                padding: 3px 10px;
                font-size: 0.75rem;
                font-weight: 600;
            }

            .sort-wrap {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 0.85rem;
                color: var(--gray);
                flex-shrink: 0;
            }
            .sort-select {
                background: var(--light);
                border: 1.5px solid var(--border);
                border-radius: 8px;
                padding: 7px 12px;
                font-size: 0.85rem;
                font-family: 'Nunito', sans-serif;
                color: var(--dark);
                outline: none;
                cursor: pointer;
                transition: border-color 0.2s;
            }
            .sort-select:focus { border-color: var(--primary); }

            /* Product grid */
            .products-grid-4 {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 16px;
                margin-bottom: 32px;
            }

            /* Product card */
            .p-card {
                border: 1px solid var(--border);
                border-radius: 12px;
                overflow: hidden;
                background: #fff;
                transition: all 0.25s;
                position: relative;
                display: flex;
                flex-direction: column;
            }
            .p-card:hover {
                border-color: var(--primary);
                box-shadow: 0 8px 24px rgba(0,87,255,0.1);
                transform: translateY(-3px);
            }
            .p-badge {
                position: absolute;
                top: 10px; left: 10px;
                background: var(--danger);
                color: #fff;
                font-size: 0.68rem;
                font-weight: 700;
                padding: 3px 8px;
                border-radius: 6px;
                z-index: 1;
            }
            .p-img {
                width: 100%; aspect-ratio: 1;
                background: var(--light);
                display: flex; align-items: center; justify-content: center;
                font-size: 3.5rem;
                overflow: hidden;
            }
            .p-img img {
                width: 100%; height: 100%;
                object-fit: cover;
                transition: transform 0.3s;
            }
            .p-card:hover .p-img img { transform: scale(1.04); }
            .p-body {
                padding: 12px;
                display: flex;
                flex-direction: column;
                flex: 1;
            }
            .p-brand {
                font-size: 0.68rem;
                font-weight: 700;
                color: var(--primary);
                text-transform: uppercase;
                letter-spacing: 1px;
                margin-bottom: 4px;
            }
            .p-name {
                font-weight: 700;
                font-size: 0.875rem;
                color: var(--dark);
                margin-bottom: 8px;
                line-height: 1.4;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
                min-height: 2.5em;
                flex: 1;
            }
            .p-specs {
                display: flex;
                gap: 5px;
                flex-wrap: wrap;
                margin-bottom: 8px;
            }
            .p-spec-tag {
                background: var(--light);
                border: 1px solid var(--border);
                color: var(--gray);
                font-size: 0.68rem;
                font-weight: 600;
                padding: 2px 7px;
                border-radius: 5px;
            }
            .p-price-wrap { margin-bottom: 10px; }
            .p-price {
                font-size: 1rem;
                font-weight: 800;
                color: var(--danger);
                display: block;
            }
            .p-old-price {
                font-size: 0.78rem;
                color: var(--gray);
                text-decoration: line-through;
            }
            .btn-cart {
                width: 100%;
                background: var(--primary);
                color: #fff;
                border: none;
                border-radius: 8px;
                padding: 9px;
                font-size: 0.82rem;
                font-weight: 700;
                font-family: 'Nunito', sans-serif;
                cursor: pointer;
                transition: background 0.2s;
                text-decoration: none;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
            }
            .btn-cart:hover { background: var(--primary-dark); color: #fff; }

            /* Highlight search term */
            .highlight { background: #FEF08A; border-radius: 3px; padding: 0 2px; }

            /* Empty state */
            .empty-state {
                text-align: center;
                padding: 60px 20px;
                grid-column: 1 / -1;
            }
            .empty-state-icon { font-size: 4rem; margin-bottom: 16px; }
            .empty-state h3 { font-weight: 700; color: var(--dark); margin-bottom: 8px; }
            .empty-state p { color: var(--gray); font-size: 0.9rem; }

            /* ── PAGINATION ── */
            .pagination-wrap {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
            }
            .page-btn {
                min-width: 38px; height: 38px;
                display: flex; align-items: center; justify-content: center;
                border: 1.5px solid var(--border);
                border-radius: 8px;
                font-size: 0.875rem;
                font-weight: 600;
                color: var(--dark);
                text-decoration: none;
                transition: all 0.2s;
                padding: 0 10px;
            }
            .page-btn:hover { border-color: var(--primary); color: var(--primary); }
            .page-btn.active {
                background: var(--primary);
                border-color: var(--primary);
                color: #fff;
            }
            .page-btn.disabled {
                opacity: 0.4;
                pointer-events: none;
            }

            /* ── RESPONSIVE ── */
            @media (max-width: 1100px) {
                .products-grid-4 { grid-template-columns: repeat(3, 1fr); }
            }
            @media (max-width: 900px) {
                .products-page { grid-template-columns: 1fr; }
                .sidebar { position: static; }
                .filter-card { display: none; }
                .filter-card.show { display: block; }
                .mobile-filter-btn { display: flex !important; }
            }
            @media (max-width: 600px) {
                .products-grid-4 { grid-template-columns: repeat(2, 1fr); }
                .products-page { padding: 16px; }
            }

            /* Mobile filter toggle */
            .mobile-filter-btn {
                display: none;
                align-items: center;
                gap: 6px;
                background: var(--light);
                border: 1.5px solid var(--border);
                border-radius: 8px;
                padding: 8px 14px;
                font-size: 0.85rem;
                font-weight: 600;
                font-family: 'Nunito', sans-serif;
                color: var(--dark);
                cursor: pointer;
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

            <div class="cat-dropdown">
                <button class="cat-dropdown-btn">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                    <span>Danh mục</span>
                    <i class="bi bi-chevron-down" style="font-size:0.7rem"></i>
                </button>
                <div class="cat-dropdown-menu">
                    <?php
                    $i = 0;
                    $cat_bi_icons = ['bi-phone','bi-headphones','bi-plug','bi-phone-flip'];
                    $all_categories->data_seek(0);
                    while ($cat = $all_categories->fetch_assoc()):
                        $icon = $cat_bi_icons[$i % count($cat_bi_icons)]; $i++;
                    ?>
                    <a href="products.php?category=<?= $cat['slug'] ?>" class="cat-dropdown-item">
                        <i class="bi <?= $icon ?> cat-icon"></i>
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                    <?php endwhile; ?>
                    <a href="products.php" class="cat-dropdown-item" style="border-top:1px solid #f0f0f0;margin-top:4px;color:#0057FF">
                        <i class="bi bi-grid cat-icon"></i> Xem tất cả
                    </a>
                </div>
            </div>

            <div class="search-wrap">
                <input type="text" id="searchInput" placeholder="Bạn muốn tìm gì hôm nay?"
                    value="<?= htmlspecialchars($q) ?>"
                    onkeydown="if(event.key==='Enter') window.location='products.php?q='+encodeURIComponent(this.value)">
                <button class="search-btn" onclick="window.location='products.php?q='+encodeURIComponent(document.getElementById('searchInput').value)">
                    <i class="bi bi-search"></i>
                </button>
            </div>

            <ul class="nav-links">
                <li><a href="products.php" class="active"><i class="bi bi-phone"></i> Sản phẩm</a></li>
                <li><a href="contact.php"><i class="bi bi-headset"></i> Liên hệ</a></li>
            </ul>

            <a href="cart.php" class="cart-link">
                <i class="bi bi-bag" style="font-size:1.1rem"></i>
                Giỏ hàng
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
                    <a href="../admin/index.php" class="user-dropdown-item">
                        <i class="bi bi-speedometer2"></i> Trang quản trị
                    </a>
                    <?php endif; ?>
                    <a href="orders.php" class="user-dropdown-item">
                        <i class="bi bi-bag-check"></i> Đơn hàng của tôi
                    </a>
                    <a href="profile.php" class="user-dropdown-item">
                        <i class="bi bi-gear"></i> Cài đặt tài khoản
                    </a>
                    <div class="user-dropdown-divider"></div>
                    <a href="../auth/logout.php" class="user-dropdown-item user-dropdown-logout">
                        <i class="bi bi-box-arrow-right"></i> Đăng xuất
                    </a>
                </div>
            </div>
            <?php else: ?>
                <a href="../auth/login.php" class="btn-login">
                    <i class="bi bi-person"></i> Đăng nhập
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- ══ BREADCRUMB ══ -->
    <div style="background:#fff;border-bottom:1px solid var(--border);padding:10px 0;">
        <div style="max-width:1280px;margin:0 auto;padding:0 24px;font-size:0.82rem;color:var(--gray);">
            <a href="../index.php" style="color:var(--gray);text-decoration:none;">Trang chủ</a>
            <i class="bi bi-chevron-right" style="font-size:0.7rem;margin:0 6px;"></i>
            <span style="color:var(--dark);font-weight:600;">Sản phẩm<?= $q ? " — \"$q\"" : '' ?></span>
        </div>
    </div>

    <!-- ══ MAIN ══ -->
    <div class="products-page">

        <!-- ══════ SIDEBAR ══════ -->
        <aside class="sidebar">

            <!-- Mobile toggle -->
            <button class="mobile-filter-btn" onclick="toggleMobileFilter()">
                <i class="bi bi-sliders"></i> Bộ lọc
            </button>

            <form method="GET" action="products.php" id="filterForm">
                <?php if ($q): ?>
                    <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                <?php endif; ?>

                <!-- Danh mục -->
                <div class="filter-card">
                    <div class="filter-header">
                        <span class="filter-header-title"><i class="bi bi-grid-fill"></i> Danh mục</span>
                    </div>
                    <div class="filter-body">
                        <label class="filter-check">
                            <input type="radio" name="category" value=""
                                <?= $category === '' ? 'checked' : '' ?>> Tất cả
                        </label>
                        <?php
                        $all_categories->data_seek(0);
                        while ($cat = $all_categories->fetch_assoc()):
                        ?>
                        <label class="filter-check">
                            <input type="radio" name="category" value="<?= $cat['slug'] ?>"
                                <?= $category === $cat['slug'] ? 'checked' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </label>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Hãng -->
                <div class="filter-card">
                    <div class="filter-header">
                        <span class="filter-header-title"><i class="bi bi-building"></i> Hãng</span>
                    </div>
                    <div class="filter-body">
                        <?php while ($br = $all_brands->fetch_assoc()): ?>
                        <label class="filter-check">
                            <input type="checkbox" name="brand[]" value="<?= htmlspecialchars($br['name']) ?>"
                                <?= in_array($br['name'], $brands) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($br['name']) ?>
                        </label>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Khoảng giá -->
                <div class="filter-card">
                    <div class="filter-header">
                        <span class="filter-header-title"><i class="bi bi-cash-stack"></i> Khoảng giá</span>
                    </div>
                    <div class="filter-body">
                        <div class="price-inputs">
                            <input type="number" name="price_min" class="price-input"
                                placeholder="Từ" value="<?= $price_min ?: '' ?>">
                            <input type="number" name="price_max" class="price-input"
                                placeholder="Đến" value="<?= $price_max ?: '' ?>">
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:10px;">
                            <button type="button" class="price-preset" onclick="setPrice(0,5000000)">Dưới 5tr</button>
                            <button type="button" class="price-preset" onclick="setPrice(5000000,10000000)">5-10tr</button>
                            <button type="button" class="price-preset" onclick="setPrice(10000000,20000000)">10-20tr</button>
                            <button type="button" class="price-preset" onclick="setPrice(20000000,0)">Trên 20tr</button>
                        </div>
                    </div>
                </div>

                <!-- RAM -->
                <?php if ($all_rams->num_rows > 0): ?>
                <div class="filter-card">
                    <div class="filter-header">
                        <span class="filter-header-title"><i class="bi bi-memory"></i> RAM</span>
                    </div>
                    <div class="filter-body">
                        <?php while ($r = $all_rams->fetch_assoc()): ?>
                        <label class="filter-check">
                            <input type="checkbox" name="ram[]" value="<?= $r['ram'] ?>"
                                <?= in_array($r['ram'], $rams) ? 'checked' : '' ?>>
                            <?= $r['ram'] ?>
                        </label>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Bộ nhớ -->
                <?php if ($all_storages->num_rows > 0): ?>
                <div class="filter-card">
                    <div class="filter-header">
                        <span class="filter-header-title"><i class="bi bi-device-hdd"></i> Bộ nhớ</span>
                    </div>
                    <div class="filter-body">
                        <?php while ($s = $all_storages->fetch_assoc()): ?>
                        <label class="filter-check">
                            <input type="checkbox" name="storage[]" value="<?= $s['storage'] ?>"
                                <?= in_array($s['storage'], $storages) ? 'checked' : '' ?>>
                            <?= $s['storage'] ?>
                        </label>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn-filter-apply">
                    <i class="bi bi-funnel-fill"></i> Áp dụng bộ lọc
                </button>
                <a href="products.php<?= $q ? '?q='.urlencode($q) : '' ?>" class="btn-filter-clear">
                    <i class="bi bi-x-circle"></i> Xóa bộ lọc
                </a>

            </form>
        </aside>

        <!-- ══════ MAIN CONTENT ══════ -->
        <div class="products-main">

            <!-- Toolbar -->
            <div class="products-toolbar">
                <div class="products-count">
                    Tìm thấy <strong><?= number_format($total_rows) ?></strong> sản phẩm
                    <?php if ($q): ?>
                        cho "<strong><?= htmlspecialchars($q) ?></strong>"
                    <?php endif; ?>
                </div>

                <!-- Active filter tags -->
                <div class="active-filters">
                    <?php if ($category): ?>
                        <span class="filter-tag"><i class="bi bi-tag-fill"></i> <?= htmlspecialchars($category) ?></span>
                    <?php endif; ?>
                    <?php foreach ($brands as $br): ?>
                        <span class="filter-tag"><i class="bi bi-building"></i> <?= htmlspecialchars($br) ?></span>
                    <?php endforeach; ?>
                    <?php if ($price_min || $price_max): ?>
                        <span class="filter-tag">
                            <i class="bi bi-cash"></i>
                            <?= $price_min ? number_format($price_min).'đ' : '0' ?> —
                            <?= $price_max ? number_format($price_max).'đ' : '...' ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="sort-wrap">
                    <span>Sắp xếp:</span>
                    <select class="sort-select" onchange="changeSort(this.value)">
                        <option value="newest"     <?= $sort==='newest'     ? 'selected':'' ?>>Mới nhất</option>
                        <option value="price_asc"  <?= $sort==='price_asc'  ? 'selected':'' ?>>Giá thấp → cao</option>
                        <option value="price_desc" <?= $sort==='price_desc' ? 'selected':'' ?>>Giá cao → thấp</option>
                        <option value="discount"   <?= $sort==='discount'   ? 'selected':'' ?>>Khuyến mãi nhiều</option>
                    </select>
                </div>
            </div>

            <!-- Product grid -->
            <div class="products-grid-4">
                <?php if ($products->num_rows > 0): ?>
                    <?php while ($p = $products->fetch_assoc()): ?>
                    <div class="p-card">
                        <?php if ($p['discount_percent'] > 0): ?>
                            <span class="p-badge">-<?= $p['discount_percent'] ?>%</span>
                        <?php endif; ?>

                        <a href="product_detail.php?id=<?= $p['id'] ?>">
                            <div class="p-img">
                                <?php if ($p['thumbnail']): ?>
                                    <img src="../assets/images/products/<?= htmlspecialchars($p['thumbnail']) ?>"
                                        alt="<?= htmlspecialchars($p['name']) ?>">
                                <?php else: ?>
                                    📱
                                <?php endif; ?>
                            </div>
                        </a>

                        <div class="p-body">
                            <div class="p-brand"><?= htmlspecialchars($p['brand_name'] ?? '') ?></div>
                            <a href="product_detail.php?id=<?= $p['id'] ?>" style="text-decoration:none">
                                <div class="p-name">
                                    <?php
                                    $name = htmlspecialchars($p['name']);
                                    if ($q) {
                                        $name = preg_replace('/('.preg_quote(htmlspecialchars($q), '/').')/i',
                                            '<mark class="highlight">$1</mark>', $name);
                                    }
                                    echo $name;
                                    ?>
                                </div>
                            </a>

                            <?php if ($p['ram'] || $p['storage']): ?>
                            <div class="p-specs">
                                <?php if ($p['ram']): ?>
                                    <span class="p-spec-tag">RAM <?= $p['ram'] ?></span>
                                <?php endif; ?>
                                <?php if ($p['storage']): ?>
                                    <span class="p-spec-tag"><?= $p['storage'] ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <div class="p-price-wrap">
                                <span class="p-price"><?= number_format($p['price'], 0, ',', '.') ?>đ</span>
                                <?php if ($p['old_price']): ?>
                                    <span class="p-old-price"><?= number_format($p['old_price'], 0, ',', '.') ?>đ</span>
                                <?php endif; ?>
                            </div>

                            <a href="../api/add_to_cart.php?product_id=<?= $p['id'] ?>" class="btn-cart">
                                <i class="bi bi-bag-plus"></i> Thêm vào giỏ
                            </a>
                        </div>
                    </div>
                    <?php endwhile; ?>

                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🔍</div>
                        <h3>Không tìm thấy sản phẩm</h3>
                        <p>Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm khác nhé!</p>
                        <a href="products.php" style="color:var(--primary);font-weight:600;font-size:0.9rem;">
                            Xem tất cả sản phẩm →
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-wrap">
                <?php
                $base_url = build_url();
                // Prev
                if ($page > 1):
                ?>
                    <a href="<?= $base_url ?>&page=<?= $page-1 ?>" class="page-btn">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="page-btn disabled"><i class="bi bi-chevron-left"></i></span>
                <?php endif; ?>

                <?php
                // Hiện tối đa 5 trang
                $start = max(1, $page - 2);
                $end   = min($total_pages, $page + 2);
                if ($start > 1): ?>
                    <a href="<?= $base_url ?>&page=1" class="page-btn">1</a>
                    <?php if ($start > 2): ?><span class="page-btn disabled">...</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="<?= $base_url ?>&page=<?= $i ?>"
                    class="page-btn <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?><span class="page-btn disabled">...</span><?php endif; ?>
                    <a href="<?= $base_url ?>&page=<?= $total_pages ?>" class="page-btn"><?= $total_pages ?></a>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="<?= $base_url ?>&page=<?= $page+1 ?>" class="page-btn">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="page-btn disabled"><i class="bi bi-chevron-right"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- ══ FOOTER ══ -->
    <footer style="margin-top:48px">
        <div class="container-main">
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="footer-brand">Phone<span>Store</span></div>
                    <p class="footer-desc">Chuỗi cửa hàng điện thoại & phụ kiện chính hãng uy tín toàn quốc.</p>
                </div>
                <div class="col-lg-2 col-6">
                    <div class="footer-heading">Sản phẩm</div>
                    <ul class="footer-links">
                        <li><a href="products.php?category=dien-thoai">Điện thoại</a></li>
                        <li><a href="products.php?category=tai-nghe">Tai nghe</a></li>
                        <li><a href="products.php?category=sac-cap">Sạc & Cáp</a></li>
                        <li><a href="products.php?category=op-lung">Ốp lưng</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-6">
                    <div class="footer-heading">Hỗ trợ</div>
                    <ul class="footer-links">
                        <li><a href="policy.php">Chính sách đổi trả</a></li>
                        <li><a href="policy.php#warranty">Bảo hành</a></li>
                        <li><a href="policy.php#shipping">Vận chuyển</a></li>
                        <li><a href="contact.php">Liên hệ</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="footer-heading">Liên hệ</div>
                    <div class="footer-contact-item"><i class="bi bi-geo-alt-fill"></i><span>123 Nguyễn Huệ, Quận 1, TP. HCM</span></div>
                    <div class="footer-contact-item"><i class="bi bi-telephone-fill"></i><span>1800 2097</span></div>
                    <div class="footer-contact-item"><i class="bi bi-envelope-fill"></i><span>support@phonestore.com</span></div>
                </div>
            </div>
            <div class="footer-bottom">
                <span>© 2024 PhoneStore. All rights reserved.</span>
            </div>
        </div>
    </footer>

    <!-- CSS bổ sung -->
    <style>
    .price-preset {
        background: var(--light); border: 1.5px solid var(--border);
        border-radius: 6px; padding: 4px 8px; font-size: 0.72rem;
        font-weight: 600; color: var(--gray); cursor: pointer;
        font-family: 'Nunito', sans-serif; transition: all 0.2s;
    }
    .price-preset:hover { border-color: var(--primary); color: var(--primary); }
    .nav-links a.active { color: var(--primary); background: #EEF4FF; }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Sắp xếp
    function changeSort(val) {
        const url = new URL(window.location);
        url.searchParams.set('sort', val);
        url.searchParams.delete('page');
        window.location = url;
    }

    // Preset giá
    function setPrice(min, max) {
        document.querySelector('input[name="price_min"]').value = min || '';
        document.querySelector('input[name="price_max"]').value = max || '';
    }

    // Mobile filter toggle
    function toggleMobileFilter() {
        document.querySelectorAll('.filter-card').forEach(el => el.classList.toggle('show'));
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