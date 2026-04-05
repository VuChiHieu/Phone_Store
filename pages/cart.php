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

        // Cập nhật số lượng — xóa dòng thừa
        if (isset($_POST['update_qty'])) {
            $cart_id = (int)$_POST['cart_id'];
            $qty     = max(1, (int)$_POST['quantity']);
            // Chỉ giữ 1 lần prepare, xóa dòng đầu thừa
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

    include '../includes/navbar.php';

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
                grid-template-columns: 24px 80px 1fr auto;
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
                    <div style="display:flex;align-items:center;gap:12px">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0">
                            <input type="checkbox" id="checkAll" 
                                style="width:18px;height:18px;accent-color:var(--primary);cursor:pointer">
                            <span class="cart-title">Giỏ hàng (<?= $total_items ?> sản phẩm)</span>
                        </label>
                    </div>
                    <form method="POST" onsubmit="return confirm('Xóa toàn bộ giỏ hàng?')">
                        <button type="submit" name="clear_cart" class="btn-clear">
                            <i class="bi bi-trash3"></i> Xóa tất cả
                        </button>
                    </form>
                </div>

                <?php foreach ($items as $item): ?>
                <div class="cart-item" id="item-<?= $item['cart_id'] ?>">

                    <!-- Checkbox chọn -->
                    <input type="checkbox" class="item-check"
                        data-id="<?= $item['cart_id'] ?>"
                        data-price="<?= $item['price'] ?>"
                        data-qty="<?= $item['quantity'] ?>"
                        style="width:18px;height:18px;accent-color:var(--primary);cursor:pointer;margin-right:4px"
                        checked>

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

                <!-- Thông báo ship động -->
                <div id="shippingNote" style="margin-bottom:16px"></div>

                <div class="summary-row">
                    <span class="label">Đã chọn (<span id="selectedCount">0</span> sp)</span>
                    <span class="value" id="selectedSubtotal">0đ</span>
                </div>
                <div class="summary-row">
                    <span class="label">Phí vận chuyển</span>
                    <span class="value" id="shippingFee">30.000đ</span>
                </div>

                <div class="summary-divider"></div>

                <div class="summary-total">
                    <span class="label">Tổng cộng</span>
                    <span class="value" id="grandTotal">0đ</span>
                </div>

                <a href="checkout.php" class="btn-checkout" id="btnCheckout">
                    <i class="bi bi-credit-card"></i> Tiến hành thanh toán
                </a>
                <a href="products.php" class="btn-continue">
                    <i class="bi bi-arrow-left"></i> Tiếp tục mua sắm
                </a>
            </div>
        </div>

        <?php endif; ?>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // ── Checkbox + tính tiền ────────────────────────────
    const checkAll    = document.getElementById('checkAll');
    const itemChecks  = document.querySelectorAll('.item-check');

    function formatVND(n) {
        return n.toLocaleString('vi-VN') + 'đ';
    }

    function recalculate() {
        let subtotal = 0;
        let count    = 0;
        const selectedIds = [];

        itemChecks.forEach(cb => {
            if (cb.checked) {
                const price = parseInt(cb.dataset.price);
                const qty   = parseInt(document.getElementById('qty-' + cb.dataset.id)?.value || cb.dataset.qty);
                subtotal   += price * qty;
                count      += qty;
                selectedIds.push(cb.dataset.id);
            }
        });

        const shippingFee = subtotal >= 500000 ? 0 : (subtotal > 0 ? 30000 : 0);
        const grandTotal  = subtotal + shippingFee;

        // Cập nhật UI
        document.getElementById('selectedCount').textContent   = count;
        document.getElementById('selectedSubtotal').textContent = formatVND(subtotal);
        document.getElementById('shippingFee').textContent     = shippingFee === 0 && subtotal > 0 ? 'Miễn phí' : formatVND(shippingFee);
        document.getElementById('grandTotal').textContent      = formatVND(grandTotal);

        // Thông báo ship
        const noteEl = document.getElementById('shippingNote');
        if (subtotal === 0) {
            noteEl.innerHTML = '';
        } else if (shippingFee === 0) {
            noteEl.innerHTML = `<div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;padding:10px 12px;font-size:0.78rem;color:#16A34A;font-weight:600;text-align:center;display:flex;align-items:center;justify-content:center;gap:6px">
                <i class="bi bi-truck"></i> Bạn được miễn phí vận chuyển!
            </div>`;
        } else {
            const remain = 500000 - subtotal;
            noteEl.innerHTML = `<div style="background:#FFF7ED;border:1px solid #FED7AA;border-radius:8px;padding:10px 12px;font-size:0.78rem;color:#C2410C;font-weight:600;text-align:center">
                <i class="bi bi-info-circle"></i> Mua thêm ${formatVND(remain)} để được miễn phí ship!
            </div>`;
        }

        // Nút thanh toán — truyền id đã chọn qua URL
        const btn = document.getElementById('btnCheckout');
        if (count > 0) {
            btn.href = 'checkout.php?items=' + selectedIds.join(',');
            btn.style.opacity = '1';
            btn.style.pointerEvents = 'auto';
        } else {
            btn.href = '#';
            btn.style.opacity = '0.5';
            btn.style.pointerEvents = 'none';
        }

        // Sync checkbox "chọn tất cả"
        const checkedCount = document.querySelectorAll('.item-check:checked').length;
        checkAll.checked       = checkedCount === itemChecks.length;
        checkAll.indeterminate = checkedCount > 0 && checkedCount < itemChecks.length;
    }

    // Chọn tất cả
    checkAll.addEventListener('change', function () {
        itemChecks.forEach(cb => cb.checked = this.checked);
        recalculate();
    });

    // Từng checkbox
    itemChecks.forEach(cb => {
        cb.addEventListener('change', recalculate);
    });

    // ── Nút +/- ────────────────────────────────────────
    function changeQty(btn, delta, maxStock) {
        const form  = btn.closest('form');
        const input = form.querySelector('input[name="quantity"]');
        let val = parseInt(input.value) + delta;

        if (val < 1) val = 1;
        if (val > maxStock) val = maxStock;

        input.value = val;

        // Cập nhật dataset qty cho checkbox để recalculate đúng
        const cartId = form.querySelector('[name="cart_id"]').value;
        const cb = document.querySelector(`.item-check[data-id="${cartId}"]`);
        if (cb) cb.dataset.qty = val;

        recalculate();

        // Submit cập nhật DB
        form.querySelector('[name="update_qty"]').click();
    }

    recalculate();
    </script>
    <?php include '../includes/footer.php'; ?>
    </body>
</html>