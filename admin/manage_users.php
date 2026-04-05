<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$success = '';
$error   = '';

// ── XÓA USER ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $del_id = (int)$_POST['user_id'];
    if ($del_id === $_SESSION['user_id']) {
        $error = 'Không thể xóa tài khoản đang đăng nhập!';
    } else {
        $conn->query("DELETE FROM users WHERE id = $del_id");
        $success = 'Đã xóa tài khoản!';
    }
}

// ── ĐỔI ROLE ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_role'])) {
    $uid  = (int)$_POST['user_id'];
    $role = $_POST['current_role'] === 'admin' ? 'customer' : 'admin';
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->bind_param("si", $role, $uid);
    $stmt->execute();
    $success = 'Đã cập nhật quyền!';
}

// ── CẬP NHẬT USER ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $edit_id    = (int)$_POST['edit_id'];
    $edit_name  = trim($_POST['edit_name']);
    $edit_email = trim($_POST['edit_email']);
    $edit_phone = trim($_POST['edit_phone']);
    $edit_role  = in_array($_POST['edit_role'], ['customer','admin']) ? $_POST['edit_role'] : 'customer';
    $edit_pass  = $_POST['edit_password'];

    if (empty($edit_name) || empty($edit_email)) {
        $error = 'Vui lòng điền đầy đủ họ tên và email!';
    } else {
        // Kiểm tra email trùng với user KHÁC
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt_check->bind_param("si", $edit_email, $edit_id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $error = 'Email này đã được sử dụng bởi tài khoản khác!';
        } else {
            if (!empty($edit_pass)) {
                // Cập nhật kèm mật khẩu mới
                if (strlen($edit_pass) < 6) {
                    $error = 'Mật khẩu phải có ít nhất 6 ký tự!';
                } else {
                    $hashed = password_hash($edit_pass, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, role=?, password=? WHERE id=?");
                    $stmt->bind_param("sssssi", $edit_name, $edit_email, $edit_phone, $edit_role, $hashed, $edit_id);
                    $stmt->execute();
                    $success = 'Đã cập nhật tài khoản thành công!';
                }
            } else {
                // Cập nhật KHÔNG đổi mật khẩu
                $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, role=? WHERE id=?");
                $stmt->bind_param("ssssi", $edit_name, $edit_email, $edit_phone, $edit_role, $edit_id);
                $stmt->execute();
                $success = 'Đã cập nhật tài khoản thành công!';
            }
        }
    }
}

// ── THÊM USER ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $new_name  = trim($conn->real_escape_string($_POST['new_name']));
    $new_email = trim($conn->real_escape_string($_POST['new_email']));
    $new_phone = trim($conn->real_escape_string($_POST['new_phone']));
    $new_role  = in_array($_POST['new_role'], ['customer','admin']) ? $_POST['new_role'] : 'customer';
    $new_pass  = $_POST['new_password'];

    // Kiểm tra email đã tồn tại chưa
    $check = $conn->query("SELECT id FROM users WHERE email = '$new_email'");
    if ($check->num_rows > 0) {
        $error = 'Email này đã được sử dụng!';
    } elseif (strlen($new_pass) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự!';
    } elseif (empty($new_name) || empty($new_email)) {
        $error = 'Vui lòng điền đầy đủ họ tên và email!';
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, role, password, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssss", $new_name, $new_email, $new_phone, $new_role, $hashed);
        $stmt->execute();
        $success = "Đã thêm tài khoản \"$new_name\" thành công!";
    }
}

// ── LỌC & TÌM KIẾM ──────────────────────────────────
$search   = trim($_GET['q'] ?? '');
$f_role   = $_GET['role'] ?? 'all';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;

$where = "WHERE 1=1";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (u.full_name LIKE '%$s%' OR u.email LIKE '%$s%' OR u.phone LIKE '%$s%')";
}
if ($f_role !== 'all') {
    $r = $conn->real_escape_string($f_role);
    $where .= " AND u.role = '$r'";
}

$total_rows  = $conn->query("SELECT COUNT(*) AS c FROM users u $where")->fetch_assoc()['c'];
$total_pages = max(1, ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$users = $conn->query("
    SELECT u.*,
           COUNT(DISTINCT o.id) AS order_count,
           COALESCE(SUM(CASE WHEN o.status='delivered' THEN o.total_price END), 0) AS total_spent
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    $where
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT $per_page OFFSET $offset
");

$total_customers = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='customer'")->fetch_assoc()['c'];
$total_admins    = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='admin'")->fetch_assoc()['c'];
$pending_orders  = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status='pending'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý khách hàng - Admin</title>
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
        .alert-error { background:#FEF2F2; border:1px solid #FECACA; color:#DC2626; border-radius:10px; padding:12px 16px; font-size:0.875rem; margin-bottom:16px; display:flex; align-items:center; gap:8px; }

        /* Stats */
        .stats-row { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:20px; }
        .stat-card { background:#fff; border:1px solid var(--border); border-radius:12px; padding:16px 20px; display:flex; align-items:center; gap:14px; }
        .stat-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
        .stat-label { font-size:0.75rem; font-weight:700; color:var(--gray); text-transform:uppercase; letter-spacing:0.5px; }
        .stat-value { font-size:1.4rem; font-weight:800; color:var(--dark); line-height:1; margin-top:2px; }

        /* Filter tabs */
        .filter-tabs { display:flex; gap:6px; margin-bottom:16px; }
        .filter-tab { display:flex; align-items:center; gap:5px; padding:7px 14px; border-radius:100px; font-size:0.8rem; font-weight:700; text-decoration:none; border:1.5px solid var(--border); color:var(--gray); background:#fff; transition:all 0.2s; }
        .filter-tab:hover { border-color:var(--primary); color:var(--primary); }
        .filter-tab.active { background:var(--primary); border-color:var(--primary); color:#fff; }

        /* Toolbar */
        .toolbar { display:flex; align-items:center; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
        .search-input { background:#fff; border:1.5px solid var(--border); border-radius:10px; padding:9px 14px; font-size:0.875rem; font-family:var(--font); outline:none; width:280px; transition:border-color 0.2s; }
        .search-input:focus { border-color:var(--primary); }
        .btn-primary { background:var(--primary); color:#fff; border:none; border-radius:10px; padding:9px 16px; font-size:0.875rem; font-weight:700; font-family:var(--font); cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:background 0.2s; }
        .btn-primary:hover { background:var(--primary-dark); color:#fff; }
        .btn-secondary { background:#fff; color:var(--gray); border:1.5px solid var(--border); border-radius:10px; padding:9px 14px; font-size:0.875rem; font-weight:600; font-family:var(--font); cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s; }
        .btn-secondary:hover { border-color:var(--primary); color:var(--primary); }

        /* Table */
        .table-card { background:#fff; border:1px solid var(--border); border-radius:14px; overflow:hidden; }
        .admin-table { width:100%; border-collapse:collapse; }
        .admin-table th { padding:10px 16px; text-align:left; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--gray); background:var(--light); border-bottom:1px solid var(--border); white-space:nowrap; }
        .admin-table td { padding:12px 16px; font-size:0.85rem; border-bottom:1px solid #F9FAFB; vertical-align:middle; }
        .admin-table tr:last-child td { border-bottom:none; }
        .admin-table tr:hover td { background:#FAFAFA; }

        .user-avatar-cell { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.9rem; font-weight:800; flex-shrink:0; }
        .role-badge { display:inline-flex; align-items:center; gap:4px; font-size:0.72rem; font-weight:700; padding:3px 10px; border-radius:100px; }
        .role-admin    { background:#EEF4FF; color:var(--primary); }
        .role-customer { background:#F0FDF4; color:#16A34A; }

        .btn-sm { border:none; border-radius:7px; padding:5px 10px; font-size:0.75rem; font-weight:700; font-family:var(--font); cursor:pointer; display:inline-flex; align-items:center; gap:4px; transition:all 0.2s; }
        .btn-role   { background:#F5F3FF; color:#7C3AED; }
        .btn-role:hover { background:#7C3AED; color:#fff; }
        .btn-delete { background:#FEF2F2; color:#EF4444; }
        .btn-delete:hover { background:#EF4444; color:#fff; }

        /* Pagination */
        .pagination-wrap { display:flex; justify-content:center; gap:6px; margin-top:20px; flex-wrap:wrap; }
        .page-btn { min-width:36px; height:36px; display:flex; align-items:center; justify-content:center; border:1.5px solid var(--border); border-radius:8px; font-size:0.82rem; font-weight:700; color:var(--dark); text-decoration:none; transition:all 0.2s; padding:0 10px; }
        .page-btn:hover { border-color:var(--primary); color:var(--primary); }
        .page-btn.active { background:var(--primary); border-color:var(--primary); color:#fff; }
        .page-btn.disabled { opacity:0.4; pointer-events:none; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<!-- ══ MAIN ══ -->
<div class="main-content">
    <div class="admin-topbar">
        <div class="admin-topbar-title">👥 Quản lý khách hàng</div>
        <span style="font-size:0.78rem;color:var(--gray)">Tổng: <?= $total_rows ?> tài khoản</span>
    </div>

    <div class="page-body">

        <?php if ($success): ?>
        <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert-error"><i class="bi bi-exclamation-circle-fill"></i> <?= $error ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon" style="background:#F0FDF4">👥</div>
                <div>
                    <div class="stat-label">Khách hàng</div>
                    <div class="stat-value"><?= $total_customers ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#EEF4FF">🛡️</div>
                <div>
                    <div class="stat-label">Quản trị viên</div>
                    <div class="stat-value"><?= $total_admins ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#FFFBEB">📦</div>
                <div>
                    <div class="stat-label">Tổng tài khoản</div>
                    <div class="stat-value"><?= $total_customers + $total_admins ?></div>
                </div>
            </div>
        </div>

        <!-- Filter tabs -->
        <div class="filter-tabs">
            <a href="manage_users.php" class="filter-tab <?= $f_role==='all'?'active':'' ?>">Tất cả</a>
            <a href="manage_users.php?role=customer" class="filter-tab <?= $f_role==='customer'?'active':'' ?>">
                Khách hàng
            </a>
            <a href="manage_users.php?role=admin" class="filter-tab <?= $f_role==='admin'?'active':'' ?>">
                Quản trị viên
            </a>
        </div>

        <!-- Search -->
        <form method="GET" action="manage_users.php">
            <?php if ($f_role !== 'all'): ?>
            <input type="hidden" name="role" value="<?= $f_role ?>">
            <?php endif; ?>
            <div class="toolbar">
                <input type="text" name="q" class="search-input"
                       placeholder="🔍 Tìm tên, email, số điện thoại..."
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-primary"><i class="bi bi-search"></i> Tìm</button>
                <?php if ($search): ?>
                <a href="manage_users.php<?= $f_role!=='all'?'?role='.$f_role:'' ?>" class="btn-secondary">
                    <i class="bi bi-x"></i> Xóa
                </a>
                <?php endif; ?>
                <button type="button" class="btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddUser" style="margin-left:auto">
                    <i class="bi bi-person-plus"></i> Thêm tài khoản
                </button>
            </div>
        </form>

        <!-- Table -->
        <div class="table-card">
            <div style="overflow-x:auto">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:50px">#</th>
                            <th>Tài khoản</th>
                            <th>Liên hệ</th>
                            <th>Đơn hàng</th>
                            <th>Chi tiêu</th>
                            <th>Quyền</th>
                            <th>Ngày tạo</th>
                            <th style="width:120px">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users->num_rows > 0): ?>
                            <?php
                            $colors = ['#EEF4FF','#F0FDF4','#FFFBEB','#F5F3FF','#FFF1F2'];
                            $tcolors = ['#0057FF','#16A34A','#D97706','#7C3AED','#E11D48'];
                            $i = 0;
                            while ($u = $users->fetch_assoc()):
                                $ci = $i % count($colors);
                            ?>
                            <tr>
                                <td style="color:var(--gray);font-size:0.78rem"><?= $offset + $i + 1 ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px">
                                        <div class="user-avatar-cell"
                                             style="background:<?= $colors[$ci] ?>;color:<?= $tcolors[$ci] ?>">
                                            <?= mb_strtoupper(mb_substr($u['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:700"><?= htmlspecialchars($u['full_name']) ?></div>
                                            <div style="font-size:0.72rem;color:var(--gray)"><?= htmlspecialchars($u['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-size:0.82rem;color:var(--gray)">
                                    <?= htmlspecialchars($u['phone'] ?: '—') ?>
                                </td>
                                <td>
                                    <span style="font-weight:700"><?= $u['order_count'] ?></span>
                                    <span style="font-size:0.72rem;color:var(--gray)"> đơn</span>
                                </td>
                                <td style="font-weight:700;color:#EF4444;font-size:0.82rem">
                                    <?= $u['total_spent'] > 0 ? number_format($u['total_spent'],0,',','.').'đ' : '—' ?>
                                </td>
                                <td>
                                    <span class="role-badge <?= $u['role']==='admin' ? 'role-admin' : 'role-customer' ?>">
                                        <?= $u['role'] === 'admin' ? '🛡️ Admin' : '👤 Khách hàng' ?>
                                    </span>
                                </td>
                                <td style="color:var(--gray);font-size:0.78rem">
                                    <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                                </td>
                                <td>
                                    <div style="display:flex;gap:5px">
                                        <?php if ($u['id'] !== $_SESSION['user_id']): ?>

                                        <!-- Nút Sửa -->
                                        <button type="button" class="btn-sm btn-role"
                                                title="Chỉnh sửa"
                                                onclick="openEditModal(
                                                    <?= $u['id'] ?>,
                                                    <?= htmlspecialchars(json_encode($u['full_name'])) ?>,
                                                    <?= htmlspecialchars(json_encode($u['email'])) ?>,
                                                    <?= htmlspecialchars(json_encode($u['phone'] ?? '')) ?>,
                                                    '<?= $u['role'] ?>'
                                                )">
                                            <i class="bi bi-pencil-square"></i> Sửa
                                        </button>

                                        <!-- Nút Xóa -->
                                        <form method="POST" style="display:inline"
                                            onsubmit="return confirm('Xóa tài khoản này? Dữ liệu đơn hàng vẫn còn.')">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" name="delete_user" class="btn-sm btn-delete">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>

                                        <!-- Xóa -->
                                        <form method="POST" style="display:inline"
                                              onsubmit="return confirm('Xóa tài khoản này? Dữ liệu đơn hàng vẫn còn.')">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" name="delete_user" class="btn-sm btn-delete">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span style="font-size:0.72rem;color:var(--gray)">Tài khoản bạn</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php $i++; endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;padding:40px;color:var(--gray)">
                                    <div style="font-size:2.5rem;margin-bottom:8px">👤</div>
                                    Không tìm thấy tài khoản nào
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1):
            $base = '?role='.$f_role.($search?'&q='.urlencode($search):'');
        ?>
        <div class="pagination-wrap">
            <a href="<?= $base ?>&page=<?= $page-1 ?>" class="page-btn <?= $page<=1?'disabled':'' ?>">
                <i class="bi bi-chevron-left"></i>
            </a>
            <?php for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
            <a href="<?= $base ?>&page=<?= $i ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="<?= $base ?>&page=<?= $page+1 ?>" class="page-btn <?= $page>=$total_pages?'disabled':'' ?>">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
        <?php endif; ?>

    </div>
</div>
<!-- ══ MODAL THÊM USER ══ -->
<div class="modal fade" id="modalAddUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:1px solid var(--border);font-family:var(--font)">
            <div class="modal-header" style="border-bottom:1px solid var(--border);padding:18px 24px">
                <h5 class="modal-title" style="font-weight:800;font-size:1rem">➕ Thêm tài khoản mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body" style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">

                    <div>
                        <label style="font-size:0.78rem;font-weight:700;color:var(--gray);display:block;margin-bottom:5px">
                            HỌ TÊN <span style="color:#EF4444">*</span>
                        </label>
                        <input type="text" name="new_name" class="search-input" style="width:100%"
                               placeholder="Nguyễn Văn A" required>
                    </div>

                    <div>
                        <label style="font-size:0.78rem;font-weight:700;color:var(--gray);display:block;margin-bottom:5px">
                            EMAIL <span style="color:#EF4444">*</span>
                        </label>
                        <input type="email" name="new_email" class="search-input" style="width:100%"
                               placeholder="example@email.com" required>
                    </div>

                    <div>
                        <label style="font-size:0.78rem;font-weight:700;color:var(--gray);display:block;margin-bottom:5px">
                            SỐ ĐIỆN THOẠI
                        </label>
                        <input type="text" name="new_phone" class="search-input" style="width:100%"
                               placeholder="0901 234 567">
                    </div>

                    <div>
                        <label style="font-size:0.78rem;font-weight:700;color:var(--gray);display:block;margin-bottom:5px">
                            MẬT KHẨU <span style="color:#EF4444">*</span>
                        </label>
                        <input type="password" name="new_password" class="search-input" style="width:100%"
                               placeholder="Tối thiểu 6 ký tự" required minlength="6">
                    </div>

                    <div>
                        <label style="font-size:0.78rem;font-weight:700;color:var(--gray);display:block;margin-bottom:5px">
                            PHÂN QUYỀN
                        </label>
                        <select name="new_role" class="search-input" style="width:100%">
                            <option value="customer">👤 Khách hàng</option>
                            <option value="admin">🛡️ Quản trị viên</option>
                        </select>
                    </div>

                </div>
                <div class="modal-footer" style="border-top:1px solid var(--border);padding:14px 24px;gap:8px">
                    <button type="button" class="btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" name="add_user" class="btn-primary">
                        <i class="bi bi-person-check"></i> Tạo tài khoản
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- ══ MODAL CHỈNH SỬA USER ══ -->
<div class="modal fade" id="modalEditUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:1px solid var(--border);font-family:var(--font)">
            <div class="modal-header" style="border-bottom:1px solid var(--border);padding:18px 24px">
                <h5 class="modal-title" style="font-weight:800;font-size:1rem">✏️ Chỉnh sửa tài khoản</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="modal-body" style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">

                    <div>
                        <label style="font-size:0.78rem;font-weight:700;color:var(--gray);display:block;margin-bottom:5px">
                            HỌ TÊN <span style="color:#EF4444">*</span>
                        </label>
                        <input type="text" name="edit_name" id="edit_name"
                               class="search-input" style="width:100%" required>
                    </div>

                    <div>
                        <label style="font-size:0.78rem;font-weight:700;color:var(--gray);display:block;margin-bottom:5px">
                            EMAIL <span style="color:#EF4444">*</span>
                        </label>
                        <input type="email" name="edit_email" id="edit_email"
                               class="search-input" style="width:100%" required>
                    </div>

                    <div>
                        <label style="font-size:0.78rem;font-weight:700;color:var(--gray);display:block;margin-bottom:5px">
                            SỐ ĐIỆN THOẠI
                        </label>
                        <input type="text" name="edit_phone" id="edit_phone"
                               class="search-input" style="width:100%">
                    </div>

                    <div>
                        <label style="font-size:0.78rem;font-weight:700;color:var(--gray);display:block;margin-bottom:5px">
                            MẬT KHẨU MỚI
                            <span style="font-weight:400;color:var(--gray)">(để trống nếu không đổi)</span>
                        </label>
                        <input type="password" name="edit_password"
                               class="search-input" style="width:100%"
                               placeholder="Tối thiểu 6 ký tự" minlength="6">
                    </div>

                    <div>
                        <label style="font-size:0.78rem;font-weight:700;color:var(--gray);display:block;margin-bottom:5px">
                            PHÂN QUYỀN
                        </label>
                        <select name="edit_role" id="edit_role" class="search-input" style="width:100%">
                            <option value="customer">👤 Khách hàng</option>
                            <option value="admin">🛡️ Quản trị viên</option>
                        </select>
                    </div>

                </div>
                <div class="modal-footer" style="border-top:1px solid var(--border);padding:14px 24px;gap:8px">
                    <button type="button" class="btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" name="edit_user" class="btn-primary">
                        <i class="bi bi-check-lg"></i> Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(id, name, email, phone, role) {
    document.getElementById('edit_id').value    = id;
    document.getElementById('edit_name').value  = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_phone').value = phone;
    document.getElementById('edit_role').value  = role;

    document.querySelector('[name="edit_password"]').value = '';

    new bootstrap.Modal(document.getElementById('modalEditUser')).show();
}
</script>

<!-- Bootstrap JS (cần cho modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>