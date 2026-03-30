<?php
    session_start();
    require_once '../config.php';

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: ../auth/login.php');
        exit;
    }

    $success = '';
    $error   = '';

    // ── XÓA BANNER ──────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_banner'])) {
        $bid = (int)$_POST['banner_id'];
        // Xóa ảnh nếu có
        $old = $conn->query("SELECT image FROM promotions WHERE id=$bid")->fetch_assoc();
        if ($old['image'] && file_exists('../assets/images/banners/'.$old['image'])) {
            unlink('../assets/images/banners/'.$old['image']);
        }
        $conn->query("DELETE FROM promotions WHERE id=$bid");
        $success = 'Đã xóa banner!';
    }

    // ── BẬT/TẮT BANNER ──────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_banner'])) {
        $bid    = (int)$_POST['banner_id'];
        $active = (int)$_POST['current_active'] === 1 ? 0 : 1;
        $conn->query("UPDATE promotions SET is_active=$active WHERE id=$bid");
        $success = $active ? 'Đã bật banner!' : 'Đã tắt banner!';
    }

    // ── THÊM / SỬA BANNER ───────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_banner'])) {
        $title       = trim($_POST['title']);
        $description = trim($_POST['description']);
        $start_date  = $_POST['start_date'] ?: null;
        $end_date    = $_POST['end_date']   ?: null;
        $is_active   = isset($_POST['is_active']) ? 1 : 0;
        $bid         = (int)($_POST['banner_id'] ?? 0);
        $link_url    = trim($_POST['link_url'] ?? '');
        $banner_type = $_POST['banner_type'] ?? 'main';

        if (empty($title)) {
            $error = 'Vui lòng nhập tiêu đề banner!';
        } else {
            // Upload ảnh
            $image = trim($_POST['image_current'] ?? '');
            if (!empty($_FILES['image']['name'])) {
                // Tạo thư mục nếu chưa có
                if (!is_dir('../assets/images/banners/')) {
                    mkdir('../assets/images/banners/', 0755, true);
                }
                $ext      = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $filename = 'banner-' . time() . '.' . $ext;
                $dest     = '../assets/images/banners/' . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                    // Xóa ảnh cũ
                    if ($image && file_exists('../assets/images/banners/'.$image)) {
                        unlink('../assets/images/banners/'.$image);
                    }
                    $image = $filename;
                }
            }

            if ($bid > 0) {
                $stmt = $conn->prepare("UPDATE promotions SET title=?,description=?,image=?,start_date=?,end_date=?,is_active=?,link_url=?,banner_type=? WHERE id=?");
                $stmt->bind_param("sssssissi", $title, $description, $image, $start_date, $end_date, $is_active, $link_url, $banner_type, $bid);
            } else {
                $stmt = $conn->prepare("INSERT INTO promotions (title,description,image,start_date,end_date,is_active,link_url,banner_type) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->bind_param("sssssiss", $title, $description, $image, $start_date, $end_date, $is_active, $link_url, $banner_type);
            }
            $stmt->execute();
            $success = $bid > 0 ? 'Cập nhật banner thành công!' : 'Thêm banner thành công!';
        }
    }

    // ── DỮ LIỆU ─────────────────────────────────────────
    $banners    = $conn->query("SELECT * FROM promotions ORDER BY id DESC");
    $action     = $_GET['action'] ?? 'list';
    $edit_id    = (int)($_GET['id'] ?? 0);
    $edit_banner = null;
    if ($action === 'edit' && $edit_id > 0) {
        $edit_banner = $conn->query("SELECT * FROM promotions WHERE id=$edit_id")->fetch_assoc();
        if (!$edit_banner) $action = 'list';
    }
    $pending_orders = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status='pending'")->fetch_assoc()['c'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Banner - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary:#0057FF; --primary-dark:#0040CC;
            --dark:#0A0A0A; --gray:#6B7280;
            --light:#F8F8F8; --border:#E5E7EB;
            --sidebar-w:240px; --font:'Nunito',sans-serif;
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
        .alert-error   { background:#FEF2F2; border:1px solid #FECACA; color:#DC2626; border-radius:10px; padding:12px 16px; font-size:0.875rem; margin-bottom:16px; display:flex; align-items:center; gap:8px; }

        .btn-primary { background:var(--primary); color:#fff; border:none; border-radius:10px; padding:9px 18px; font-size:0.875rem; font-weight:700; font-family:var(--font); cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:background 0.2s; }
        .btn-primary:hover { background:var(--primary-dark); color:#fff; }
        .btn-secondary { background:#fff; color:var(--gray); border:1.5px solid var(--border); border-radius:10px; padding:9px 16px; font-size:0.875rem; font-weight:600; font-family:var(--font); cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s; }
        .btn-secondary:hover { border-color:var(--primary); color:var(--primary); }

        /* Banner cards */
        .banners-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:16px; }
        .banner-card {
            background:#fff;
            border:1px solid var(--border);
            border-radius:14px;
            overflow:hidden;
            transition:all 0.2s;
        }
        .banner-card:hover { box-shadow:0 6px 20px rgba(0,0,0,0.08); transform:translateY(-2px); }
        .banner-card.inactive { opacity:0.6; }

        .banner-img {
            width:100%; height:160px;
            background:linear-gradient(135deg, #0A0A0A, #0d1b3e);
            display:flex; align-items:center; justify-content:center;
            font-size:4rem;
            position:relative;
            overflow:hidden;
        }
        .banner-img img { width:100%; height:100%; object-fit:cover; }
        .banner-status-pill {
            position:absolute;
            top:10px; right:10px;
            font-size:0.68rem;
            font-weight:700;
            padding:3px 10px;
            border-radius:100px;
        }
        .pill-active   { background:#F0FDF4; color:#16A34A; }
        .pill-inactive { background:#FEF2F2; color:#EF4444; }
        .pill-expired  { background:#F3F4F6; color:#6B7280; }

        .banner-body { padding:14px 16px; }
        .banner-title-text { font-weight:800; font-size:0.95rem; color:var(--dark); margin-bottom:4px; }
        .banner-desc-text  { font-size:0.82rem; color:var(--gray); margin-bottom:8px; line-height:1.4; }
        .banner-dates {
            display:flex; align-items:center; gap:6px;
            font-size:0.72rem; color:var(--gray); margin-bottom:12px;
        }
        .banner-dates i { color:var(--primary); }

        .banner-actions { display:flex; gap:6px; }
        .btn-sm { border:none; border-radius:7px; padding:6px 12px; font-size:0.78rem; font-weight:700; font-family:var(--font); cursor:pointer; display:inline-flex; align-items:center; gap:4px; transition:all 0.2s; }
        .btn-edit    { background:#EEF4FF; color:var(--primary); }
        .btn-edit:hover { background:var(--primary); color:#fff; }
        .btn-toggle-on  { background:#F0FDF4; color:#16A34A; }
        .btn-toggle-on:hover { background:#16A34A; color:#fff; }
        .btn-toggle-off { background:#FFFBEB; color:#D97706; }
        .btn-toggle-off:hover { background:#D97706; color:#fff; }
        .btn-delete  { background:#FEF2F2; color:#EF4444; }
        .btn-delete:hover { background:#EF4444; color:#fff; }

        /* Form */
        .form-card { background:#fff; border:1px solid var(--border); border-radius:14px; overflow:hidden; }
        .form-card-header { padding:16px 24px; border-bottom:1px solid var(--border); font-weight:800; font-size:1rem; color:var(--dark); display:flex; align-items:center; gap:8px; }
        .form-card-header i { color:var(--primary); }
        .form-card-body { padding:24px; }
        .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .form-group { margin-bottom:16px; }
        .form-group:last-child { margin-bottom:0; }
        .form-label { display:block; font-size:0.82rem; font-weight:700; color:var(--dark); margin-bottom:6px; }
        .form-label .req { color:#EF4444; }
        .form-control { width:100%; background:var(--light); border:1.5px solid var(--border); border-radius:10px; padding:10px 14px; font-size:0.875rem; font-family:var(--font); color:var(--dark); outline:none; transition:border-color 0.2s; }
        .form-control:focus { border-color:var(--primary); background:#fff; }
        textarea.form-control { resize:vertical; min-height:80px; }
        .form-check { display:flex; align-items:center; gap:8px; }
        .form-check input { width:18px; height:18px; accent-color:var(--primary); cursor:pointer; }
        .form-check label { font-size:0.875rem; font-weight:600; cursor:pointer; }

        .img-preview {
            width:100%; height:120px;
            border:2px dashed var(--border);
            border-radius:10px;
            background:var(--light);
            display:flex; align-items:center; justify-content:center;
            font-size:2.5rem;
            margin-top:8px;
            overflow:hidden;
        }
        .img-preview img { width:100%; height:100%; object-fit:cover; }

        /* Empty */
        .empty-state { text-align:center; padding:48px; background:#fff; border:1px solid var(--border); border-radius:14px; }

        @media (max-width:900px) {
            .form-grid-2 { grid-template-columns:1fr; }
            .banners-grid { grid-template-columns:1fr; }
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
        <a href="index.php" class="sidebar-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="sidebar-section">Quản lý</div>
        <a href="manage_products.php" class="sidebar-item"><i class="bi bi-phone"></i> Sản phẩm</a>
        <a href="manage_orders.php" class="sidebar-item">
            <i class="bi bi-bag-check"></i> Đơn hàng
            <?php if ($pending_orders > 0): ?><span class="sidebar-badge"><?= $pending_orders ?></span><?php endif; ?>
        </a>
        <a href="manage_users.php" class="sidebar-item"><i class="bi bi-people"></i> Khách hàng</a>
        <a href="manage_reviews.php" class="sidebar-item"><i class="bi bi-star"></i> Đánh giá</a>
        <a href="manage_banners.php" class="sidebar-item active"><i class="bi bi-image"></i> Banner</a>
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

<!-- ══ MAIN ══ -->
<div class="main-content">
    <div class="admin-topbar">
        <div class="admin-topbar-title">
            <?= $action === 'list' ? '🖼️ Quản lý Banner' : ($action === 'add' ? '➕ Thêm Banner' : '✏️ Sửa Banner') ?>
        </div>
        <div style="display:flex;gap:8px">
            <?php if ($action !== 'list'): ?>
            <a href="manage_banners.php" class="btn-secondary"><i class="bi bi-arrow-left"></i> Quay lại</a>
            <?php else: ?>
            <a href="manage_banners.php?action=add" class="btn-primary"><i class="bi bi-plus-lg"></i> Thêm banner</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="page-body">

        <?php if ($success): ?>
        <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert-error"><i class="bi bi-exclamation-circle-fill"></i> <?= $error ?></div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
        <!-- ══ DANH SÁCH BANNER ══ -->

        <!-- Hướng dẫn nhanh -->
        <div style="background:#EEF4FF;border:1px solid #C7D9FF;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:0.82rem;color:#1D4ED8;display:flex;align-items:center;gap:8px;">
            <i class="bi bi-info-circle-fill"></i>
            Banner đang bật sẽ hiển thị ở trang chủ. Chỉ banner <strong>mới nhất + đang bật + trong thời hạn</strong> mới được hiển thị.
        </div>

        <?php if ($banners->num_rows > 0): ?>
        <div class="banners-grid">
            <?php while ($b = $banners->fetch_assoc()):
                // Xác định trạng thái
                $now      = date('Y-m-d');
                $expired  = $b['end_date'] && $b['end_date'] < $now;
                $notyet   = $b['start_date'] && $b['start_date'] > $now;
                $pillClass = !$b['is_active'] ? 'pill-inactive' : ($expired ? 'pill-expired' : 'pill-active');
                $pillText  = !$b['is_active'] ? 'Đã tắt' : ($expired ? 'Hết hạn' : ($notyet ? 'Chưa bắt đầu' : 'Đang hiển thị'));
            ?>
            <div class="banner-card <?= !$b['is_active'] ? 'inactive' : '' ?>">
                <div class="banner-img">
                    <?php if ($b['image'] && file_exists('../assets/images/banners/'.$b['image'])): ?>
                        <img src="../assets/images/banners/<?= htmlspecialchars($b['image']) ?>" alt="">
                    <?php else: ?>
                        🖼️
                    <?php endif; ?>
                    <span class="banner-status-pill <?= $pillClass ?>"><?= $pillText ?></span>
                </div>
                <div class="banner-body">
                    <div class="banner-title-text"><?= htmlspecialchars($b['title']) ?></div>
                    <?php if ($b['description']): ?>
                    <div class="banner-desc-text"><?= htmlspecialchars(mb_strimwidth($b['description'], 0, 80, '...')) ?></div>
                    <?php endif; ?>
                    <div class="banner-dates">
                        <i class="bi bi-calendar3"></i>
                        <?= $b['start_date'] ? date('d/m/Y', strtotime($b['start_date'])) : 'Không giới hạn' ?>
                        →
                        <?= $b['end_date'] ? date('d/m/Y', strtotime($b['end_date'])) : 'Không giới hạn' ?>
                    </div>
                    <div class="banner-actions">
                        <a href="manage_banners.php?action=edit&id=<?= $b['id'] ?>" class="btn-sm btn-edit">
                            <i class="bi bi-pencil"></i> Sửa
                        </a>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="banner_id" value="<?= $b['id'] ?>">
                            <input type="hidden" name="current_active" value="<?= $b['is_active'] ?>">
                            <button type="submit" name="toggle_banner"
                                    class="btn-sm <?= $b['is_active'] ? 'btn-toggle-off' : 'btn-toggle-on' ?>">
                                <i class="bi bi-<?= $b['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                <?= $b['is_active'] ? 'Tắt' : 'Bật' ?>
                            </button>
                        </form>
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Xóa banner này?')">
                            <input type="hidden" name="banner_id" value="<?= $b['id'] ?>">
                            <button type="submit" name="delete_banner" class="btn-sm btn-delete">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <?php else: ?>
        <div class="empty-state">
            <div style="font-size:3rem;margin-bottom:12px">🖼️</div>
            <h3 style="font-weight:800;color:var(--dark);margin-bottom:6px">Chưa có banner nào</h3>
            <p style="color:var(--gray);font-size:0.875rem;margin-bottom:16px">Thêm banner đầu tiên để hiển thị trên trang chủ!</p>
            <a href="manage_banners.php?action=add" class="btn-primary">
                <i class="bi bi-plus-lg"></i> Thêm banner ngay
            </a>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- ══ FORM THÊM / SỬA ══ -->
        <form method="POST" enctype="multipart/form-data">
            <?php if ($action === 'edit'): ?>
            <input type="hidden" name="banner_id" value="<?= $edit_banner['id'] ?>">
            <input type="hidden" name="image_current" value="<?= htmlspecialchars($edit_banner['image'] ?? '') ?>">
            <?php endif; ?>

            <div class="form-card">
                <div class="form-card-header">
                    <i class="bi bi-image-fill"></i>
                    <?= $action === 'edit' ? 'Sửa banner' : 'Thêm banner mới' ?>
                </div>
                <div class="form-card-body">

                    <div class="form-group">
                        <label class="form-label">Tiêu đề banner <span class="req">*</span></label>
                        <input type="text" name="title" class="form-control"
                               value="<?= htmlspecialchars($edit_banner['title'] ?? '') ?>"
                               placeholder="VD: iPhone 15 Pro Max - Titan. Đỉnh cao." required>
                        <div style="font-size:0.72rem;color:var(--gray);margin-top:4px">
                            Hiển thị làm tiêu đề lớn trên banner trang chủ
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Mô tả / Slogan</label>
                        <textarea name="description" class="form-control"
                                  placeholder="VD: Chip A17 Pro · Camera 48MP · Màn hình 6.7""><?= htmlspecialchars($edit_banner['description'] ?? '') ?></textarea>
                        <div style="font-size:0.72rem;color:var(--gray);margin-top:4px">
                            Hiển thị làm dòng phụ bên dưới tiêu đề
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Ngày bắt đầu</label>
                            <input type="date" name="start_date" class="form-control"
                                   value="<?= $edit_banner['start_date'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Ngày kết thúc</label>
                            <input type="date" name="end_date" class="form-control"
                                   value="<?= $edit_banner['end_date'] ?? '' ?>">
                            <div style="font-size:0.72rem;color:var(--gray);margin-top:4px">
                                Để trống = không giới hạn thời gian
                            </div>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Link khi bấm "Xem ngay"</label>
                            <input type="text" name="link_url" class="form-control"
                                value="<?= htmlspecialchars($edit_banner['link_url'] ?? '') ?>"
                                placeholder="VD: pages/product_detail.php?id=1">
                            <div style="font-size:0.72rem;color:var(--gray);margin-top:4px">
                                Để trống = trỏ về trang sản phẩm mặc định
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Loại banner</label>
                            <select name="banner_type" class="form-control">
                                <option value="main"  <?= ($edit_banner['banner_type'] ?? 'main') === 'main' ? 'selected' : '' ?>>
                                    Banner chính (lớn)
                                </option>
                                <option value="side" <?= ($edit_banner['banner_type'] ?? '') === 'side' ? 'selected' : '' ?>>
                                    Banner phụ (nhỏ bên phải)
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ảnh banner</label>
                        <input type="file" name="image" class="form-control"
                               accept="image/*" onchange="previewImg(this)">
                        <div class="img-preview" id="imgPreview">
                            <?php if (!empty($edit_banner['image']) && file_exists('../assets/images/banners/'.$edit_banner['image'])): ?>
                                <img src="../assets/images/banners/<?= htmlspecialchars($edit_banner['image']) ?>"
                                     alt="" id="previewEl">
                            <?php else: ?>
                                <span id="previewEl">🖼️</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:0.72rem;color:var(--gray);margin-top:4px">
                            Ảnh sẽ lưu vào <code>assets/images/banners/</code> · Khuyến nghị: 800×400px
                        </div>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="is_active"
                               <?= !isset($edit_banner) || $edit_banner['is_active'] ? 'checked' : '' ?>>
                        <label for="is_active">Bật banner — hiển thị trên trang chủ ngay</label>
                    </div>

                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:16px">
                <button type="submit" name="save_banner" class="btn-primary" style="padding:12px 28px">
                    <i class="bi bi-check-lg"></i>
                    <?= $action === 'edit' ? 'Cập nhật banner' : 'Thêm banner' ?>
                </button>
                <a href="manage_banners.php" class="btn-secondary" style="padding:12px 20px">Hủy</a>
            </div>
        </form>
        <?php endif; ?>

    </div>
</div>

<script>
function previewImg(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const preview = document.getElementById('previewEl');
            if (preview.tagName === 'IMG') {
                preview.src = e.target.result;
            } else {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.id  = 'previewEl';
                preview.replaceWith(img);
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>