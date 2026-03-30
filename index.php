<?php
session_start();
require_once 'config.php';

// Lấy sản phẩm nổi bật
$featured = $conn->query("
    SELECT p.*, b.name AS brand_name
    FROM products p
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE p.is_featured = 1
    ORDER BY p.created_at DESC
    LIMIT 10
");

// Lấy danh mục
$categories = $conn->query("SELECT * FROM categories ORDER BY id ASC");

// Lấy khuyến mãi đang active
$promotions = $conn->query("
    SELECT * FROM promotions
    WHERE is_active = 1
    AND (start_date IS NULL OR start_date <= CURDATE())
    AND (end_date IS NULL OR end_date >= CURDATE())
    LIMIT 3
");

// Đếm giỏ hàng
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $r = $conn->prepare("SELECT SUM(quantity) AS total FROM cart WHERE user_id = ?");
    $r->bind_param("i", $uid);
    $r->execute();
    $cart_count = $r->get_result()->fetch_assoc()['total'] ?? 0;
}

// Lucide icon names cho từng danh mục (dropdown + quick bar)
$cat_icons = ['smartphone', 'headphones', 'plug', 'package', 'watch', 'laptop'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phone Store - Điện thoại & Phụ kiện chính hãng</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Lucide Icons — SVG icon hiện đại -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
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

        <a href="index.php" class="navbar-brand">Phone<span>Store</span></a>

        <!-- ✅ Dropdown danh mục — toggle bằng JS, không dùng CSS :hover -->
        <div class="cat-dropdown" id="catDropdown">
            <button class="cat-dropdown-btn" id="catDropdownBtn">
                <i class="bi bi-grid-3x3-gap-fill"></i>
                <span>Danh mục</span>
                <i class="bi bi-chevron-down arrow"></i>
            </button>
            <div class="cat-dropdown-menu" id="catDropdownMenu">
                <?php
                $categories->data_seek(0);
                $i = 0;
                while ($cat = $categories->fetch_assoc()):
                    $icon = $cat_icons[$i % count($cat_icons)]; $i++;
                ?>
                <a href="pages/products.php?category=<?= $cat['slug'] ?>" class="cat-dropdown-item">
                    <span class="cat-icon"><i data-lucide="<?= $icon ?>"></i></span>
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
                <?php endwhile; ?>
                <div class="cat-dropdown-divider"></div>
                <a href="pages/products.php" class="cat-dropdown-item" style="color:var(--primary);font-weight:700;">
                    <span class="cat-icon"><i data-lucide="search"></i></span> Xem tất cả sản phẩm
                </a>
            </div>
        </div>

        <div class="search-wrap">
            <input type="text" id="searchInput" placeholder="Bạn muốn tìm gì hôm nay?"
                onkeydown="if(event.key==='Enter') window.location='pages/products.php?q='+encodeURIComponent(this.value)">
            <button class="search-btn" onclick="window.location='pages/products.php?q='+encodeURIComponent(document.getElementById('searchInput').value)">
                <i class="bi bi-search"></i>
            </button>
        </div>

        <ul class="nav-links">
            <li><a href="pages/products.php"><i class="bi bi-phone"></i> Sản phẩm</a></li>
            <li><a href="pages/contact.php"><i class="bi bi-headset"></i> Liên hệ</a></li>
        </ul>

        <a href="pages/cart.php" class="cart-link">
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
                <a href="admin/index.php" class="user-dropdown-item">
                    <i class="bi bi-speedometer2"></i> Trang quản trị
                </a>
                <?php endif; ?>
                <a href="pages/orders.php" class="user-dropdown-item">
                    <i class="bi bi-bag-check"></i> Đơn hàng của tôi
                </a>
                <a href="pages/profile.php" class="user-dropdown-item">
                    <i class="bi bi-gear"></i> Cài đặt tài khoản
                </a>
                <div class="user-dropdown-divider"></div>
                <a href="auth/logout.php" class="user-dropdown-item user-dropdown-logout">
                    <i class="bi bi-box-arrow-right"></i> Đăng xuất
                </a>
            </div>
        </div>
        <?php else: ?>
            <a href="auth/login.php" class="btn-login">
                <i class="bi bi-person"></i> Đăng nhập
            </a>
        <?php endif; ?>

    </div>
</nav>

<!-- ══ QUICK CATEGORY BAR — Lucide Icons ══ -->
<div class="quick-cats">
    <div class="quick-cats-inner">
        <a href="pages/products.php" class="quick-cat-item active">
            <i data-lucide="layout-grid" class="qc-icon"></i> Tất cả
        </a>
        <?php
        $categories->data_seek(0); $i = 0;
        while ($cat = $categories->fetch_assoc()):
            $icon = $cat_icons[$i % count($cat_icons)]; $i++;
        ?>
        <a href="pages/products.php?category=<?= $cat['slug'] ?>" class="quick-cat-item">
            <i data-lucide="<?= $icon ?>" class="qc-icon"></i>
            <?= htmlspecialchars($cat['name']) ?>
        </a>
        <?php endwhile; ?>
        <a href="pages/products.php?sort=discount" class="quick-cat-item">
            <i data-lucide="tag" class="qc-icon"></i> Khuyến mãi
        </a>
    </div>
</div>

<!-- ══ HERO BANNER ══ -->
<div class="hero-section">
    <div class="hero-inner">
        <?php
        $banner_main = $conn->query("
            SELECT * FROM promotions
            WHERE is_active=1 AND banner_type='main'
            AND (start_date IS NULL OR start_date <= CURDATE())
            AND (end_date IS NULL OR end_date >= CURDATE())
            ORDER BY id DESC LIMIT 1
        ")->fetch_assoc();

        $banner_sides = $conn->query("
            SELECT * FROM promotions
            WHERE is_active=1 AND banner_type='side'
            AND (start_date IS NULL OR start_date <= CURDATE())
            AND (end_date IS NULL OR end_date >= CURDATE())
            ORDER BY id DESC LIMIT 2
        ");
        $side1 = $banner_sides->fetch_assoc();
        $side2 = $banner_sides->fetch_assoc();
        ?>

        <div class="main-banner">
            <div>
                <span class="banner-label">✦ Khuyến mãi đặc biệt</span>
                <div class="banner-title">
                    <?= htmlspecialchars($banner_main['title'] ?? 'iPhone 15 Pro Max.') ?><br>
                    <span><?= htmlspecialchars($banner_main['description'] ?? 'Titan. Đỉnh cao.') ?></span>
                </div>
                <?php if ($banner_main && $banner_main['image']): ?>
                <div class="banner-phone-img" style="position:absolute;right:40px;bottom:0;width:200px;height:260px;overflow:hidden;border-radius:12px">
                    <img src="assets/images/banners/<?= htmlspecialchars($banner_main['image']) ?>"
                        style="width:100%;height:100%;object-fit:cover">
                </div>
                <?php else: ?>
                <div class="banner-phone-img">📱</div>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($banner_main['link_url'] ?? 'pages/products.php') ?>" class="btn-banner">
                    Xem ngay <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>

        <div class="side-banners">
            <a href="<?= htmlspecialchars($side1['link_url'] ?? 'pages/products.php?category=tai-nghe') ?>" class="side-banner">
                <div>
                    <div class="side-banner-tag"><?= htmlspecialchars($side1['title'] ?? '🎧 Phụ kiện') ?></div>
                    <div class="side-banner-title"><?= nl2br(htmlspecialchars($side1['description'] ?? 'AirPods Pro 2 · Giảm đến 14%')) ?></div>
                </div>
            </a>
            <a href="<?= htmlspecialchars($side2['link_url'] ?? 'pages/products.php?sort=discount') ?>" class="side-banner">
                <div>
                    <div class="side-banner-tag"><?= htmlspecialchars($side2['title'] ?? '🔥 Flash Sale') ?></div>
                    <div class="side-banner-title"><?= nl2br(htmlspecialchars($side2['description'] ?? 'Samsung Galaxy · Ưu đãi hôm nay')) ?></div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- ══ SẢN PHẨM NỔI BẬT ══ -->
<section class="section">
    <div class="container-main">
        <div class="section-header">
            <h2 class="section-title">Sản phẩm nổi bật</h2>
            <a href="pages/products.php?featured=1" class="section-link">
                Xem tất cả <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        <?php if ($featured->num_rows > 0): ?>
        <div class="products-grid">
            <?php while ($p = $featured->fetch_assoc()): ?>
            <div class="product-card">
                <?php if ($p['discount_percent'] > 0): ?>
                    <span class="product-badge">-<?= $p['discount_percent'] ?>%</span>
                <?php endif; ?>
                <a href="pages/product_detail.php?id=<?= $p['id'] ?>">
                    <div class="product-img-wrap">
                        <?php if ($p['thumbnail']): ?>
                            <img src="assets/images/products/<?= htmlspecialchars($p['thumbnail']) ?>"
                                alt="<?= htmlspecialchars($p['name']) ?>">
                        <?php else: ?>📱<?php endif; ?>
                    </div>
                </a>
                <div class="product-body">
                    <div class="product-brand"><?= htmlspecialchars($p['brand_name'] ?? '') ?></div>
                    <a href="pages/product_detail.php?id=<?= $p['id'] ?>" style="text-decoration:none">
                        <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                    </a>
                    <div class="product-price-wrap">
                        <span class="product-price"><?= number_format($p['price'], 0, ',', '.') ?>đ</span>
                        <?php if ($p['old_price']): ?>
                            <span class="product-old-price"><?= number_format($p['old_price'], 0, ',', '.') ?>đ</span>
                        <?php endif; ?>
                    </div>
                    <a href="api/add_to_cart.php?product_id=<?= $p['id'] ?>" class="btn-add-cart">
                        <i class="bi bi-bag-plus"></i> Thêm vào giỏ
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <div style="font-size:3rem">📦</div>
                <p class="mt-2">Chưa có sản phẩm nổi bật.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ══ BANNER KHUYẾN MÃI ══ -->
<?php if ($promotions->num_rows > 0): ?>
<section class="section section-bg">
    <div class="container-main">
        <div class="section-header">
            <h2 class="section-title">Khuyến mãi đặc biệt</h2>
        </div>
        <div class="promo-grid">
            <?php while ($promo = $promotions->fetch_assoc()): ?>
            <div class="promo-card">
                <div>
                    <div class="promo-tag">🔥 Ưu đãi</div>
                    <div class="promo-title"><?= htmlspecialchars($promo['title']) ?></div>
                    <?php if ($promo['description']): ?>
                        <div class="promo-desc"><?= htmlspecialchars($promo['description']) ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($promo['end_date']): ?>
                    <div class="promo-desc" style="margin-top:10px">
                        <i class="bi bi-clock"></i> Đến <?= date('d/m/Y', strtotime($promo['end_date'])) ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ══ FOOTER ══ -->
<footer>
    <div class="container-main">
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="footer-brand">Phone<span>Store</span></div>
                <p class="footer-desc">Chuỗi cửa hàng điện thoại & phụ kiện chính hãng uy tín toàn quốc.</p>
                <div class="footer-badges">
                    <span class="footer-badge">✓ Chính hãng</span>
                    <span class="footer-badge">✓ Bảo hành</span>
                </div>
            </div>
            <div class="col-lg-2 col-md-6 col-6">
                <div class="footer-heading">Sản phẩm</div>
                <!-- ✅ Bỏ Sạc & Cáp và Ốp lưng theo yêu cầu -->
                <ul class="footer-links">
                    <li><a href="pages/products.php?category=dien-thoai">Điện thoại</a></li>
                    <li><a href="pages/products.php?category=tai-nghe">Tai nghe</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-6 col-6">
                <div class="footer-heading">Chính sách</div>
                <ul class="footer-links">
                    <li><a href="pages/policy.php">Đổi trả hàng</a></li>
                    <li><a href="pages/policy.php#warranty">Bảo hành</a></li>
                    <li><a href="pages/policy.php#shipping">Vận chuyển</a></li>
                    <li><a href="pages/contact.php">Liên hệ</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-6 col-6">
                <div class="footer-heading">Hỗ trợ</div>
                <ul class="footer-links">
                    <li><a href="auth/login.php">Đăng nhập</a></li>
                    <li><a href="auth/register.php">Đăng ký</a></li>
                    <li><a href="pages/cart.php">Giỏ hàng</a></li>
                    <li><a href="pages/contact.php">Feedback</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="footer-heading">Liên hệ</div>
                <div class="footer-contact-item">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>123 Nguyễn Huệ, Quận 1, TP. HCM</span>
                </div>
                <div class="footer-contact-item">
                    <i class="bi bi-telephone-fill"></i>
                    <span>1800 2097 (Miễn phí)</span>
                </div>
                <div class="footer-contact-item">
                    <i class="bi bi-envelope-fill"></i>
                    <span>support@phonestore.com</span>
                </div>
                <div class="footer-contact-item">
                    <i class="bi bi-clock-fill"></i>
                    <span>8:00 - 21:00, Thứ 2 - Chủ nhật</span>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© 2024 PhoneStore. All rights reserved.</span>
            <span>Made with ❤️ by Nhóm bạn</span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ✅ FIX: Dropdown danh mục dùng JS click thay CSS :hover — tránh lỗi mobile/touch
const catBtn  = document.getElementById('catDropdownBtn');
const catMenu = document.getElementById('catDropdownMenu');

catBtn?.addEventListener('click', function(e) {
    e.stopPropagation();
    const isOpen = catMenu.classList.toggle('open');
    catBtn.classList.toggle('open', isOpen);
});

// Đóng khi click ra ngoài
document.addEventListener('click', function(e) {
    if (!document.getElementById('catDropdown')?.contains(e.target)) {
        catMenu?.classList.remove('open');
        catBtn?.classList.remove('open');
    }
});

// User dropdown
document.querySelector('.user-dropdown-btn')?.addEventListener('click', function(e) {
    e.stopPropagation();
    document.querySelector('.user-dropdown-menu').classList.toggle('show');
});
document.addEventListener('click', function() {
    document.querySelector('.user-dropdown-menu')?.classList.remove('show');
});

// Khởi tạo tất cả Lucide icons
lucide.createIcons();
</script>
</body>
</html>