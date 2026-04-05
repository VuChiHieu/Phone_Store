<?php
session_start();
require_once '../config.php';
include '../includes/navbar.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$uid = $_SESSION['user_id'];

// ── HỦY ĐƠN HÀNG ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_id = (int)$_POST['order_id'];
    $stmt = $conn->prepare("
        UPDATE orders SET status = 'cancelled'
        WHERE id = ? AND user_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("ii", $order_id, $uid);
    $stmt->execute();
    header('Location: orders.php?cancelled=1');
    exit;
}

// ── LẤY DANH SÁCH ĐƠN HÀNG ──────────────────────────
$filter = $_GET['status'] ?? 'all';

$where = "WHERE o.user_id = $uid";
if ($filter !== 'all') {
    $f = $conn->real_escape_string($filter);
    $where .= " AND o.status = '$f'";
}

$orders = $conn->query("
    SELECT o.*,
           COUNT(oi.id) AS item_count,
           SUM(oi.quantity) AS total_qty
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    $where
    GROUP BY o.id
    ORDER BY o.created_at DESC
");

// Đếm theo trạng thái
$counts = [];
$rs = $conn->query("
    SELECT status, COUNT(*) as cnt FROM orders
    WHERE user_id = $uid GROUP BY status
");
while ($r = $rs->fetch_assoc()) $counts[$r['status']] = $r['cnt'];
$counts['all'] = array_sum($counts);

// Đếm giỏ hàng
$cart_count = 0;
$r = $conn->query("SELECT SUM(quantity) AS total FROM cart WHERE user_id = $uid");
$cart_count = $r->fetch_assoc()['total'] ?? 0;

$status_labels = [
    'pending'   => ['label' => 'Chờ xác nhận', 'color' => '#D97706', 'bg' => '#FFFBEB', 'border' => '#FDE68A', 'icon' => 'bi-clock'],
    'confirmed' => ['label' => 'Đã xác nhận',  'color' => '#2563EB', 'bg' => '#EFF6FF', 'border' => '#BFDBFE', 'icon' => 'bi-check-circle'],
    'shipping'  => ['label' => 'Đang giao',     'color' => '#7C3AED', 'bg' => '#F5F3FF', 'border' => '#DDD6FE', 'icon' => 'bi-truck'],
    'delivered' => ['label' => 'Đã giao',       'color' => '#16A34A', 'bg' => '#F0FDF4', 'border' => '#BBF7D0', 'icon' => 'bi-bag-check'],
    'cancelled' => ['label' => 'Đã hủy',        'color' => '#DC2626', 'bg' => '#FEF2F2', 'border' => '#FECACA', 'icon' => 'bi-x-circle'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng của tôi - Phone Store</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .orders-wrap {
            max-width: 960px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        /* Page header */
        .page-header { margin-bottom: 24px; }
        .page-header h1 { font-size: 1.5rem; font-weight: 800; color: var(--dark); margin-bottom: 4px; }
        .page-header p { color: var(--gray); font-size: 0.875rem; }

        /* Filter tabs */
        .filter-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 20px; }
        .filter-tab {
            display: flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 100px;
            font-size: 0.82rem; font-weight: 700;
            text-decoration: none; border: 1.5px solid var(--border);
            color: var(--gray); background: #fff; transition: all 0.2s;
        }
        .filter-tab:hover { border-color: var(--primary); color: var(--primary); }
        .filter-tab.active { background: var(--primary); border-color: var(--primary); color: #fff; }
        .filter-tab .count { background: rgba(0,0,0,0.1); padding: 1px 6px; border-radius: 100px; font-size: 0.72rem; }
        .filter-tab.active .count { background: rgba(255,255,255,0.25); }

        /* Alert */
        .alert-success-sm {
            background: #F0FDF4; border: 1px solid #BBF7D0; color: #16A34A;
            border-radius: 10px; padding: 10px 16px; font-size: 0.85rem;
            margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
        }

        /* Order card */
        .order-card {
            background: #fff; border: 1px solid var(--border);
            border-radius: 14px; overflow: hidden;
            margin-bottom: 16px; transition: box-shadow 0.2s;
        }
        .order-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
        .order-card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 20px; border-bottom: 1px solid var(--border);
            background: #FAFAFA; flex-wrap: wrap; gap: 8px;
        }
        .order-code {
            font-family: monospace; font-size: 0.85rem; font-weight: 800;
            color: var(--primary); background: #EEF4FF; padding: 3px 10px; border-radius: 6px;
        }
        .order-date { font-size: 0.78rem; color: var(--gray); }
        .order-status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 0.75rem; font-weight: 700;
            padding: 4px 12px; border-radius: 100px; border: 1px solid;
        }

        /* Shipping banner trên card */
        .shipping-eta-banner {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 20px;
            background: linear-gradient(90deg, #F5F3FF 0%, #EDE9FE 100%);
            border-bottom: 1px solid #DDD6FE;
            font-size: 0.82rem;
        }
        .shipping-eta-banner .eta-label { color: #7C3AED; font-weight: 600; }
        .shipping-eta-banner .eta-date  { color: #5B21B6; font-weight: 800; }

        /* Delivered banner trên card */
        .delivered-banner {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 20px;
            background: linear-gradient(90deg, #F0FDF4 0%, #DCFCE7 100%);
            border-bottom: 1px solid #BBF7D0;
            font-size: 0.82rem;
        }
        .delivered-banner .delivered-label { color: #16A34A; font-weight: 800; }

        /* Order items */
        .order-items-preview { padding: 16px 20px; display: flex; flex-direction: column; gap: 10px; }
        .order-item-row { display: flex; align-items: center; gap: 12px; }
        .order-item-img {
            width: 52px; height: 52px; border-radius: 8px;
            background: var(--light); overflow: hidden;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; flex-shrink: 0; border: 1px solid var(--border);
        }
        .order-item-img img { width: 100%; height: 100%; object-fit: cover; }
        .order-item-name { font-size: 0.875rem; font-weight: 600; color: var(--dark); flex: 1; }
        .order-item-qty { font-size: 0.78rem; color: var(--gray); }
        .order-item-price { font-size: 0.875rem; font-weight: 700; color: var(--dark); white-space: nowrap; }
        .order-more { font-size: 0.78rem; color: var(--gray); font-style: italic; }

        /* Order footer */
        .order-card-footer {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 20px; border-top: 1px solid var(--border);
            background: #FAFAFA; flex-wrap: wrap; gap: 10px;
        }
        .order-total { font-size: 0.875rem; color: var(--gray); }
        .order-total strong { font-size: 1rem; font-weight: 800; color: #EF4444; margin-left: 4px; }
        .order-payment { font-size: 0.78rem; color: var(--gray); display: flex; align-items: center; gap: 4px; }
        .order-actions { display: flex; gap: 8px; }
        .btn-order-detail {
            background: var(--primary); color: #fff; border: none; border-radius: 8px;
            padding: 7px 16px; font-size: 0.8rem; font-weight: 700;
            font-family: 'Nunito', sans-serif; cursor: pointer; text-decoration: none;
            transition: background 0.2s; display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-order-detail:hover { background: var(--primary-dark); color: #fff; }
        .btn-cancel {
            background: transparent; color: #EF4444; border: 1.5px solid #FECACA;
            border-radius: 8px; padding: 7px 14px; font-size: 0.8rem; font-weight: 700;
            font-family: 'Nunito', sans-serif; cursor: pointer; transition: all 0.2s;
        }
        .btn-cancel:hover { background: #FEF2F2; border-color: #EF4444; }

        /* Order detail modal */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); z-index: 9999;
            align-items: center; justify-content: center; padding: 16px;
        }
        .modal-overlay.show { display: flex; }
        .modal-box {
            background: #fff; border-radius: 16px; width: 100%;
            max-width: 600px; max-height: 90vh; overflow-y: auto;
        }
        .modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 24px; border-bottom: 1px solid var(--border);
            position: sticky; top: 0; background: #fff; z-index: 1;
        }
        .modal-title { font-weight: 800; font-size: 1rem; color: var(--dark); }
        .modal-close { background: none; border: none; font-size: 1.3rem; color: var(--gray); cursor: pointer; padding: 0; line-height: 1; }
        .modal-close:hover { color: var(--dark); }
        .modal-body { padding: 20px 24px; }
        .detail-row {
            display: flex; justify-content: space-between;
            padding: 8px 0; font-size: 0.875rem; border-bottom: 1px solid #F9FAFB;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-row .lbl { color: var(--gray); }
        .detail-row .val { font-weight: 700; color: var(--dark); text-align: right; }

        /* Empty */
        .empty-state { text-align: center; padding: 60px 20px; background: #fff; border: 1px solid var(--border); border-radius: 14px; }
        .empty-state-icon { font-size: 4rem; margin-bottom: 14px; }
        .empty-state h3 { font-weight: 800; color: var(--dark); margin-bottom: 6px; }
        .empty-state p { color: var(--gray); font-size: 0.875rem; margin-bottom: 20px; }
        .btn-shop {
            background: var(--primary); color: #fff; border: none; border-radius: 10px;
            padding: 11px 24px; font-size: 0.875rem; font-weight: 700;
            font-family: 'Nunito', sans-serif; text-decoration: none; transition: background 0.2s;
        }
        .btn-shop:hover { background: var(--primary-dark); color: #fff; }

        @media (max-width: 600px) {
            .orders-wrap { padding: 16px; }
            .order-card-header { flex-direction: column; align-items: flex-start; }
            .order-card-footer { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<!-- ══ BREADCRUMB ══ -->
<div style="background:#fff;border-bottom:1px solid var(--border);padding:10px 0;">
    <div style="max-width:960px;margin:0 auto;padding:0 24px;font-size:0.82rem;color:var(--gray);">
        <a href="../index.php" style="color:var(--gray);text-decoration:none;">Trang chủ</a>
        <i class="bi bi-chevron-right" style="font-size:0.7rem;margin:0 6px;"></i>
        <span style="color:var(--dark);font-weight:600;">Đơn hàng của tôi</span>
    </div>
</div>

<div class="orders-wrap">

    <div class="page-header">
        <h1><i class="bi bi-bag-check" style="color:var(--primary)"></i> Đơn hàng của tôi</h1>
        <p>Theo dõi và quản lý tất cả đơn hàng của bạn</p>
    </div>

    <?php if (isset($_GET['cancelled'])): ?>
    <div class="alert-success-sm">
        <i class="bi bi-check-circle-fill"></i> Đơn hàng đã được hủy thành công!
    </div>
    <?php endif; ?>

    <!-- Filter tabs -->
    <div class="filter-tabs">
        <a href="orders.php" class="filter-tab <?= $filter==='all' ? 'active' : '' ?>">
            Tất cả <span class="count"><?= $counts['all'] ?? 0 ?></span>
        </a>
        <?php foreach ($status_labels as $key => $s): ?>
        <a href="orders.php?status=<?= $key ?>"
           class="filter-tab <?= $filter===$key ? 'active' : '' ?>">
            <i class="bi <?= $s['icon'] ?>"></i>
            <?= $s['label'] ?>
            <?php if (!empty($counts[$key])): ?>
                <span class="count"><?= $counts[$key] ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Order list -->
    <?php if ($orders->num_rows > 0): ?>
        <?php while ($order = $orders->fetch_assoc()):
            $s = $status_labels[$order['status']] ?? $status_labels['pending'];

            // Lấy items của đơn
            $items_q = $conn->query("
                SELECT oi.*, p.name, p.thumbnail, b.name AS brand_name
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                LEFT JOIN brands b ON p.brand_id = b.id
                WHERE oi.order_id = {$order['id']}
                LIMIT 3
            ");
            $order_items = [];
            while ($it = $items_q->fetch_assoc()) $order_items[] = $it;
        ?>
        <div class="order-card">

            <!-- Header -->
            <div class="order-card-header">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span class="order-code"><?= $order['payment_code'] ?></span>
                    <span class="order-date">
                        <i class="bi bi-calendar3"></i>
                        <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                    </span>
                </div>
                <span class="order-status-badge"
                      style="color:<?= $s['color'] ?>;background:<?= $s['bg'] ?>;border-color:<?= $s['border'] ?>">
                    <i class="bi <?= $s['icon'] ?>"></i>
                    <?= $s['label'] ?>
                </span>
            </div>

            <!-- ✅ Banner dự kiến giao hàng hiển thị ngay trên card -->
            <?php if ($order['status'] === 'shipping' && !empty($order['estimated_delivery'])): ?>
            <div class="shipping-eta-banner">
                <span style="font-size:1.1rem">🚚</span>
                <span class="eta-label">Dự kiến giao hàng:&nbsp;</span>
                <span class="eta-date"><?= date('l, d/m/Y', strtotime($order['estimated_delivery'])) ?></span>
            </div>
            <?php elseif ($order['status'] === 'delivered'): ?>
            <div class="delivered-banner">
                <span style="font-size:1.1rem">✅</span>
                <span class="delivered-label">Đã giao hàng thành công</span>
            </div>
            <?php endif; ?>

            <!-- Items preview -->
            <div class="order-items-preview">
                <?php foreach ($order_items as $item): ?>
                <div class="order-item-row">
                    <div class="order-item-img">
                        <?php if ($item['thumbnail']): ?>
                            <img src="../assets/images/products/<?= htmlspecialchars($item['thumbnail']) ?>"
                                 alt="<?= htmlspecialchars($item['name']) ?>">
                        <?php else: ?>📱<?php endif; ?>
                    </div>
                    <div class="order-item-name"><?= htmlspecialchars($item['name']) ?></div>
                    <div class="order-item-qty">x<?= $item['quantity'] ?></div>
                    <div class="order-item-price"><?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>đ</div>
                </div>
                <?php endforeach; ?>
                <?php if ($order['item_count'] > 3): ?>
                <div class="order-more">+ <?= $order['item_count'] - 3 ?> sản phẩm khác...</div>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="order-card-footer">
                <div>
                    <div class="order-total">
                        Tổng tiền:
                        <strong><?= number_format($order['total_price'], 0, ',', '.') ?>đ</strong>
                    </div>
                    <div class="order-payment">
                        <i class="bi bi-credit-card"></i>
                        <?= $order['payment_method'] === 'cod' ? 'Thanh toán khi nhận hàng' : 'Chuyển khoản ngân hàng' ?>
                        <?php if ($order['payment_status'] === 'paid'): ?>
                            · <span style="color:#16A34A;font-weight:700"><i class="bi bi-check-circle-fill"></i> Đã thanh toán</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="order-actions">
                    <?php if ($order['status'] === 'pending'): ?>
                    <form method="POST" onsubmit="return confirm('Bạn chắc chắn muốn hủy đơn hàng này?')">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <button type="submit" name="cancel_order" class="btn-cancel">
                            <i class="bi bi-x-circle"></i> Hủy đơn
                        </button>
                    </form>
                    <?php endif; ?>
                    <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn-order-detail">
                        <i class="bi bi-eye"></i> Chi tiết
                    </a>
                </div>
            </div>

        </div>
        <?php endwhile; ?>

    <?php else: ?>
    <div class="empty-state">
        <div class="empty-state-icon">📦</div>
        <h3>Chưa có đơn hàng nào</h3>
        <p>Bạn chưa có đơn hàng <?= $filter !== 'all' ? '"'.$status_labels[$filter]['label'].'"' : '' ?> nào.</p>
        <a href="products.php" class="btn-shop">
            <i class="bi bi-bag"></i> Mua sắm ngay
        </a>
    </div>
    <?php endif; ?>

</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>

