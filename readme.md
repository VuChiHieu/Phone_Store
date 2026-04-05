# 📱 Phone Store - Website Thương Mại Điện Tử Bán Điện Thoại

Một website thương mại điện tử bán điện thoại được xây dựng bằng PHP thuần, chạy trên môi trường XAMPP (localhost).

---

## 🚀 Tính Năng

- 🔐 Đăng ký / Đăng nhập / Đăng xuất tài khoản
- 🛍️ Xem danh sách sản phẩm & chi tiết sản phẩm
- 🔍 Tìm kiếm sản phẩm (gợi ý tìm kiếm real-time)
- 🛒 Giỏ hàng (thêm, xoá, cập nhật số lượng)
- 💳 Thanh toán & đặt hàng
- 📦 Xem lịch sử đơn hàng & chi tiết đơn hàng
- 👤 Quản lý thông tin cá nhân
- 📋 Trang chính sách, liên hệ
- 🛠️ Trang quản trị (Admin)

---

## 🗂️ Cấu Trúc Thư Mục

```
phone_store/
├── admin/                  # Trang quản trị
├── api/
│   ├── add_to_cart.php     # API thêm vào giỏ hàng
│   └── search_suggest.php  # API gợi ý tìm kiếm
├── assets/                 # CSS, JS, hình ảnh
├── auth/
│   ├── login.php           # Đăng nhập
│   ├── logout.php          # Đăng xuất
│   └── register.php        # Đăng ký
├── database/
│   ├── phone_store.sql     # Cấu trúc database
│   └── sample_data.sql     # Dữ liệu mẫu
├── includes/
│   ├── navbar.php          # Thanh điều hướng
│   └── footer.php          # Chân trang
├── pages/
│   ├── products.php        # Danh sách sản phẩm
│   ├── product_detail.php  # Chi tiết sản phẩm
│   ├── cart.php            # Giỏ hàng
│   ├── checkout.php        # Thanh toán
│   ├── orders.php          # Lịch sử đơn hàng
│   ├── order_detail.php    # Chi tiết đơn hàng
│   ├── profile.php         # Thông tin cá nhân
│   ├── contact.php         # Liên hệ
│   └── policy.php          # Chính sách
├── config.php              # Cấu hình kết nối database
└── index.php               # Trang chủ
```

---

## ⚙️ Yêu Cầu Hệ Thống

| Công nghệ | Phiên bản khuyến nghị |
|-----------|----------------------|
| XAMPP     | 8.x trở lên          |
| PHP       | 8.0+                 |
| MySQL     | 5.7+ / MariaDB 10.4+ |
| Trình duyệt | Chrome, Firefox, Edge |

---

## 🛠️ Hướng Dẫn Cài Đặt

### Bước 1 — Cài đặt XAMPP

Tải và cài đặt XAMPP tại: https://www.apachefriends.org/

### Bước 2 — Clone / Copy dự án

Sao chép thư mục dự án vào thư mục `htdocs` của XAMPP:

```
C:\xampp\htdocs\phone_store\
```

### Bước 3 — Import Database

1. Mở trình duyệt, truy cập: `http://localhost/phpmyadmin`
2. Tạo database mới tên: `phone_store`
3. Chọn database vừa tạo → Click **Import**
4. Import file `database/phone_store.sql` (cấu trúc bảng)
5. Import tiếp file `database/sample_data.sql` (dữ liệu mẫu)

### Bước 4 — Tạo tài khoản Admin

Sau khi import database, chạy câu SQL sau trong **phpMyAdmin** (chọn đúng database `phone_store` → tab **SQL**) để tạo tài khoản admin:

**Bước 4.1 — Tạo mật khẩu băm (bcrypt)**

Tạo file `generate_hash.php` trong thư mục `htdocs`, chạy một lần để lấy hash:

```php
<?php
echo password_hash('matkhau_cua_ban', PASSWORD_BCRYPT);
?>
```

Truy cập `http://localhost/generate_hash.php`, sao chép chuỗi hash xuất ra.

**Bước 4.2 — Chạy câu INSERT**

Vào **phpMyAdmin → phone_store → SQL**, dán và chỉnh sửa câu sau:

```sql
INSERT INTO users (full_name, email, password, phone, address, role)
VALUES (
    'Admin',
    'admin@phonestore.com',
    '$2y$...',       -- Thay bằng chuỗi hash từ bước 4.1
    '0909000000',
    'TP. Hồ Chí Minh',
    'admin'
);
```

> ⚠️ **Lưu ý:** Không dùng mật khẩu dạng plain text. Bắt buộc phải dùng hash bcrypt từ bước 4.1 để đăng nhập hoạt động đúng.

Sau khi tạo xong, đăng nhập tại `http://localhost/phone_store/auth/login.php` bằng email và mật khẩu vừa đặt.

---



Mở file `config.php`, đảm bảo thông tin kết nối đúng:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');           // XAMPP mặc định không có password
define('DB_NAME', 'phone_store');
```



### Bước 6 — Chạy dự án

1. Mở **XAMPP Control Panel**, khởi động **Apache** và **MySQL**
2. Truy cập trình duyệt: `http://localhost/phone_store`

---

## 💻 Công Nghệ Sử Dụng

- **Backend:** PHP (thuần, không framework)
- **Database:** MySQL
- **Frontend:** HTML, CSS, JavaScript
- **Môi trường:** XAMPP (Apache + MySQL)
- **Charset:** UTF-8 MB4 (hỗ trợ tiếng Việt)

---

## 📝 Ghi Chú

- Dự án chạy trên môi trường **localhost**, chưa tối ưu cho production.
- Mật khẩu database mặc định của XAMPP là **rỗng** (`''`).
- Nếu bạn đặt mật khẩu MySQL riêng, hãy cập nhật lại `DB_PASS` trong `config.php`.