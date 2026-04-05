<?php
session_start();
require_once '../config.php';
include '../includes/navbar.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$uid      = (int) $_SESSION['user_id']; 
$order_id = (int) ($_GET['id'] ?? 0);

if (!$order_id) {
    header('Location: orders.php');
    exit;
}

$stmt_order = $conn->prepare("SELECT o.* FROM orders o WHERE o.id = ? AND o.user_id = ?");
$stmt_order->bind_param("ii", $order_id, $uid);
$stmt_order->execute();
$order = $stmt_order->get_result()->fetch_assoc();

if (!$order) {
    header('Location: orders.php');
    exit;
}

// ✅ Prepared statement cho order items
$stmt_items = $conn->prepare("
    SELECT oi.*, p.name, p.thumbnail, p.slug, b.name AS brand_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE oi.order_id = ?
");
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$items_q = $stmt_items->get_result();
$items = [];
while ($it = $items_q->fetch_assoc()) $items[] = $it;

// ✅ Prepared statement cho cart count
$stmt_cart = $conn->prepare("SELECT SUM(quantity) AS t FROM cart WHERE user_id = ?");
$stmt_cart->bind_param("i", $uid);
$stmt_cart->execute();
$cart_count = $stmt_cart->get_result()->fetch_assoc()['t'] ?? 0;

$status_labels = [
    'pending'   => ['label'=>'Chờ xác nhận','color'=>'#D97706','bg'=>'#FFFBEB','border'=>'#FDE68A','icon'=>'bi-clock',        'step'=>1],
    'confirmed' => ['label'=>'Đã xác nhận', 'color'=>'#2563EB','bg'=>'#EFF6FF','border'=>'#BFDBFE','icon'=>'bi-check-circle', 'step'=>2],
    'shipping'  => ['label'=>'Đang giao',   'color'=>'#7C3AED','bg'=>'#F5F3FF','border'=>'#DDD6FE','icon'=>'bi-truck',        'step'=>3],
    'delivered' => ['label'=>'Đã giao',     'color'=>'#16A34A','bg'=>'#F0FDF4','border'=>'#BBF7D0','icon'=>'bi-bag-check',    'step'=>4],
    'cancelled' => ['label'=>'Đã hủy',      'color'=>'#DC2626','bg'=>'#FEF2F2','border'=>'#FECACA','icon'=>'bi-x-circle',     'step'=>0],
];

$s       = $status_labels[$order['status']] ?? $status_labels['pending'];
$is_cod  = $order['payment_method'] === 'cod';
$is_paid = $order['payment_status'] === 'paid';

// ✅ payment_code fallback — tránh hiện "0" với đơn hàng cũ không có mã
$payment_code_display = !empty($order['payment_code']) ? $order['payment_code'] : ('ĐH#' . $order['id']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết đơn hàng <?= htmlspecialchars($payment_code_display) ?> - PhoneStore</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .detail-wrap { max-width:860px; margin:0 auto; padding:32px 24px 64px; }

        /* Breadcrumb */
        .bc { font-size:0.82rem; color:var(--gray); padding:10px 0; background:#fff; border-bottom:1px solid var(--border); }
        .bc a { color:var(--gray); text-decoration:none; }
        .bc a:hover { color:var(--primary); }

        /* Header card */
        .order-header-card {
            background:#fff; border:1px solid var(--border); border-radius:14px;
            padding:20px 24px; margin-bottom:16px;
            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;
        }
        .order-code-big { font-family:monospace; font-size:1.1rem; font-weight:800; color:var(--primary); }
        .order-date-small { font-size:0.78rem; color:var(--gray); margin-top:2px; }
        .status-pill {
            display:inline-flex; align-items:center; gap:6px;
            font-size:0.82rem; font-weight:700; padding:6px 16px;
            border-radius:100px; border:1.5px solid;
        }

        /* Progress bar trạng thái */
        .order-progress { background:#fff; border:1px solid var(--border); border-radius:14px; padding:20px 24px; margin-bottom:16px; }
        .progress-title { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--gray); margin-bottom:16px; }
        .progress-steps { display:flex; align-items:flex-start; justify-content:space-between; position:relative; }
        .progress-steps::before {
            content:''; position:absolute; top:16px; left:0; right:0; height:2px;
            background:var(--border); z-index:0;
        }
        .progress-fill {
            position:absolute; top:16px; left:0; height:2px;
            background:var(--primary); z-index:1; transition:width 0.4s;
        }
        .p-step { display:flex; flex-direction:column; align-items:center; gap:6px; position:relative; z-index:2; flex:1; }
        .p-step-dot {
            width:32px; height:32px; border-radius:50%; border:2px solid var(--border);
            background:#fff; display:flex; align-items:center; justify-content:center;
            font-size:0.85rem; color:var(--gray); font-weight:700; transition:all 0.3s;
        }
        .p-step.done .p-step-dot  { background:var(--primary); border-color:var(--primary); color:#fff; }
        .p-step.active .p-step-dot { background:#fff; border-color:var(--primary); color:var(--primary); box-shadow:0 0 0 4px rgba(0,87,255,0.12); }
        .p-step-label { font-size:0.7rem; font-weight:700; color:var(--gray); text-align:center; }
        .p-step.done .p-step-label, .p-step.active .p-step-label { color:var(--primary); }

        /* Info sections */
        .info-card { background:#fff; border:1px solid var(--border); border-radius:14px; overflow:hidden; margin-bottom:16px; }
        .info-card-header { padding:14px 20px; border-bottom:1px solid var(--border); background:#FAFAFA; font-size:0.78rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--gray); display:flex; align-items:center; gap:8px; }
        .info-card-body { padding:16px 20px; }
        .info-row { display:flex; justify-content:space-between; align-items:flex-start; padding:8px 0; font-size:0.875rem; border-bottom:1px solid #F9FAFB; }
        .info-row:last-child { border-bottom:none; }
        .info-row .lbl { color:var(--gray); flex-shrink:0; margin-right:16px; }
        .info-row .val { font-weight:700; color:var(--dark); text-align:right; }

        /* ETA banner */
        .eta-banner {
            display:flex; align-items:center; gap:14px;
            padding:16px 20px;
            background:linear-gradient(90deg,#F5F3FF,#EDE9FE);
            border:1px solid #DDD6FE; border-radius:14px;
            margin-bottom:16px;
        }
        .eta-banner.delivered { background:linear-gradient(90deg,#F0FDF4,#DCFCE7); border-color:#BBF7D0; }
        .eta-icon { font-size:2rem; flex-shrink:0; }
        .eta-label { font-size:0.7rem; font-weight:700; color:#7C3AED; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px; }
        .eta-banner.delivered .eta-label { color:#16A34A; }
        .eta-date { font-size:1rem; font-weight:800; color:#5B21B6; }
        .eta-banner.delivered .eta-date { color:#15803D; }
        .eta-sub { font-size:0.75rem; color:#7C3AED; margin-top:2px; }
        .eta-banner.delivered .eta-sub { color:#16A34A; }

        /* Items */
        .item-row {
            display:flex; align-items:center; gap:14px;
            padding:12px 0; border-bottom:1px solid #F9FAFB;
        }
        .item-row:last-child { border-bottom:none; }
        .item-img {
            width:60px; height:60px; border-radius:10px; overflow:hidden;
            background:var(--light); border:1px solid var(--border);
            display:flex; align-items:center; justify-content:center;
            font-size:1.5rem; flex-shrink:0;
        }
        .item-img img { width:100%; height:100%; object-fit:cover; }
        .item-name { font-size:0.875rem; font-weight:700; color:var(--dark); flex:1; }
        .item-brand { font-size:0.72rem; color:var(--gray); margin-top:2px; }
        .item-qty { font-size:0.82rem; color:var(--gray); white-space:nowrap; }
        .item-price { font-size:0.92rem; font-weight:800; color:var(--dark); white-space:nowrap; }

        /* Total */
        .total-box { background:#F8F8F8; border-radius:10px; padding:14px 20px; }
        .total-row { display:flex; justify-content:space-between; font-size:0.875rem; padding:4px 0; }
        .total-row .lbl { color:var(--gray); }
        .total-row .val { font-weight:700; }
        .total-row.grand { padding-top:10px; margin-top:6px; border-top:1.5px solid var(--border); }
        .total-row.grand .lbl { font-weight:800; color:var(--dark); font-size:0.95rem; }
        .total-row.grand .val { font-size:1.1rem; font-weight:800; color:#EF4444; }

        /* Back button */
        .btn-back { display:inline-flex; align-items:center; gap:6px; color:var(--gray); font-size:0.82rem; font-weight:700; text-decoration:none; background:#fff; border:1.5px solid var(--border); border-radius:8px; padding:7px 14px; transition:all 0.2s; }
        .btn-back:hover { border-color:var(--primary); color:var(--primary); }

        @media(max-width:600px) {
            .detail-wrap { padding:16px; }
            .order-header-card { flex-direction:column; align-items:flex-start; }
            .progress-steps { gap:4px; }
            .p-step-label { font-size:0.62rem; }
        }
    </style>
</head>
<body>


<!-- BREADCRUMB -->
<div class="bc">
    <div style="max-width:860px;margin:0 auto;padding:0 24px;">
        <a href="../index.php">Trang chủ</a>
        <i class="bi bi-chevron-right" style="font-size:0.7rem;margin:0 6px;"></i>
        <a href="orders.php">Đơn hàng của tôi</a>
        <i class="bi bi-chevron-right" style="font-size:0.7rem;margin:0 6px;"></i>
        <span style="color:var(--dark);font-weight:600;"><?= htmlspecialchars($payment_code_display) ?></span>
    </div>
</div>

<div class="detail-wrap">

    <div style="margin-bottom:16px;">
        <a href="orders.php" class="btn-back"><i class="bi bi-arrow-left"></i> Về danh sách đơn hàng</a>
    </div>

    <!-- Header card -->
    <div class="order-header-card">
        <div>
            <div class="order-code-big"><?= htmlspecialchars($payment_code_display) ?></div>
            <div class="order-date-small">
                <i class="bi bi-calendar3"></i>
                Đặt lúc <?= date('H:i · d/m/Y', strtotime($order['created_at'])) ?>
            </div>
        </div>
        <span class="status-pill" style="color:<?= $s['color'] ?>;background:<?= $s['bg'] ?>;border-color:<?= $s['border'] ?>">
            <i class="bi <?= $s['icon'] ?>"></i> <?= $s['label'] ?>
        </span>
    </div>

    <!-- Progress steps (ẩn nếu bị hủy) -->
    <?php if ($order['status'] !== 'cancelled'): ?>
    <div class="order-progress">
        <div class="progress-title"><i class="bi bi-activity"></i> Tiến trình đơn hàng</div>
        <?php
        $steps    = ['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','shipping'=>'Đang giao','delivered'=>'Đã giao'];
        $cur_step = $s['step'];
        $total_steps = count($steps) - 1;
        $fill_pct = $cur_step > 0 ? min(100, round(($cur_step - 1) / $total_steps * 100)) : 0;
        ?>
        <div class="progress-steps">
            <div class="progress-fill" style="width:<?= $fill_pct ?>%"></div>
            <?php foreach ($steps as $key => $label):
                $step_num = $status_labels[$key]['step'];
                $cls = $step_num < $cur_step ? 'done' : ($step_num === $cur_step ? 'active' : '');
            ?>
            <div class="p-step <?= $cls ?>">
                <div class="p-step-dot">
                    <?php if ($step_num < $cur_step): ?>
                        <i class="bi bi-check"></i>
                    <?php else: ?>
                        <?= $step_num ?>
                    <?php endif; ?>
                </div>
                <div class="p-step-label"><?= $label ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ETA banner -->
    <?php if ($order['status'] === 'shipping' && !empty($order['estimated_delivery'])): ?>
    <div class="eta-banner">
        <div class="eta-icon">🚚</div>
        <div>
            <div class="eta-label">Dự kiến giao hàng</div>
            <div class="eta-date"><?php
                $d = new DateTime($order['estimated_delivery']);
                $days_vn = ['Chủ nhật','Thứ hai','Thứ ba','Thứ tư','Thứ năm','Thứ sáu','Thứ bảy'];
                echo $days_vn[$d->format('w')] . ', ngày ' . $d->format('d/m/Y');
            ?></div>
            <div class="eta-sub">Vui lòng để ý điện thoại để nhận hàng đúng hẹn 📞</div>
        </div>
    </div>
    <?php elseif ($order['status'] === 'delivered'): ?>
    <div class="eta-banner delivered">
        <div class="eta-icon">✅</div>
        <div>
            <div class="eta-label">Giao hàng thành công</div>
            <div class="eta-date">Đơn hàng đã được giao đến bạn</div>
            <div class="eta-sub">Cảm ơn bạn đã mua hàng tại PhoneStore 🎉</div>
        </div>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:16px;align-items:start;">

        <!-- Left -->
        <div>
            <div class="info-card">
                <div class="info-card-header"><i class="bi bi-bag"></i> Sản phẩm đã mua</div>
                <div class="info-card-body">
                    <?php foreach ($items as $item): ?>
                    <div class="item-row">
                        <div class="item-img">
                            <?php if ($item['thumbnail']): ?>
                                <img src="../assets/images/products/<?= htmlspecialchars($item['thumbnail']) ?>" alt="">
                            <?php else: ?>📱<?php endif; ?>
                        </div>
                        <div style="flex:1">
                            <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                            <?php if ($item['brand_name']): ?>
                            <div class="item-brand"><?= htmlspecialchars($item['brand_name']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="text-align:right">
                            <div class="item-qty">x<?= $item['quantity'] ?></div>
                            <div class="item-price"><?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>đ</div>
                            <div style="font-size:0.7rem;color:var(--gray)"><?= number_format($item['price'], 0, ',', '.') ?>đ/cái</div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div style="margin-top:14px;">
                        <div class="total-box">
                            <?php
                            $subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
                            $ship     = $order['total_price'] - $subtotal;
                            ?>
                            <div class="total-row">
                                <span class="lbl">Tạm tính</span>
                                <span class="val"><?= number_format($subtotal, 0, ',', '.') ?>đ</span>
                            </div>
                            <div class="total-row">
                                <span class="lbl">Phí vận chuyển</span>
                                <span class="val <?= $ship==0?'free':'' ?>"><?= $ship===0?'Miễn phí':number_format($ship,0,',','.').'đ' ?></span>
                            </div>
                            <div class="total-row grand">
                                <span class="lbl">Tổng cộng</span>
                                <span class="val"><?= number_format($order['total_price'], 0, ',', '.') ?>đ</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right -->
        <div>
            <!-- Thông tin giao hàng -->
            <div class="info-card">
                <div class="info-card-header"><i class="bi bi-geo-alt"></i> Thông tin giao hàng</div>
                <div class="info-card-body">
                    <div class="info-row">
                        <span class="lbl">Người nhận</span>
                        <span class="val"><?= htmlspecialchars($order['full_name']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Số điện thoại</span>
                        <span class="val"><?= htmlspecialchars($order['phone']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Địa chỉ</span>
                        <span class="val"><?= htmlspecialchars($order['address']) ?></span>
                    </div>
                    <?php if ($order['note']): ?>
                    <div class="info-row">
                        <span class="lbl">Ghi chú</span>
                        <span class="val"><?= htmlspecialchars($order['note']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Thanh toán -->
            <div class="info-card">
                <div class="info-card-header"><i class="bi bi-credit-card"></i> Thanh toán</div>
                <div class="info-card-body">
                    <div class="info-row">
                        <span class="lbl">Phương thức</span>
                        <span class="val"><?= $is_cod ? '💵 COD' : '🏦 Chuyển khoản' ?></span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Trạng thái</span>
                        <span class="val" style="color:<?= $is_paid?'#16A34A':'#D97706' ?>">
                            <?= $is_paid ? '✓ Đã thanh toán' : '⏳ Chưa thanh toán' ?>
                        </span>
                    </div>
                    <?php if ($is_paid && $order['paid_at']): ?>
                    <div class="info-row">
                        <span class="lbl">Thời gian TT</span>
                        <span class="val"><?= date('H:i d/m/Y', strtotime($order['paid_at'])) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php
                    // ✅ FIX logic: chỉ hiện cảnh báo CK khi chưa paid VÀ là bank_transfer
                    // (đơn hàng mới sẽ luôn paid ngay với bank_transfer nên block này
                    //  chỉ xuất hiện với đơn cũ tạo trước khi fix)
                    if (!$is_paid && !$is_cod && !empty($order['payment_code'])):
                    ?>
                    <div style="margin-top:10px;padding:10px 12px;background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;font-size:0.78rem;color:#92400E;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Vui lòng chuyển khoản đúng số tiền và nội dung
                        <strong><?= htmlspecialchars($order['payment_code']) ?></strong>
                        để đơn được xử lý nhanh.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($order['status'] === 'pending'): ?>
            <form method="POST" action="orders.php" onsubmit="return confirm('Bạn chắc chắn muốn hủy đơn hàng này?')">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <button type="submit" name="cancel_order"
                        style="width:100%;background:transparent;color:#EF4444;border:1.5px solid #FECACA;border-radius:10px;padding:10px;font-size:0.875rem;font-weight:700;font-family:'Nunito',sans-serif;cursor:pointer;transition:all 0.2s;">
                    <i class="bi bi-x-circle"></i> Hủy đơn hàng
                </button>
            </form>
            <?php endif; ?>
        </div>

    </div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>