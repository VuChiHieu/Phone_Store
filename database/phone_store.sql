-- 1. USERS (khách hàng + admin)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(15),
    address TEXT,
    role ENUM('customer', 'admin') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. CATEGORIES (danh mục: điện thoại, tai nghe, sạc, ốp lưng)
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    image VARCHAR(255)
);

-- 3. BRANDS (hãng: Apple, Samsung, Xiaomi...)
CREATE TABLE brands (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    logo VARCHAR(255)
);

-- 4. PRODUCTS (sản phẩm)
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    category_id INT,
    brand_id INT,
    price DECIMAL(15,0) NOT NULL,         -- giá mới
    old_price DECIMAL(15,0),              -- giá cũ (khuyến mãi)
    discount_percent INT DEFAULT 0,
    stock INT DEFAULT 0,
    description TEXT,
    ram VARCHAR(20),                       -- 8GB, 12GB...
    storage VARCHAR(20),                   -- 128GB, 256GB...
    thumbnail VARCHAR(255),               -- ảnh đại diện
    is_featured TINYINT(1) DEFAULT 0,     -- sản phẩm nổi bật
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (brand_id) REFERENCES brands(id)
);

-- 5. PRODUCT_IMAGES (nhiều ảnh cho 1 sản phẩm)
CREATE TABLE product_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 6. ORDERS (đơn hàng)
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    address TEXT NOT NULL,
    total_price DECIMAL(15,0) NOT NULL,
    status ENUM('pending','confirmed','shipping','delivered','cancelled') DEFAULT 'pending',
    payment_method ENUM('cod','bank_transfer') DEFAULT 'cod',
    payment_status ENUM('unpaid','paid','refunded') DEFAULT 'unpaid',
    payment_code VARCHAR(50),
    paid_at TIMESTAMP NULL,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 7. ORDER_ITEMS (chi tiết từng sản phẩm trong đơn)
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(15,0) NOT NULL,         -- giá tại thời điểm mua
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- 8. CART (giỏ hàng tạm)
CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 9. REVIEWS (đánh giá + bình luận)
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 10. CONTACTS (form liên hệ / feedback)
CREATE TABLE contacts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 11. PROMOTIONS (khuyến mãi / banner)
CREATE TABLE promotions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    start_date DATE,
    end_date DATE,
    is_active TINYINT(1) DEFAULT 1,
    link_url VARCHAR(255) DEFAULT NULL,
    banner_type ENUM('main','side') DEFAULT 'main'
);
