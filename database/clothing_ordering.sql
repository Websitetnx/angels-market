-- =====================================================
-- Clothing Ordering Management System Database
-- =====================================================

CREATE DATABASE IF NOT EXISTS clothing_ordering;
USE clothing_ordering;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    province VARCHAR(100) DEFAULT NULL,
    zip_code VARCHAR(10) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'client') NOT NULL DEFAULT 'client',
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Products Table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    description TEXT,
    brand VARCHAR(100) DEFAULT NULL,
    gender ENUM('Men', 'Women', 'Unisex') DEFAULT 'Unisex',
    sizes VARCHAR(255) DEFAULT NULL,
    colors VARCHAR(255) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL,
    discount INT DEFAULT 0,
    stock INT DEFAULT 0,
    rating DECIMAL(2,1) DEFAULT 4.5,
    sold INT DEFAULT 0,
    featured TINYINT(1) DEFAULT 0,
    new_arrival TINYINT(1) DEFAULT 0,
    location VARCHAR(255) DEFAULT NULL,
    status ENUM('Available', 'Out of Stock') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Product Images Table
CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cart Table
CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    size VARCHAR(10) DEFAULT NULL,
    color VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Orders Table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    fullname VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    zip_code VARCHAR(10) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('COD', 'GCash') NOT NULL DEFAULT 'COD',
    status ENUM('Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Pending',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Order Items Table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    size VARCHAR(10) DEFAULT NULL,
    color VARCHAR(50) DEFAULT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Insert Default Admin Account
-- Email: angelombao@gmail.com | Password: admin12345
-- =====================================================
INSERT INTO users (fullname, email, password, role) VALUES
('Angel Ombao', 'angelombao@gmail.com', '$2y$10$fdyQctEeEYEbgKsDaQ2cGerPCZY.QGIU/KnnTUotFAfAkveCH.kTu', 'admin');

-- =====================================================
-- Insert Default Categories
-- =====================================================
('Oversized Shirts', 'bi-shirt'),
('Polo Shirts', 'bi-shirt'),
('Crop Tops', 'bi-tags'),
('T-Shirts', 'bi-shirt'),
('Hoodies', 'bi-snow2'),
('Jackets', 'bi-shield'),
('Shorts', 'bi-border-bottom'),
('Pants', 'bi-border-all'),
('Accessories', 'bi-watch'),
('Skincare', 'bi-droplet-half'),
('Makeup', 'bi-palette'),
('Hair Care', 'bi-scissors'),
('Fragrances', 'bi-flower1'),
('Nail Care', 'bi-hand-index-thumb'),
('Lip Care', 'bi-heart'),
('Body Care', 'bi-stars'),
('Beauty Tools', 'bi-tools');

-- =====================================================
-- Insert Sample Products
-- =====================================================
INSERT INTO products (category_id, product_name, description, brand, gender, sizes, colors, price, discount, stock, rating, sold, featured, new_arrival, location, status) VALUES
(1, 'Korean Oversized T-shirt Retro Loose Casual', 'Premium quality oversized t-shirt with retro print design. Comfortable and stylish for everyday wear.', 'JIEBANGREN', 'Unisex', 'S,M,L,XL,XXL', 'White,Black,Gray,Beige', 399.00, 59, 150, 4.7, 2450, 1, 1, 'Guiguinto, Bulacan', 'Available'),
(1, 'Korean Oversized Motorcycle Zipper T-shirt', 'Trendy motorcycle-inspired zipper t-shirt with oversized fit. Perfect for streetwear enthusiasts.', 'StreetWear Co.', 'Unisex', 'S,M,L,XL,XXL', 'Black,White,Gray', 399.00, 45, 200, 4.8, 1890, 1, 0, 'Guiguinto, Bulacan', 'Available'),
(2, 'Embossed Pattern Button Down Polo Neck', 'Elegant embossed pattern polo shirt with button-down collar. Great for casual and semi-formal occasions.', 'PoloStyle', 'Men', 'S,M,L,XL', 'White,Navy,Beige,Black', 410.00, 45, 120, 4.8, 1560, 1, 0, 'Guiguinto, Bulacan', 'Available'),
(3, 'Girls Summer Semi Crop Top Striped Round Neck', 'Cute striped crop top perfect for summer. Lightweight and breathable fabric.', 'SummerVibes', 'Women', 'XS,S,M,L', 'White,Pink,Blue,Yellow', 220.00, 53, 80, 4.9, 3200, 1, 0, 'Binondo, Metro Manila', 'Available'),
(4, 'American Retro Hot Girl Off-Shoulder T-shirt', 'Stylish off-shoulder t-shirt with American retro design. Perfect for casual outings.', 'RetroGirl', 'Women', 'XS,S,M,L,XL', 'Red,Black,White', 399.00, 68, 95, 4.8, 2100, 0, 1, 'Guiguinto, Bulacan', 'Available'),
(5, 'Korean Style Pullover Hoodie Unisex', 'Warm and cozy pullover hoodie with Korean-inspired design. Perfect for cool weather.', 'KStyle', 'Unisex', 'S,M,L,XL,XXL', 'Gray,Black,White,Navy,Brown', 650.00, 30, 180, 4.6, 980, 1, 1, 'Quezon City', 'Available'),
(6, 'Oversized Denim Jacket Vintage Wash', 'Classic oversized denim jacket with vintage wash finish. A timeless wardrobe staple.', 'DenimCo', 'Unisex', 'S,M,L,XL', 'Light Blue,Dark Blue,Black', 890.00, 25, 60, 4.5, 750, 0, 0, 'Makati City', 'Available'),
(7, 'Korean Stripe Tshirt for Men Aesthetic', 'Aesthetic striped t-shirt with Korean-inspired design. Comfortable cotton blend.', 'AesthCo', 'Men', 'S,M,L,XL,XXL', 'Red,Navy,Green,Black', 399.00, 63, 110, 4.6, 1450, 1, 0, 'Binondo, Metro Manila', 'Available'),
(8, 'Wide Leg Pants High Waist Straight', 'Trendy wide-leg pants with high waist design. Comfortable and flattering fit.', 'ModernFit', 'Women', 'XS,S,M,L,XL', 'Black,White,Khaki,Gray', 550.00, 40, 90, 4.7, 1200, 0, 1, 'Makati City', 'Available'),
(9, 'Y2K One Shoulder Strap Top Lakeside', 'Trendy Y2K-inspired one shoulder strap top. Perfect for a bold fashion statement.', 'Y2KStyle', 'Women', 'XS,S,M,L', 'White,Black,Pink,Blue', 399.00, 50, 70, 4.5, 890, 0, 0, 'Guiguinto, Bulacan', 'Available'),
(2, 'Unique Design Buttoned V-Neck Polo Top', 'Stylish V-neck polo with unique button design. Premium fabric for ultimate comfort.', 'PoloStyle', 'Men', 'S,M,L,XL', 'White,Black,Navy,Burgundy', 380.00, 58, 130, 4.5, 1680, 1, 0, 'Guiguinto, Bulacan', 'Available'),
(1, 'Womens Trendy Off-Shoulder Oversize T-shirt', 'Fashionable off-shoulder oversized t-shirt. Soft fabric and relaxed fit.', 'TrendyWear', 'Women', 'XS,S,M,L,XL', 'Beige,White,Black,Gray', 350.00, 64, 100, 4.8, 2300, 0, 1, 'Guiguinto, Bulacan', 'Available');

-- =====================================================
-- Insert Sample Beauty Products
-- =====================================================
INSERT INTO products (category_id, product_name, description, brand, gender, sizes, colors, price, discount, stock, rating, sold, featured, new_arrival, location, status) VALUES
(10, 'Hyaluronic Acid Hydrating Serum 30ml', 'Deeply hydrating serum with pure hyaluronic acid that plumps and moisturizes skin. Lightweight, fast-absorbing formula suitable for all skin types.', 'GlowLab', 'Unisex', '30ml,50ml', '', 349.00, 30, 200, 4.9, 5200, 1, 1, 'Makati City', 'Available'),
(10, 'Vitamin C Brightening Moisturizer SPF30', 'Daily moisturizer infused with Vitamin C and SPF30 sun protection. Brightens dull skin, evens out skin tone, and protects from UV damage.', 'SkinDew', 'Unisex', '50ml,100ml', '', 499.00, 25, 150, 4.8, 3800, 1, 0, 'Quezon City', 'Available'),
(11, 'Velvet Matte Liquid Lipstick Set (6 Shades)', 'Long-lasting velvet matte liquid lipstick set with 6 gorgeous shades. Transfer-proof, lightweight formula that keeps your lips hydrated all day.', 'LuxeLips', 'Women', '3ml each', 'Nude Pink,Rose,Coral,Berry,Red,Mauve', 599.00, 40, 120, 4.8, 4500, 1, 1, 'Makati City', 'Available'),
(11, '16-Color Shimmer & Matte Eyeshadow Palette', 'Professional-grade eyeshadow palette with 16 highly pigmented shades. Mix of shimmer, matte, and glitter finishes.', 'GlamEyes', 'Women', 'Standard', 'Warm Neutrals,Cool Tones', 450.00, 35, 90, 4.7, 2900, 1, 0, 'Binondo, Metro Manila', 'Available'),
(12, 'Keratin Repair Shampoo & Conditioner Set', 'Salon-quality keratin repair shampoo and conditioner duo. Restores damaged hair, reduces frizz, and adds brilliant shine.', 'SilkStrand', 'Unisex', '250ml,500ml', '', 650.00, 20, 180, 4.6, 2100, 0, 1, 'Quezon City', 'Available'),
(13, 'Bloom Garden Eau de Parfum 50ml', 'Elegant floral eau de parfum with top notes of jasmine and peony, heart of rose and lily, and base of white musk.', 'Aura Scents', 'Women', '30ml,50ml,100ml', '', 899.00, 15, 75, 4.9, 1800, 1, 1, 'Makati City', 'Available'),
(14, 'Gel Nail Polish Collection (12 Colors)', 'Professional gel nail polish set with 12 trendy colors. UV/LED curable, chip-resistant formula that lasts up to 21 days.', 'NailGlow', 'Women', '8ml each', 'Red,Pink,Nude,Coral,Lavender,Peach,Berry,Mint,Rose Gold,Burgundy,White,Black', 799.00, 45, 100, 4.7, 3200, 0, 1, 'Guiguinto, Bulacan', 'Available'),
(15, 'Rose Petal Lip Sleeping Mask', 'Overnight lip treatment mask infused with real rose petals and vitamin E. Deeply nourishes and repairs dry, chapped lips while you sleep.', 'PetalSoft', 'Unisex', '20g', '', 280.00, 30, 250, 4.8, 6100, 1, 0, 'Binondo, Metro Manila', 'Available'),
(16, 'Shea Butter Whipped Body Cream 200ml', 'Luxuriously rich whipped body cream with pure shea butter and coconut oil. Intensely moisturizes and softens skin.', 'BodyBliss', 'Unisex', '200ml,400ml', '', 399.00, 20, 160, 4.6, 2400, 0, 0, 'Quezon City', 'Available'),
(17, 'Rose Gold Makeup Brush Set (15 Pieces)', 'Premium 15-piece makeup brush set with ultra-soft synthetic bristles and elegant rose gold handles. Comes with a chic carrying case.', 'BrushCraft', 'Women', '15-Piece Set', 'Rose Gold,Black,Pink', 750.00, 50, 85, 4.8, 3600, 1, 1, 'Makati City', 'Available'),
(10, 'Tea Tree Oil Acne Clearing Spot Treatment', 'Targeted acne spot treatment with tea tree oil and salicylic acid. Reduces blemishes overnight, unclogs pores, and prevents future breakouts.', 'ClearSkin', 'Unisex', '15ml,30ml', '', 250.00, 35, 300, 4.7, 7800, 0, 0, 'Guiguinto, Bulacan', 'Available'),
(11, 'Full Coverage Cushion Foundation SPF50', 'Korean-style cushion compact foundation with buildable full coverage and SPF50 protection. Available in 6 shades for every Filipina skin tone.', 'DewDrop', 'Women', '15g', 'Fair,Light,Natural,Sand,Honey,Caramel', 550.00, 30, 140, 4.9, 4100, 1, 1, 'Makati City', 'Available');

-- Insert product images (placeholder paths)
INSERT INTO product_images (product_id, image, is_primary) VALUES
(1, 'product_1_1.jpg', 1),
(2, 'product_2_1.jpg', 1),
(3, 'product_3_1.jpg', 1),
(4, 'product_4_1.jpg', 1),
(5, 'product_5_1.jpg', 1),
(6, 'product_6_1.jpg', 1),
(7, 'product_7_1.jpg', 1),
(8, 'product_8_1.jpg', 1),
(9, 'product_9_1.jpg', 1),
(10, 'product_10_1.jpg', 1),
(11, 'product_11_1.jpg', 1),
(12, 'beauty-hyaluronic-serum.webp', 1),
(13, 'beauty-vitamin-c-moisturizer.webp', 1),
(14, 'beauty-matte-lipstick-set.webp', 1),
(15, 'beauty-eyeshadow-palette.webp', 1),
(16, 'beauty-keratin-hair-set.webp', 1),
(17, 'beauty-bloom-perfume.webp', 1),
(18, 'beauty-gel-nail-polish-set.webp', 1),
(19, 'beauty-rose-lip-mask.webp', 1),
(20, 'beauty-shea-body-cream.webp', 1),
(21, 'beauty-makeup-brush-set.webp', 1),
(22, 'beauty-tea-tree-treatment.webp', 1),
(23, 'beauty-cushion-foundation.webp', 1);
