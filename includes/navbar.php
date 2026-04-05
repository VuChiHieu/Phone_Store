<?php
// Đếm giỏ hàng
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $r = $conn->prepare("SELECT SUM(quantity) AS total FROM cart WHERE user_id = ?");
    $r->bind_param("i", $uid);
    $r->execute();
    $cart_count = $r->get_result()->fetch_assoc()['total'] ?? 0;
}

// Xác định trang hiện tại để highlight nav
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));

// Đường dẫn base (khác nhau giữa index.php và pages/*)
$base = $current_dir === 'pages' ? '../' : '';
?>

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

        <a href="<?= $base ?>index.php" class="navbar-brand">Phone<span>Store</span></a>

        <!-- Search -->
        <div class="search-wrap" style="position:relative; flex:1; max-width:480px; margin:0 24px;">
            <input type="text" id="searchInput"
                   placeholder="Bạn muốn tìm gì hôm nay?"
                   autocomplete="off"
                   onkeydown="if(event.key==='Enter') goSearch()">
            <button class="search-btn" onclick="goSearch()">
                <i class="bi bi-search"></i>
            </button>
            <div id="searchSuggest" style="
                display:none; position:absolute; top:calc(100% + 6px); left:0; right:0;
                background:#fff; border:1.5px solid var(--border); border-radius:12px;
                box-shadow:0 8px 24px rgba(0,0,0,0.1); z-index:300;
                max-height:320px; overflow-y:auto; padding:6px;
            "></div>
        </div>

        <!-- Nav links -->
        <ul class="nav-links">
            <li>
                <a href="<?= $base ?>pages/products.php"
                   class="<?= $current_page==='products.php'?'active':'' ?>">
                    <i class="bi bi-phone"></i> Sản phẩm
                </a>
            </li>
            <li>
                <a href="<?= $base ?>pages/policy.php"
                   class="<?= $current_page==='policy.php'?'active':'' ?>">
                    <i class="bi bi-shield-check"></i> Chính sách
                </a>
            </li>
            <li>
                <a href="<?= $base ?>pages/contact.php"
                   class="<?= $current_page==='contact.php'?'active':'' ?>">
                    <i class="bi bi-headset"></i> Liên hệ
                </a>
            </li>
        </ul>

        <!-- Giỏ hàng -->
        <a href="<?= $base ?>pages/cart.php" class="cart-link">
            <i class="bi bi-bag" style="font-size:1.1rem"></i>
            Giỏ hàng
            <?php if ($cart_count > 0): ?>
                <span class="cart-badge"><?= $cart_count ?></span>
            <?php endif; ?>
        </a>

        <!-- User -->
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
                        <div class="user-role">
                            <?= $_SESSION['role'] === 'admin' ? 'Quản trị viên' : 'Khách hàng' ?>
                        </div>
                    </div>
                </div>
                <div class="user-dropdown-divider"></div>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="<?= $base ?>admin/index.php" class="user-dropdown-item">
                    <i class="bi bi-speedometer2"></i> Trang quản trị
                </a>
                <?php endif; ?>
                <a href="<?= $base ?>pages/orders.php" class="user-dropdown-item">
                    <i class="bi bi-bag-check"></i> Đơn hàng của tôi
                </a>
                <a href="<?= $base ?>pages/profile.php" class="user-dropdown-item">
                    <i class="bi bi-gear"></i> Cài đặt tài khoản
                </a>
                <div class="user-dropdown-divider"></div>
                <a href="<?= $base ?>auth/logout.php" class="user-dropdown-item user-dropdown-logout">
                    <i class="bi bi-box-arrow-right"></i> Đăng xuất
                </a>
            </div>
        </div>
        <?php else: ?>
            <a href="<?= $base ?>auth/login.php" class="btn-login">
                <i class="bi bi-person"></i> Đăng nhập
            </a>
        <?php endif; ?>

    </div>
</nav>

<!-- ══ Script navbar (search + user dropdown) ══ -->
<script>
function goSearch() {
    const q = document.getElementById('searchInput').value.trim();
    if (q) window.location = '<?= $base ?>pages/products.php?q=' + encodeURIComponent(q);
}

document.addEventListener('DOMContentLoaded', function () {

    // Search suggest
    const searchInput   = document.getElementById('searchInput');
    const searchSuggest = document.getElementById('searchSuggest');
    let searchTimer;

    searchInput?.addEventListener('input', function () {
        clearTimeout(searchTimer);
        const q = this.value.trim();
        if (q.length < 2) { searchSuggest.style.display = 'none'; return; }

        searchTimer = setTimeout(() => {
            fetch('<?= $base ?>api/search_suggest.php?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if (!data.length) { searchSuggest.style.display = 'none'; return; }
                    searchSuggest.innerHTML = data.map(item => `
                        <a href="<?= $base ?>pages/product_detail.php?id=${item.id}"
                           style="display:flex;align-items:center;gap:10px;padding:8px 10px;
                                  border-radius:8px;text-decoration:none;color:var(--dark);
                                  font-size:0.875rem;transition:background 0.15s"
                           onmouseover="this.style.background='#F3F4F6'"
                           onmouseout="this.style.background=''">
                            <img src="<?= $base ?>assets/images/products/${item.thumbnail || ''}"
                                 onerror="this.style.display='none'"
                                 style="width:36px;height:36px;object-fit:cover;border-radius:6px;background:#F3F4F6">
                            <div>
                                <div style="font-weight:700">${item.name}</div>
                                <div style="font-size:0.75rem;color:#EF4444;font-weight:700">
                                    ${Number(item.price).toLocaleString('vi-VN')}đ
                                </div>
                            </div>
                        </a>
                    `).join('') + `
                        <div style="padding:8px 10px;border-top:1px solid #F3F4F6;margin-top:4px">
                            <a href="<?= $base ?>pages/products.php?q=${encodeURIComponent(q)}"
                               style="font-size:0.8rem;color:var(--primary);font-weight:700;text-decoration:none">
                                <i class="bi bi-search"></i> Xem tất cả kết quả cho "${q}"
                            </a>
                        </div>
                    `;
                    searchSuggest.style.display = 'block';
                })
                .catch(() => { searchSuggest.style.display = 'none'; });
        }, 300);
    });

    document.addEventListener('click', function (e) {
        if (!searchInput?.contains(e.target) && !searchSuggest?.contains(e.target)) {
            searchSuggest.style.display = 'none';
        }
    });

    // User dropdown
    const userBtn  = document.querySelector('.user-dropdown-btn');
    const userMenu = document.querySelector('.user-dropdown-menu');

    userBtn?.addEventListener('click', function (e) {
        e.stopPropagation();
        userMenu?.classList.toggle('show');
    });

    document.addEventListener('click', function () {
        userMenu?.classList.remove('show');
    });

});

</script>