<?php
    session_start();
    require_once '../config.php';

    // Chưa đăng nhập → về login
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../auth/login.php');
        exit;
    }

    $uid = $_SESSION['user_id'];

    // ── XỬ LÝ CẬP NHẬT / XÓA ───────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Cập nhật số lượng
        if (isset($_POST['update_qty'])) {
            $cart_id = (int)$_POST['cart_id'];
            $qty     = max(1, (int)$_POST['quantity']);
            $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?")
                ->bind_param("iii", $qty, $cart_id, $uid) || true;
            $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("iii", $qty, $cart_id, $uid);
            $stmt->execute();
        }

        // Xóa 1 sản phẩm
        if (isset($_POST['remove_item'])) {
            $cart_id = (int)$_POST['cart_id'];
            $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $cart_id, $uid);
            $stmt->execute();
        }

        // Xóa tất cả
        if (isset($_POST['clear_cart'])) {
            $conn->query("DELETE FROM cart WHERE user_id = $uid");
        }

        header('Location: cart.php');
        exit;
    }

    // ── LẤY GIỎ HÀNG ────────────────────────────────────
    $cart_items = $conn->query("
        SELECT c.id AS cart_id, c.quantity,
            p.id AS product_id, p.name, p.price, p.old_price,
            p.discount_percent, p.thumbnail, p.stock,
            b.name AS brand_name
        FROM cart c
        JOIN products p ON c.product_id = p.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE c.user_id = $uid
        ORDER BY c.id DESC
    ");

    $items       = [];
    $total_price = 0;
    $total_items = 0;
    while ($item = $cart_items->fetch_assoc()) {
        $items[]      = $item;
        $total_price += $item['price'] * $item['quantity'];
        $total_items += $item['quantity'];
    }

    // Đếm giỏ hàng navbar
    $cart_count = $total_items;

    $shipping_fee = $total_price >= 500000 ? 0 : 30000;
    $final_total  = $total_price + $shipping_fee;
?>
<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Giỏ hàng - Phone Store</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
        <link rel="stylesheet" href="../assets/css/style.css">
        <style>
            .cart-wrap {
                max-width: 1280px;
                margin: 0 auto;
                padding: 24px;
                display: grid;
                grid-template-columns: 1fr 360px;
                gap: 24px;
                align-items: start;
            }

            /* ── CART TABLE ── */
            .cart-card {
                background: #fff;
                border: 1px solid var(--border);
                border-radius: 16px;
                overflow: hidden;
            }
            .cart-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 18px 24px;
                border-bottom: 1px solid var(--border);
            }
            .cart-title {
                font-size: 1.1rem;
                font-weight: 800;
                color: var(--dark);
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .cart-title::before {
                content: '';
                display: inline-block;
                width: 4px; height: 18px;
                background: var(--primary);
                border-radius: 2px;
            }
            .btn-clear {
                background: none;
                border: 1.5px solid var(--border);
                border-radius: 8px;
                padding: 6px 14px;
                font-size: 0.8rem;
                font-weight: 600;
                color: var(--gray);
                cursor: pointer;
                font-family: 'Nunito', sans-serif;
                transition: all 0.2s;
            }
            .btn-clear:hover { border-color: var(--danger); color: var(--danger); }

            /* Cart item */
            .cart-item {
                display: grid;
                grid-template-columns: 80px 1fr auto;
                gap: 16px;
                align-items: center;
                padding: 16px 24px;
                border-bottom: 1px solid var(--border);
                transition: background 0.15s;
            }
            .cart-item:last-child { border-bottom: none; }
            .cart-item:hover { background: #FAFAFA; }

            .cart-item-img {
                width: 80px; height: 80px;
                border-radius: 10px;
                background: var(--light);
                overflow: hidden;
                display: flex; align-items: center; justify-content: center;
                font-size: 2.2rem;
                flex-shrink: 0;
            }
            .cart-item-img img { width: 100%; height: 100%; object-fit: cover; }

            .cart-item-info {}
            .cart-item-brand {
                font-size: 0.68rem;
                font-weight: 700;
                color: var(--primary);
                text-transform: uppercase;
                letter-spacing: 1px;
                margin-bottom: 3px;
            }
            .cart-item-name {
                font-weight: 700;
                font-size: 0.9rem;
                color: var(--dark);
                margin-bottom: 8px;
                line-height: 1.3;
            }
            .cart-item-name a { color: inherit; text-decoration: none; }
            .cart-item-name a:hover { color: var(--primary); }
            .cart-item-price {
                font-size: 1rem;
                font-weight: 800;
                color: var(--danger);
            }
            .cart-item-old {
                font-size: 0.78rem;
                color: var(--gray);
                text-decoration: line-through;
                margin-left: 6px;
            }

            .cart-item-actions {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 10px;
            }
            .cart-item-subtotal {
                font-size: 1rem;
                font-weight: 800;
                color: var(--dark);
                white-space: nowrap;
            }

            /* Qty control */
            .qty-ctrl {
                display: flex;
                align-items: center;
                border: 1.5px solid var(--border);
                border-radius: 8px;
                overflow: hidden;
            }
            .qty-ctrl button {
                width: 30px; height: 30px;
                background: var(--light);
                border: none; cursor: pointer;
                font-size: 1rem; color: var(--dark);
                transition: background 0.15s;
                font-family: 'Nunito', sans-serif;
            }
            .qty-ctrl button:hover { background: #ddd; }
            .qty-ctrl input {
                width: 40px; height: 30px;
                border: none;
                border-left: 1.5px solid var(--border);
                border-right: 1.5px solid var(--border);
                text-align: center;
                font-size: 0.875rem;
                font-weight: 700;
                font-family: 'Nunito', sans-serif;
                outline: none;
            }

            .btn-remove {
                background: none;
                border: none;
                color: var(--gray);
                cursor: pointer;
                font-size: 0.8rem;
                padding: 0;
                display: flex; align-items: center; gap: 4px;
                transition: color 0.2s;
                font-family: 'Nunito', sans-serif;
            }
            .btn-remove:hover { color: var(--danger); }

            /* ── ORDER SUMMARY ── */
            .summary-card {
                background: #fff;
                border: 1px solid var(--border);
                border-radius: 16px;
                padding: 24px;
                position: sticky;
                top: 88px;
            }
            .summary-title {
                font-size: 1rem;
                font-weight: 800;
                color: var(--dark);
                margin-bottom: 20px;
                padding-bottom: 14px;
                border-bottom: 1px solid var(--border);
                display: flex; align-items: center; gap: 8px;
            }
            .summary-title::before {
                content: '';
                display: inline-block;
                width: 4px; height: 16px;
                background: var(--primary);
                border-radius: 2px;
            }
            .summary-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 0.875rem;
                margin-bottom: 12px;
            }
            .summary-row .label { color: var(--gray); }
            .summary-row .value { font-weight: 700; color: var(--dark); }
            .summary-row .value.free { color: #16A34A; }
            .summary-divider {
                height: 1px;
                background: var(--border);
                margin: 16px 0;
            }
            .summary-total {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }
            .summary-total .label {
                font-weight: 700;
                font-size: 0.9rem;
                color: var(--dark);
            }
            .summary-total .value {
                font-size: 1.3rem;
                font-weight: 800;
                color: var(--danger);
            }
            .btn-checkout {
                width: 100%;
                background: var(--primary);
                color: #fff;
                border: none;
                border-radius: 10px;
                padding: 14px;
                font-size: 0.95rem;
                font-weight: 700;
                font-family: 'Nunito', sans-serif;
                cursor: pointer;
                transition: all 0.2s;
                text-decoration: none;
                display: flex; align-items: center; justify-content: center; gap: 8px;
                margin-bottom: 10px;
            }
            .btn-checkout:hover {
                background: var(--primary-dark);
                color: #fff;
                transform: translateY(-1px);
                box-shadow: 0 6px 20px rgba(0,87,255,0.25);
            }
            .btn-continue {
                width: 100%;
                background: transparent;
                color: var(--gray);
                border: 1.5px solid var(--border);
                border-radius: 10px;
                padding: 11px;
                font-size: 0.875rem;
                font-weight: 600;
                font-family: 'Nunito', sans-serif;
                cursor: pointer;
                transition: all 0.2s;
                text-decoration: none;
                display: flex; align-items: center; justify-content: center; gap: 6px;
            }
            .btn-continue:hover { border-color: var(--primary); color: var(--primary); }

            .shipping-note {
                background: #F0FDF4;
                border: 1px solid #BBF7D0;
                border-radius: 8px;
                padding: 10px 12px;
                font-size: 0.78rem;
                color: #16A34A;
                font-weight: 600;
                text-align: center;
                margin-bottom: 16px;
                display: flex; align-items: center; justify-content: center; gap: 6px;
            }

            /* Empty cart */
            .empty-cart {
                text-align: center;
                padding: 60px 20px;
            }
            .empty-cart-icon { font-size: 5rem; margin-bottom: 16px; }
            .empty-cart h3 { font-weight: 800; color: var(--dark); margin-bottom: 8px; }
            .empty-cart p { color: var(--gray); margin-bottom: 24px; }
            .btn-shop {
                background: var(--primary); color: #fff;
                border: none; border-radius: 10px;
                padding: 12px 28px;
                font-size: 0.9rem; font-weight: 700;
                font-family: 'Nunito', sans-serif;
                text-decoration: none;
                transition: background 0.2s;
            }
            .btn-shop:hover { background: var(--primary-dark); color: #fff; }

            /* Responsive */
            @media (max-width: 900px) {
                .cart-wrap { grid-template-columns: 1fr; }
                .summary-card { position: static; }
            }
            @media (max-width: 600px) {
                .cart-wrap { padding: 12px; }
                .cart-item { grid-template-columns: 64px 1fr; }
                .cart-item-actions { flex-direction: row; align-items: center; grid-column: 1 / -1; justify-content: space-between; }
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
            <a href="cart.php" class="cart-link" style="border-color:var(--primary);color:var(--primary)">
                <i class="bi bi-bag-fill" style="font-size:1.1rem"></i> Giỏ hàng
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
    <div style="background:#fff;border-bottom:1px solid var(--border);padding:10px 0;">
        <div style="max-width:1280px;margin:0 auto;padding:0 24px;font-size:0.82rem;color:var(--gray);">
            <a href="../index.php" style="color:var(--gray);text-decoration:none;">Trang chủ</a>
            <i class="bi bi-chevron-right" style="font-size:0.7rem;margin:0 6px;"></i>
            <span style="color:var(--dark);font-weight:600;">Giỏ hàng</span>
        </div>
    </div>

    <!-- ══ MAIN ══ -->
    <div class="cart-wrap">

        <?php if (empty($items)): ?>
        <!-- Giỏ hàng trống -->
        <div class="cart-card" style="grid-column:1/-1">
            <div class="empty-cart">
                <div class="empty-cart-icon">🛒</div>
                <h3>Giỏ hàng trống!</h3>
                <p>Bạn chưa có sản phẩm nào trong giỏ hàng.</p>
                <a href="products.php" class="btn-shop">
                    <i class="bi bi-bag"></i> Mua sắm ngay
                </a>
            </div>
        </div>

        <?php else: ?>

        <!-- ══════ DANH SÁCH SẢN PHẨM ══════ -->
        <div>
            <div class="cart-card">
                <div class="cart-header">
                    <div class="cart-title">Giỏ hàng (<?= $total_items ?> sản phẩm)</div>
                    <form method="POST" onsubmit="return confirm('Xóa toàn bộ giỏ hàng?')">
                        <button type="submit" name="clear_cart" class="btn-clear">
                            <i class="bi bi-trash3"></i> Xóa tất cả
                        </button>
                    </form>
                </div>

                <?php foreach ($items as $item): ?>
                <div class="cart-item" id="item-<?= $item['cart_id'] ?>">

                    <!-- Ảnh -->
                    <a href="product_detail.php?id=<?= $item['product_id'] ?>">
                        <div class="cart-item-img">
                            <?php if ($item['thumbnail']): ?>
                                <img src="../assets/images/products/<?= htmlspecialchars($item['thumbnail']) ?>"
                                    alt="<?= htmlspecialchars($item['name']) ?>">
                            <?php else: ?>
                                📱
                            <?php endif; ?>
                        </div>
                    </a>

                    <!-- Thông tin -->
                    <div class="cart-item-info">
                        <div class="cart-item-brand"><?= htmlspecialchars($item['brand_name'] ?? '') ?></div>
                        <div class="cart-item-name">
                            <a href="product_detail.php?id=<?= $item['product_id'] ?>">
                                <?= htmlspecialchars($item['name']) ?>
                            </a>
                        </div>
                        <div>
                            <span class="cart-item-price"><?= number_format($item['price'],0,',','.') ?>đ</span>
                            <?php if ($item['old_price']): ?>
                                <span class="cart-item-old"><?= number_format($item['old_price'],0,',','.') ?>đ</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Số lượng + xóa -->
                    <div class="cart-item-actions">
                        <div class="cart-item-subtotal">
                            <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>đ
                        </div>
                        <form method="POST" style="display:flex;align-items:center;gap:8px;">
                            <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                            <div class="qty-ctrl">
                                <button type="button" onclick="changeQty(this, -1, <?= $item['stock'] ?>)">−</button>
                                <input type="number" name="quantity" value="<?= $item['quantity'] ?>"
                                    min="1" max="<?= $item['stock'] ?>"
                                    onchange="this.form.querySelector('[name=update_qty]').click()"
                                    id="qty-<?= $item['cart_id'] ?>">
                                <button type="button" onclick="changeQty(this, 1, <?= $item['stock'] ?>)">+</button>
                            </div>
                            <button type="submit" name="update_qty" style="display:none"></button>
                        </form>
                        <form method="POST">
                            <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                            <button type="submit" name="remove_item" class="btn-remove">
                                <i class="bi bi-trash3"></i> Xóa
                            </button>
                        </form>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:12px;">
                <a href="products.php" style="color:var(--primary);font-size:0.875rem;font-weight:600;text-decoration:none;">
                    <i class="bi bi-arrow-left"></i> Tiếp tục mua sắm
                </a>
            </div>
        </div>

        <!-- ══════ ORDER SUMMARY ══════ -->
        <div>
            <div class="summary-card">
                <div class="summary-title">Tóm tắt đơn hàng</div>

                <?php if ($shipping_fee === 0): ?>
                <div class="shipping-note">
                    <i class="bi bi-truck"></i> Bạn được miễn phí vận chuyển!
                </div>
                <?php else: ?>
                <div style="background:#FFF7ED;border:1px solid #FED7AA;border-radius:8px;padding:10px 12px;font-size:0.78rem;color:#C2410C;font-weight:600;text-align:center;margin-bottom:16px;">
                    <i class="bi bi-info-circle"></i>
                    Mua thêm <?= number_format(500000 - $total_price, 0, ',', '.') ?>đ để được miễn phí ship!
                </div>
                <?php endif; ?>

                <div class="summary-row">
                    <span class="label">Tạm tính (<?= $total_items ?> sp)</span>
                    <span class="value"><?= number_format($total_price, 0, ',', '.') ?>đ</span>
                </div>
                <div class="summary-row">
                    <span class="label">Phí vận chuyển</span>
                    <span class="value <?= $shipping_fee === 0 ? 'free' : '' ?>">
                        <?= $shipping_fee === 0 ? 'Miễn phí' : number_format($shipping_fee,0,',','.').'đ' ?>
                    </span>
                </div>

                <div class="summary-divider"></div>

                <div class="summary-total">
                    <span class="label">Tổng cộng</span>
                    <span class="value"><?= number_format($final_total, 0, ',', '.') ?>đ</span>
                </div>

                <a href="checkout.php" class="btn-checkout">
                    <i class="bi bi-credit-card"></i> Tiến hành thanh toán
                </a>
                <a href="products.php" class="btn-continue">
                    <i class="bi bi-arrow-left"></i> Tiếp tục mua sắm
                </a>
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
    function changeQty(btn, delta, maxStock) {
        const form  = btn.closest('form');
        const input = form.querySelector('input[name="quantity"]');
        let val = parseInt(input.value) + delta;
        val = Math.max(1, Math.min(maxStock, val));
        input.value = val;
        // Auto submit
        form.querySelector('[name=update_qty]').click();
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