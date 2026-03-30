<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$success = '';

// ── HÀM PHÂN VÙNG VẬN CHUYỂN ────────────────────────
function getShippingZone(string $address): array {
    $addr = mb_strtolower($address, 'UTF-8');

    $remote = [
        'phú quốc','côn đảo','côn sơn','trường sa','hoàng sa',
        'cà mau','năm căn','ngọc hiển','u minh',
        'kiên giang','hà tiên','rạch giá','giang thành',
        'bạc liêu','đông hải',
        'sóc trăng','cù lao dung','trần đề',
        'hậu giang','long mỹ','vị thủy',
        'đắk nông','tuy đức',
        'lai châu','sìn hồ','mường tè','nậm nhùn',
        'điện biên','mường nhé','nậm pồ',
        'sơn la','sốp cộp','mường lát',
        'cao bằng','bảo lạc','bảo lâm',
        'hà giang','đồng văn','mèo vạc','yên minh',
        'lào cai','si ma cai','mường khương',
        'bắc kạn','pác nặm','na rì',
        'lạng sơn','đình lập','bắc sơn',
        'tuyên quang','na hang','lâm bình',
        'yên bái','trạm tấu','mù cang chải',
        'kỳ sơn','tương dương','minh hóa','tuyên hóa',
    ];
    foreach ($remote as $kw) {
        if (mb_strpos($addr, $kw) !== false)
            return ['zone'=>'remote','days_add'=>6,'label'=>'Vùng sâu/xa/hải đảo','range'=>'5–7 ngày'];
    }

    $urban = [
        'hồ chí minh','tp.hcm','tp hcm','tphcm','ho chi minh',
        'quận 1','quận 2','quận 3','quận 4','quận 5','quận 6',
        'quận 7','quận 8','quận 9','quận 10','quận 11','quận 12',
        'bình thạnh','gò vấp','tân bình','tân phú','phú nhuận',
        'bình tân','hóc môn','củ chi','nhà bè','cần giờ','bình chánh',
        'thủ đức','thành phố thủ đức',
        'hà nội','ha noi',
        'ba đình','hoàn kiếm','hai bà trưng','đống đa','tây hồ',
        'cầu giấy','thanh xuân','hoàng mai','long biên',
        'nam từ liêm','bắc từ liêm','hà đông','sơn tây',
    ];
    foreach ($urban as $kw) {
        if (mb_strpos($addr, $kw) !== false)
            return ['zone'=>'urban','days_add'=>2,'label'=>'Nội thành HCM/HN','range'=>'1–2 ngày'];
    }

    $nearby = [
        'bình dương','thuận an','dĩ an','thủ dầu một',
        'đồng nai','biên hòa','long khánh','trảng bom',
        'long an','tân an','bến lức','đức hòa',
        'bà rịa','vũng tàu','brvt',
        'tây ninh','gò dầu','trảng bàng',
        'bình phước','đồng xoài','chơn thành',
        'hưng yên','hải dương','bắc ninh','vĩnh phúc',
        'hà nam','bắc giang','thái nguyên',
        'tiền giang','mỹ tho','cai lậy',
        'bến tre','châu thành',
        'vĩnh long','bình minh',
        'đồng tháp','sa đéc','cao lãnh',
        'an giang','long xuyên','châu đốc',
        'cần thơ','ninh kiều','bình thủy',
        'đà nẵng','hội an','tam kỳ',
        'thừa thiên huế','huế',
        'quảng nam','quảng ngãi',
        'bình định','quy nhơn',
        'phú yên','tuy hòa',
        'khánh hòa','nha trang','cam ranh',
    ];
    foreach ($nearby as $kw) {
        if (mb_strpos($addr, $kw) !== false)
            return ['zone'=>'nearby','days_add'=>3,'label'=>'Tỉnh lân cận','range'=>'2–3 ngày'];
    }

    return ['zone'=>'other','days_add'=>4,'label'=>'Các tỉnh khác','range'=>'3–5 ngày'];
}

// ── XÁC NHẬN THANH TOÁN (bank_transfer) ─────────────
// Admin nhấn nút "Xác nhận đã thanh toán" → set paid thủ công
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $order_id = (int)$_POST['order_id'];
    $now      = date('Y-m-d H:i:s');
    $stmt     = $conn->prepare("
        UPDATE orders SET payment_status = 'paid', paid_at = ?
        WHERE id = ? AND payment_method = 'bank_transfer'
    ");
    $stmt->bind_param("si", $now, $order_id);
    $stmt->execute();
    $success = '✅ Đã xác nhận thanh toán chuyển khoản!';
}

// ── CẬP NHẬT TRẠNG THÁI ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $status   = $_POST['status'];
    $valid    = ['pending','confirmed','shipping','delivered','cancelled'];

    if (in_array($status, $valid)) {
        $order = $conn->query("SELECT payment_method, payment_status FROM orders WHERE id = $order_id")->fetch_assoc();

        if ($status === 'shipping') {
            $addr_row  = $conn->query("SELECT address FROM orders WHERE id = $order_id")->fetch_assoc();
            $zone      = getShippingZone($addr_row['address'] ?? '');
            $estimated = date('Y-m-d', strtotime('+' . $zone['days_add'] . ' days'));

            $stmt = $conn->prepare("UPDATE orders SET status = ?, estimated_delivery = ? WHERE id = ?");
            $stmt->bind_param("ssi", $status, $estimated, $order_id);
            $success = '✅ Đã chuyển sang Đang giao · ' . $zone['label'] . ' · Dự kiến: ' . $zone['range'] . ' (trước ' . date('d/m/Y', strtotime($estimated)) . ')';

        } elseif ($status === 'delivered') {
            // ✅ FIX COD: Khi giao thành công + COD → tự động set paid
            if ($order['payment_method'] === 'cod' && $order['payment_status'] === 'unpaid') {
                $now  = date('Y-m-d H:i:s');
                $stmt = $conn->prepare("UPDATE orders SET status = ?, payment_status = 'paid', paid_at = ? WHERE id = ?");
                $stmt->bind_param("ssi", $status, $now, $order_id);
                $success = '✅ Đã giao hàng! Thanh toán COD được xác nhận tự động.';
            } else {
                // bank_transfer: chỉ cập nhật status, KHÔNG tự set paid
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $status, $order_id);
                $success = '✅ Cập nhật trạng thái thành công!';
            }

        } else {
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $order_id);
            $success = 'Cập nhật trạng thái thành công!';
        }

        $stmt->execute();
    }
}

// ── LỌC ─────────────────────────────────────────────
$filter   = $_GET['status'] ?? 'all';
$search   = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;

$where = "WHERE 1=1";
if ($filter !== 'all') {
    $f = $conn->real_escape_string($filter);
    $where .= " AND o.status = '$f'";
}
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (o.payment_code LIKE '%$s%' OR o.full_name LIKE '%$s%' OR o.phone LIKE '%$s%')";
}

$total_rows  = $conn->query("SELECT COUNT(*) AS c FROM orders o $where")->fetch_assoc()['c'];
$total_pages = max(1, ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$orders = $conn->query("
    SELECT o.*, u.full_name AS customer_name, u.email AS customer_email,
           COUNT(oi.id) AS item_count,
           SUM(oi.quantity) AS total_qty
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    $where
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT $per_page OFFSET $offset
");

$counts = ['all' => 0];
$rs = $conn->query("SELECT status, COUNT(*) AS c FROM orders GROUP BY status");
while ($r = $rs->fetch_assoc()) {
    $counts[$r['status']] = $r['c'];
    $counts['all'] += $r['c'];
}
$pending_orders = $counts['pending'] ?? 0;

// Đếm đơn bank_transfer chưa thanh toán để hiển thị badge cảnh báo
$unpaid_bank = $conn->query("
    SELECT COUNT(*) AS c FROM orders
    WHERE payment_method = 'bank_transfer' AND payment_status = 'unpaid'
    AND status NOT IN ('cancelled')
")->fetch_assoc()['c'];

$status_labels = [
    'pending'   => ['label' => 'Chờ xác nhận', 'color' => '#D97706', 'bg' => '#FFFBEB', 'icon' => 'bi-clock'],
    'confirmed' => ['label' => 'Đã xác nhận',  'color' => '#2563EB', 'bg' => '#EFF6FF', 'icon' => 'bi-check-circle'],
    'shipping'  => ['label' => 'Đang giao',    'color' => '#7C3AED', 'bg' => '#F5F3FF', 'icon' => 'bi-truck'],
    'delivered' => ['label' => 'Đã giao',      'color' => '#16A34A', 'bg' => '#F0FDF4', 'icon' => 'bi-bag-check'],
    'cancelled' => ['label' => 'Đã hủy',       'color' => '#DC2626', 'bg' => '#FEF2F2', 'icon' => 'bi-x-circle'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý đơn hàng - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #0057FF; --primary-dark: #0040CC;
            --dark: #0A0A0A; --gray: #6B7280;
            --light: #F8F8F8; --border: #E5E7EB;
            --sidebar-w: 240px; --font: 'Nunito', sans-serif;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:var(--font); background:#F3F4F6; color:var(--dark); }

        .sidebar { position:fixed; top:0; left:0; width:var(--sidebar-w); height:100vh; background:#0A0A0A; display:flex; flex-direction:column; z-index:100; overflow-y:auto; }
        .sidebar-brand { padding:20px 20px 16px; border-bottom:1px solid rgba(255,255,255,0.08); }
        .sidebar-brand a { font-size:1.3rem; font-weight:800; color:#fff; text-decoration:none; }
        .sidebar-brand a span { color:var(--primary); }
        .sidebar-brand-sub { font-size:0.68rem; color:rgba(255,255,255,0.3); font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-top:2px; }
        .sidebar-nav { padding:12px 0; flex:1; }
        .sidebar-section { font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:rgba(255,255,255,0.25); padding:14px 20px 6px; }
        .sidebar-item { display:flex; align-items:center; gap:10px; padding:10px 20px; color:rgba(255,255,255,0.55); text-decoration:none; font-size:0.875rem; font-weight:600; transition:all 0.2s; border-left:3px solid transparent; }
        .sidebar-item:hover { background:rgba(255,255,255,0.05); color:#fff; }
        .sidebar-item.active { background:rgba(0,87,255,0.15); color:#fff; border-left-color:var(--primary); }
        .sidebar-item i { font-size:1rem; width:18px; }
        .sidebar-badge { margin-left:auto; background:#EF4444; color:#fff; font-size:0.65rem; font-weight:700; padding:2px 6px; border-radius:100px; }
        .sidebar-badge.yellow { background:#D97706; }
        .sidebar-footer { padding:16px 20px; border-top:1px solid rgba(255,255,255,0.08); }
        .sidebar-user { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
        .sidebar-avatar { width:34px; height:34px; background:rgba(0,87,255,0.3); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#60A5FA; font-size:0.85rem; font-weight:800; }
        .sidebar-user-name { font-size:0.82rem; font-weight:700; color:#fff; }
        .sidebar-user-role { font-size:0.68rem; color:rgba(255,255,255,0.35); }
        .btn-logout { display:flex; align-items:center; gap:8px; width:100%; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2); color:#F87171; border-radius:8px; padding:8px 12px; font-size:0.8rem; font-weight:700; font-family:var(--font); cursor:pointer; text-decoration:none; transition:all 0.2s; }
        .btn-logout:hover { background:rgba(239,68,68,0.2); color:#FCA5A5; }

        .main-content { margin-left:var(--sidebar-w); min-height:100vh; }
        .admin-topbar { background:#fff; border-bottom:1px solid var(--border); padding:0 28px; height:60px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
        .admin-topbar-title { font-size:1rem; font-weight:800; color:var(--dark); }
        .page-body { padding:24px 28px; }

        .alert-success { background:#F0FDF4; border:1px solid #BBF7D0; color:#16A34A; border-radius:10px; padding:12px 16px; font-size:0.875rem; margin-bottom:16px; display:flex; align-items:center; gap:8px; font-weight:600; }

        /* Alert cảnh báo unpaid bank_transfer */
        .alert-warning { background:#FFFBEB; border:1px solid #FDE68A; color:#92400E; border-radius:10px; padding:12px 16px; font-size:0.875rem; margin-bottom:16px; display:flex; align-items:center; gap:8px; font-weight:600; }

        .filter-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px; }
        .filter-tab { display:flex; align-items:center; gap:5px; padding:7px 14px; border-radius:100px; font-size:0.8rem; font-weight:700; text-decoration:none; border:1.5px solid var(--border); color:var(--gray); background:#fff; transition:all 0.2s; }
        .filter-tab:hover { border-color:var(--primary); color:var(--primary); }
        .filter-tab.active { background:var(--primary); border-color:var(--primary); color:#fff; }
        .filter-tab .cnt { background:rgba(0,0,0,0.1); padding:1px 6px; border-radius:100px; font-size:0.7rem; }
        .filter-tab.active .cnt { background:rgba(255,255,255,0.25); }

        .toolbar { display:flex; align-items:center; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
        .search-input { background:#fff; border:1.5px solid var(--border); border-radius:10px; padding:9px 14px; font-size:0.875rem; font-family:var(--font); outline:none; width:280px; transition:border-color 0.2s; }
        .search-input:focus { border-color:var(--primary); }
        .btn-primary { background:var(--primary); color:#fff; border:none; border-radius:10px; padding:9px 16px; font-size:0.875rem; font-weight:700; font-family:var(--font); cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:background 0.2s; }
        .btn-primary:hover { background:var(--primary-dark); color:#fff; }
        .btn-secondary { background:#fff; color:var(--gray); border:1.5px solid var(--border); border-radius:10px; padding:9px 14px; font-size:0.875rem; font-weight:600; font-family:var(--font); cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s; }
        .btn-secondary:hover { border-color:var(--primary); color:var(--primary); }

        .table-card { background:#fff; border:1px solid var(--border); border-radius:14px; overflow:hidden; }
        .admin-table { width:100%; border-collapse:collapse; }
        .admin-table th { padding:10px 14px; text-align:left; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--gray); background:var(--light); border-bottom:1px solid var(--border); white-space:nowrap; }
        .admin-table td { padding:12px 14px; font-size:0.85rem; border-bottom:1px solid #F9FAFB; vertical-align:middle; }
        .admin-table tr:last-child td { border-bottom:none; }
        .admin-table tr:hover td { background:#FAFAFA; }

        /* Row highlight: bank_transfer chưa paid */
        .admin-table tr.unpaid-bank td { background:#FFFBEB; }
        .admin-table tr.unpaid-bank:hover td { background:#FEF3C7; }

        .status-select { border:1.5px solid var(--border); border-radius:8px; padding:5px 10px; font-size:0.78rem; font-weight:700; font-family:var(--font); outline:none; cursor:pointer; transition:border-color 0.2s; background:#fff; }
        .status-select:focus { border-color:var(--primary); }

        /* Nút xác nhận thanh toán */
        .btn-confirm-pay {
            display:inline-flex; align-items:center; gap:4px;
            background:#FEF3C7; color:#92400E;
            border:1.5px solid #FDE68A; border-radius:7px;
            padding:5px 10px; font-size:0.72rem; font-weight:700;
            font-family:var(--font); cursor:pointer; transition:all 0.2s;
            margin-top:4px;
        }
        .btn-confirm-pay:hover { background:#D97706; color:#fff; border-color:#D97706; }

        .btn-detail { background:#EEF4FF; color:var(--primary); border:none; border-radius:7px; padding:6px 12px; font-size:0.78rem; font-weight:700; font-family:var(--font); cursor:pointer; display:inline-flex; align-items:center; gap:4px; transition:all 0.2s; }
        .btn-detail:hover { background:var(--primary); color:#fff; }

        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:16px; }
        .modal-overlay.show { display:flex; }
        .modal-box { background:#fff; border-radius:16px; width:100%; max-width:620px; max-height:90vh; overflow-y:auto; }
        .modal-header { display:flex; align-items:center; justify-content:space-between; padding:18px 24px; border-bottom:1px solid var(--border); position:sticky; top:0; background:#fff; z-index:1; }
        .modal-title { font-weight:800; font-size:1rem; color:var(--dark); }
        .modal-close { background:none; border:none; font-size:1.3rem; color:var(--gray); cursor:pointer; padding:0; line-height:1; }
        .modal-close:hover { color:var(--dark); }
        .modal-body { padding:20px 24px; }
        .detail-row { display:flex; justify-content:space-between; padding:8px 0; font-size:0.875rem; border-bottom:1px solid #F9FAFB; }
        .detail-row:last-child { border-bottom:none; }
        .detail-row .lbl { color:var(--gray); }
        .detail-row .val { font-weight:700; color:var(--dark); text-align:right; max-width:60%; }

        .pagination-wrap { display:flex; justify-content:center; gap:6px; margin-top:20px; flex-wrap:wrap; }
        .page-btn { min-width:36px; height:36px; display:flex; align-items:center; justify-content:center; border:1.5px solid var(--border); border-radius:8px; font-size:0.82rem; font-weight:700; color:var(--dark); text-decoration:none; transition:all 0.2s; padding:0 10px; }
        .page-btn:hover { border-color:var(--primary); color:var(--primary); }
        .page-btn.active { background:var(--primary); border-color:var(--primary); color:#fff; }
        .page-btn.disabled { opacity:0.4; pointer-events:none; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <a href="index.php">Phone<span>Store</span></a>
        <div class="sidebar-brand-sub">Admin Panel</div>
    </div>
    <nav class="sidebar-nav">
        <div class="sidebar-section">Tổng quan</div>
        <a href="index.php" class="sidebar-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="sidebar-section">Quản lý</div>
        <a href="manage_products.php" class="sidebar-item"><i class="bi bi-phone"></i> Sản phẩm</a>
        <a href="manage_orders.php" class="sidebar-item active">
            <i class="bi bi-bag-check"></i> Đơn hàng
            <?php if ($pending_orders > 0): ?>
                <span class="sidebar-badge"><?= $pending_orders ?></span>
            <?php elseif ($unpaid_bank > 0): ?>
                <span class="sidebar-badge yellow"><?= $unpaid_bank ?></span>
            <?php endif; ?>
        </a>
        <a href="manage_users.php" class="sidebar-item"><i class="bi bi-people"></i> Khách hàng</a>
        <a href="manage_reviews.php" class="sidebar-item"><i class="bi bi-star"></i> Đánh giá</a>
        <div class="sidebar-section">Khác</div>
        <a href="../index.php" class="sidebar-item" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Xem website</a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar"><?= mb_strtoupper(mb_substr($_SESSION['full_name'], 0, 1)) ?></div>
            <div>
                <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                <div class="sidebar-user-role">Quản trị viên</div>
            </div>
        </div>
        <a href="../auth/logout.php" class="btn-logout"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
    </div>
</aside>

<div class="main-content">
    <div class="admin-topbar">
        <div class="admin-topbar-title">📦 Quản lý đơn hàng</div>
        <span style="font-size:0.78rem;color:var(--gray)">Tổng: <?= $counts['all'] ?? 0 ?> đơn hàng</span>
    </div>

    <div class="page-body">

        <?php if ($success): ?>
        <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= $success ?></div>
        <?php endif; ?>

        <?php if ($unpaid_bank > 0 && !$success): ?>
        <div class="alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Có <strong><?= $unpaid_bank ?></strong> đơn chuyển khoản chưa xác nhận thanh toán.
            Kiểm tra tài khoản ngân hàng và xác nhận thủ công cho từng đơn.
        </div>
        <?php endif; ?>

        <div class="filter-tabs">
            <a href="manage_orders.php" class="filter-tab <?= $filter==='all'?'active':'' ?>">
                Tất cả <span class="cnt"><?= $counts['all'] ?? 0 ?></span>
            </a>
            <?php foreach ($status_labels as $key => $s): ?>
            <a href="manage_orders.php?status=<?= $key ?><?= $search ? '&q='.urlencode($search) : '' ?>"
               class="filter-tab <?= $filter===$key?'active':'' ?>">
                <i class="bi <?= $s['icon'] ?>"></i> <?= $s['label'] ?>
                <?php if (!empty($counts[$key])): ?><span class="cnt"><?= $counts[$key] ?></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" action="manage_orders.php">
            <?php if ($filter !== 'all'): ?>
            <input type="hidden" name="status" value="<?= $filter ?>">
            <?php endif; ?>
            <div class="toolbar">
                <input type="text" name="q" class="search-input"
                       placeholder="🔍 Tìm mã đơn, tên, SĐT..."
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-primary"><i class="bi bi-search"></i> Tìm</button>
                <?php if ($search): ?>
                <a href="manage_orders.php<?= $filter !== 'all' ? '?status='.$filter : '' ?>" class="btn-secondary">
                    <i class="bi bi-x"></i> Xóa
                </a>
                <?php endif; ?>
                <span style="margin-left:auto;font-size:0.82rem;color:var(--gray)"><?= $total_rows ?> kết quả</span>
            </div>
        </form>

        <div class="table-card">
            <div style="overflow-x:auto">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>Sản phẩm</th>
                            <th>Tổng tiền</th>
                            <th>Thanh toán</th>
                            <th>Trạng thái</th>
                            <th>Ngày đặt</th>
                            <th style="width:100px">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders->num_rows > 0): ?>
                            <?php while ($o = $orders->fetch_assoc()):
                                $s = $status_labels[$o['status']] ?? $status_labels['pending'];
                                $is_unpaid_bank = ($o['payment_method'] === 'bank_transfer' && $o['payment_status'] === 'unpaid' && $o['status'] !== 'cancelled');
                            ?>
                            <tr class="<?= $is_unpaid_bank ? 'unpaid-bank' : '' ?>">
                                <td>
                                    <div style="font-family:monospace;font-size:0.78rem;color:var(--primary);font-weight:700"><?= $o['payment_code'] ?></div>
                                    <div style="font-size:0.7rem;color:var(--gray)">#<?= $o['id'] ?></div>
                                </td>
                                <td>
                                    <div style="font-weight:700"><?= htmlspecialchars($o['full_name']) ?></div>
                                    <div style="font-size:0.72rem;color:var(--gray)"><?= htmlspecialchars($o['phone']) ?></div>
                                </td>
                                <td style="color:var(--gray);font-size:0.82rem">
                                    <?= $o['item_count'] ?> loại · <?= $o['total_qty'] ?> sản phẩm
                                </td>
                                <td style="font-weight:800;color:#EF4444;white-space:nowrap">
                                    <?= number_format($o['total_price'],0,',','.') ?>đ
                                </td>
                                <td>
                                    <div style="font-size:0.78rem;font-weight:600">
                                        <?= $o['payment_method'] === 'cod' ? '💵 COD' : '🏦 CK' ?>
                                    </div>
                                    <div style="font-size:0.7rem;color:<?= $o['payment_status']==='paid' ? '#16A34A' : '#D97706' ?>;font-weight:700">
                                        <?= $o['payment_status'] === 'paid' ? '✓ Đã TT' : '⏳ Chưa TT' ?>
                                    </div>
                                    <?php if ($is_unpaid_bank): ?>
                                    <!-- ✅ Nút xác nhận đã CK cho bank_transfer -->
                                    <form method="POST" style="margin:0" onsubmit="return confirm('Xác nhận khách đã chuyển khoản đơn #<?= $o['id'] ?>?')">
                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                        <button type="submit" name="confirm_payment" class="btn-confirm-pay">
                                            <i class="bi bi-check-circle"></i> Xác nhận CK
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                        <input type="hidden" name="update_status" value="1">
                                        <select name="status" class="status-select"
                                                onchange="this.form.submit()"
                                                style="color:<?= $s['color'] ?>">
                                            <?php foreach ($status_labels as $key => $sl): ?>
                                            <option value="<?= $key ?>" <?= $o['status']===$key?'selected':'' ?>>
                                                <?= $sl['label'] ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <?php if ($o['status'] === 'shipping' && !empty($o['estimated_delivery'])): ?>
                                    <div style="font-size:0.68rem;color:#7C3AED;margin-top:4px;font-weight:600">
                                        🚚 DK: <?= date('d/m', strtotime($o['estimated_delivery'])) ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td style="color:var(--gray);font-size:0.78rem;white-space:nowrap">
                                    <?= date('d/m/Y', strtotime($o['created_at'])) ?><br>
                                    <?= date('H:i', strtotime($o['created_at'])) ?>
                                </td>
                                <td>
                                    <a href="order_detail.php?id=<?= $o['id'] ?>" class="btn-detail">
                                        <i class="bi bi-eye"></i> Chi tiết
                                    </a>
                                    <form method="POST" action="order_detail.php?id=<?= $o['id'] ?>" style="display:inline;margin-left:4px"
                                          onsubmit="return confirm('Xóa đơn #<?= $o['id'] ?>? Không thể hoàn tác!')">
                                        <button type="submit" name="delete_order"
                                                style="background:#FEF2F2;color:#DC2626;border:1.5px solid #FECACA;border-radius:7px;padding:6px 10px;font-size:0.78rem;font-weight:700;font-family:var(--font);cursor:pointer;transition:all 0.2s;"
                                                onmouseover="this.style.background='#DC2626';this.style.color='#fff'"
                                                onmouseout="this.style.background='#FEF2F2';this.style.color='#DC2626'">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;padding:40px;color:var(--gray)">
                                    <div style="font-size:2.5rem;margin-bottom:8px">📭</div>
                                    Không có đơn hàng nào
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1):
            $base = '?status='.$filter.($search?'&q='.urlencode($search):'');
        ?>
        <div class="pagination-wrap">
            <a href="<?= $base ?>&page=<?= $page-1 ?>" class="page-btn <?= $page<=1?'disabled':'' ?>"><i class="bi bi-chevron-left"></i></a>
            <?php for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
            <a href="<?= $base ?>&page=<?= $i ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="<?= $base ?>&page=<?= $page+1 ?>" class="page-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="bi bi-chevron-right"></i></a>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- MODAL CHI TIẾT -->
<div class="modal-overlay" id="orderModal" onclick="if(event.target===this)closeModal()">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Chi tiết đơn hàng</div>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="modal-body" id="modalBody">
            <div style="text-align:center;padding:32px;color:var(--gray)">⏳ Đang tải...</div>
        </div>
    </div>
</div>

<script>
const ordersData = <?php
    $all = $conn->query("
        SELECT o.*, oi.quantity, oi.price AS item_price,
               p.name AS product_name, p.thumbnail
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        ORDER BY o.created_at DESC
    ");
    $data = [];
    while ($row = $all->fetch_assoc()) {
        $oid = $row['id'];
        if (!isset($data[$oid])) {
            $data[$oid] = [
                'id'                 => $row['id'],
                'payment_code'       => $row['payment_code'],
                'full_name'          => $row['full_name'],
                'phone'              => $row['phone'],
                'address'            => $row['address'],
                'total_price'        => $row['total_price'],
                'status'             => $row['status'],
                'estimated_delivery' => $row['estimated_delivery'] ?? null,
                'payment_method'     => $row['payment_method'],
                'payment_status'     => $row['payment_status'],
                'paid_at'            => $row['paid_at'] ?? null,
                'note'               => $row['note'] ?? '',
                'created_at'         => $row['created_at'],
                'items'              => []
            ];
        }
        $data[$oid]['items'][] = [
            'name'      => $row['product_name'],
            'thumbnail' => $row['thumbnail'],
            'quantity'  => $row['quantity'],
            'price'     => $row['item_price'],
        ];
    }
    echo json_encode(array_values($data));
?>;

const statusLabels = {
    pending:   { label:'Chờ xác nhận', color:'#D97706', bg:'#FFFBEB', icon:'⏳' },
    confirmed: { label:'Đã xác nhận',  color:'#2563EB', bg:'#EFF6FF', icon:'✅' },
    shipping:  { label:'Đang giao',    color:'#7C3AED', bg:'#F5F3FF', icon:'🚚' },
    delivered: { label:'Đã giao',      color:'#16A34A', bg:'#F0FDF4', icon:'📦' },
    cancelled: { label:'Đã hủy',       color:'#DC2626', bg:'#FEF2F2', icon:'❌' },
};

function formatDateVN(dateStr) {
    if (!dateStr) return null;
    const [y, m, d] = dateStr.split('-').map(Number);
    const date = new Date(y, m - 1, d);
    const days = ['Chủ nhật','Thứ hai','Thứ ba','Thứ tư','Thứ năm','Thứ sáu','Thứ bảy'];
    return `${days[date.getDay()]}, ${String(d).padStart(2,'0')}/${String(m).padStart(2,'0')}/${y}`;
}

function showOrderDetail(orderId) {
    const order = ordersData.find(o => o.id === orderId);
    if (!order) return;
    const s = statusLabels[order.status] || statusLabels.pending;

    let estimatedHtml = '';
    if (order.status === 'shipping' && order.estimated_delivery) {
        estimatedHtml = `
            <div style="background:linear-gradient(90deg,#F5F3FF,#EDE9FE);border:1px solid #DDD6FE;border-radius:10px;padding:14px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
                <span style="font-size:1.6rem">🚚</span>
                <div>
                    <div style="font-size:0.7rem;font-weight:700;color:#7C3AED;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px">Dự kiến giao hàng</div>
                    <div style="font-size:1rem;font-weight:800;color:#5B21B6">${formatDateVN(order.estimated_delivery)}</div>
                </div>
            </div>`;
    } else if (order.status === 'delivered') {
        estimatedHtml = `
            <div style="background:linear-gradient(90deg,#F0FDF4,#DCFCE7);border:1px solid #BBF7D0;border-radius:10px;padding:14px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
                <span style="font-size:1.6rem">✅</span>
                <div>
                    <div style="font-size:0.7rem;font-weight:700;color:#16A34A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px">Giao hàng thành công</div>
                    <div style="font-size:0.92rem;font-weight:800;color:#15803D">Đơn hàng đã được giao đến khách hàng</div>
                </div>
            </div>`;
    }

    // Badge thanh toán chi tiết
    let payBadge = '';
    if (order.payment_status === 'paid') {
        const paidTime = order.paid_at ? ` · ${new Date(order.paid_at).toLocaleString('vi-VN')}` : '';
        payBadge = `<span style="color:#16A34A;font-weight:700">✓ Đã thanh toán${paidTime}</span>`;
    } else {
        const warn = order.payment_method === 'bank_transfer'
            ? ' <span style="color:#D97706;font-size:0.72rem">(chờ admin xác nhận)</span>'
            : ' <span style="color:#D97706;font-size:0.72rem">(thu khi giao hàng)</span>';
        payBadge = `<span style="color:#D97706">⏳ Chưa thanh toán</span>${warn}`;
    }

    const itemsHtml = order.items.map(item => `
        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #F9FAFB">
            <div style="width:44px;height:44px;border-radius:8px;background:#F8F8F8;border:1px solid #E5E7EB;display:flex;align-items:center;justify-content:center;font-size:1.3rem;overflow:hidden;flex-shrink:0">
                ${item.thumbnail ? `<img src="../assets/images/products/${item.thumbnail}" style="width:100%;height:100%;object-fit:cover">` : '📱'}
            </div>
            <div style="flex:1;font-size:0.82rem;font-weight:600">${item.name}</div>
            <div style="font-size:0.75rem;color:#6B7280;white-space:nowrap">x${item.quantity}</div>
            <div style="font-size:0.85rem;font-weight:700;white-space:nowrap">${Number(item.price*item.quantity).toLocaleString('vi-VN')}đ</div>
        </div>
    `).join('');

    document.getElementById('modalBody').innerHTML = `
        <div style="background:${s.bg};border-radius:10px;padding:12px 16px;margin-bottom:16px;text-align:center">
            <span style="font-size:1.3rem">${s.icon}</span>
            <span style="font-weight:800;color:${s.color};margin-left:8px">${s.label}</span>
        </div>
        ${estimatedHtml}
        <div style="margin-bottom:16px">
            <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#6B7280;margin-bottom:8px">Thông tin đơn hàng</div>
            <div class="detail-row"><span class="lbl">Mã đơn</span><span class="val" style="font-family:monospace;color:#0057FF">${order.payment_code}</span></div>
            <div class="detail-row"><span class="lbl">Ngày đặt</span><span class="val">${new Date(order.created_at).toLocaleString('vi-VN')}</span></div>
            <div class="detail-row"><span class="lbl">Người nhận</span><span class="val">${order.full_name}</span></div>
            <div class="detail-row"><span class="lbl">Điện thoại</span><span class="val">${order.phone}</span></div>
            <div class="detail-row"><span class="lbl">Địa chỉ</span><span class="val">${order.address}</span></div>
            ${order.note ? `<div class="detail-row"><span class="lbl">Ghi chú</span><span class="val">${order.note}</span></div>` : ''}
            <div class="detail-row">
                <span class="lbl">Thanh toán</span>
                <span class="val" style="text-align:right">
                    ${order.payment_method === 'cod' ? '💵 COD' : '🏦 Chuyển khoản'}<br>
                    <span style="font-size:0.78rem">${payBadge}</span>
                </span>
            </div>
        </div>
        <div style="margin-bottom:16px">
            <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#6B7280;margin-bottom:8px">Sản phẩm</div>
            ${itemsHtml}
        </div>
        <div style="background:#F8F8F8;border-radius:10px;padding:14px 16px;display:flex;justify-content:space-between;align-items:center">
            <span style="font-weight:700">Tổng cộng</span>
            <span style="font-size:1.1rem;font-weight:800;color:#EF4444">${Number(order.total_price).toLocaleString('vi-VN')}đ</span>
        </div>
    `;
    document.getElementById('orderModal').classList.add('show');
}

function closeModal() {
    document.getElementById('orderModal').classList.remove('show');
}
</script>
</body>
</html>