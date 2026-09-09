-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 08, 2026 at 01:37 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `projukti_mart`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `logged_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `address_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `line1` varchar(150) NOT NULL,
  `line2` varchar(150) DEFAULT NULL,
  `city` varchar(50) NOT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(50) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`address_id`, `user_id`, `line1`, `line2`, `city`, `postal_code`, `country`, `is_default`) VALUES
(1, 5, 'House 12, Road 4', 'Dhanmondi', 'Dhaka', '1205', 'Bangladesh', 1),
(2, 6, 'House 25, Road 7', 'Mirpur', 'Dhaka', '1216', 'Bangladesh', 1),
(3, 7, 'Flat 4B, Road 11', 'Uttara', 'Dhaka', '1230', 'Bangladesh', 1),
(4, 8, 'House 8, Road 2', 'Bashundhara R/A', 'Dhaka', '1229', 'Bangladesh', 1),
(5, 9, 'House 17, Road 5', 'Mohammadpur', 'Dhaka', '1207', 'Bangladesh', 1),
(6, 10, 'Flat 6A, Road 3', 'Banani', 'Dhaka', '1213', 'Bangladesh', 1),
(7, 11, 'House 31, Road 9', 'Wari', 'Dhaka', '1203', 'Bangladesh', 1),
(8, 12, 'Flat 3C, Road 12', 'Badda', 'Dhaka', '1212', 'Bangladesh', 1);

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`cart_id`, `user_id`, `created_at`) VALUES
(1, 5, '2026-09-08 05:34:06'),
(2, 6, '2026-09-08 05:34:06'),
(3, 7, '2026-09-08 05:34:06'),
(4, 8, '2026-09-08 05:34:06'),
(5, 9, '2026-09-08 05:34:06'),
(6, 10, '2026-09-08 05:34:06'),
(7, 11, '2026-09-08 05:34:06'),
(8, 12, '2026-09-08 05:34:06'),
(9, 13, '2026-09-08 09:57:58');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `cart_item_id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` (`cart_item_id`, `cart_id`, `product_id`, `quantity`, `added_at`) VALUES
(1, 1, 13, 1, '2026-09-08 05:34:06'),
(2, 1, 19, 2, '2026-09-08 05:34:06'),
(3, 2, 5, 1, '2026-09-08 05:34:06'),
(4, 2, 17, 1, '2026-09-08 05:34:06'),
(5, 3, 23, 1, '2026-09-08 05:34:06'),
(6, 3, 15, 2, '2026-09-08 05:34:06'),
(7, 4, 2, 1, '2026-09-08 05:34:06'),
(8, 5, 21, 2, '2026-09-08 05:34:06'),
(9, 6, 18, 1, '2026-09-08 05:34:06'),
(10, 7, 22, 1, '2026-09-08 05:34:06'),
(11, 8, 3, 1, '2026-09-08 05:34:06'),
(12, 9, 22, 1, '2026-09-08 09:57:58');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `parent_category_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `parent_category_id`, `description`, `is_active`) VALUES
(1, 'Mobile', NULL, NULL, 1),
(2, 'PC', NULL, NULL, 1),
(3, 'Laptop', NULL, NULL, 1),
(4, 'Gaming Laptops', 3, NULL, 1),
(5, 'Business Laptops', 3, NULL, 1),
(6, 'Desktop PCs', 2, NULL, 1),
(7, 'Gaming PCs', 2, NULL, 1),
(8, 'Keyboards', NULL, NULL, 1),
(9, 'Headphones', NULL, NULL, 1),
(10, 'Cables', NULL, NULL, 1),
(11, 'Stands', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address_id` int(11) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `total_amount` decimal(10,2) NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `address_id`, `status`, `total_amount`, `order_date`, `updated_at`) VALUES
(1, 5, 1, 'delivered', 12500.00, '2026-08-12 04:30:00', NULL),
(2, 6, 2, 'delivered', 34999.00, '2026-08-15 09:20:00', NULL),
(3, 7, 3, 'shipped', 104999.00, '2026-08-28 06:10:00', NULL),
(4, 8, 4, 'pending', 69999.00, '2026-09-01 11:00:00', NULL),
(5, 9, 5, 'pending', 18500.00, '2026-09-05 05:15:00', NULL),
(6, 10, 6, 'delivered', 2299.00, '2026-08-20 03:45:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `quantity`, `unit_price`, `subtotal`) VALUES
(1, 1, 13, 1, 12500.00, 12500.00),
(2, 2, 16, 1, 34999.00, 34999.00),
(3, 3, 3, 1, 104999.00, 104999.00),
(4, 4, 8, 1, 69999.00, 69999.00),
(5, 5, 14, 1, 18500.00, 18500.00),
(6, 6, 20, 1, 2299.00, 2299.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `history_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `brand` varchar(50) DEFAULT NULL,
  `model` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_qty` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `category_id`, `seller_id`, `name`, `brand`, `model`, `description`, `price`, `stock_qty`, `status`, `created_at`, `updated_at`) VALUES
(1, 4, 2, 'ASUS TUF Gaming A15', 'ASUS', 'FA507', 'Gaming laptop with Ryzen 7 processor and RTX graphics.', 119999.00, 8, 'active', '2026-09-08 05:34:06', NULL),
(2, 4, 2, 'Lenovo LOQ 15', 'Lenovo', '15IRX9', 'Performance gaming laptop for gaming, coding and creative work.', 134999.00, 6, 'active', '2026-09-08 05:34:06', NULL),
(3, 4, 3, 'Acer Nitro V 15', 'Acer', 'ANV15-51', 'Value-focused gaming laptop with 144Hz display.', 104999.00, 10, 'active', '2026-09-08 05:34:06', NULL),
(4, 5, 2, 'Dell Inspiron 14', 'Dell', '5430', 'Compact business and productivity laptop with Core i5.', 84999.00, 12, 'active', '2026-09-08 05:34:06', NULL),
(5, 5, 3, 'HP Pavilion 14', 'HP', '14-eh', 'Slim laptop for office work, study and everyday productivity.', 74999.00, 15, 'active', '2026-09-08 05:34:06', NULL),
(6, 5, 4, 'Lenovo ThinkPad E14', 'Lenovo', 'Gen 6', 'Business laptop with durable chassis and excellent keyboard.', 92999.00, 9, 'active', '2026-09-08 05:34:06', NULL),
(7, 6, 2, 'TechHub Creator PC', 'Custom', 'TH-CREATOR-01', 'Desktop workstation for programming, editing and productivity.', 109999.00, 5, 'active', '2026-09-08 05:34:06', NULL),
(8, 6, 3, 'ByteZone Office PC', 'Custom', 'BZ-OFFICE-01', 'Balanced desktop PC for office applications and development.', 69999.00, 9, 'active', '2026-09-08 05:34:06', NULL),
(9, 6, 4, 'Gadget World Home PC', 'Custom', 'GW-HOME-01', 'Affordable desktop for home, study and general use.', 54999.00, 11, 'active', '2026-09-08 05:34:06', NULL),
(10, 7, 2, 'TechHub RTX Gaming PC', 'Custom', 'TH-GAME-01', 'High-performance gaming desktop with RTX graphics.', 179999.00, 4, 'active', '2026-09-08 05:34:06', NULL),
(11, 7, 3, 'ByteZone Esports PC', 'Custom', 'BZ-ESPORT-01', '1080p high-refresh-rate gaming desktop.', 124999.00, 7, 'active', '2026-09-08 05:34:06', NULL),
(12, 7, 4, 'Gadget World Starter Gaming PC', 'Custom', 'GW-GAME-01', 'Entry-level gaming desktop for popular esports titles.', 89999.00, 6, 'active', '2026-09-08 05:34:06', NULL),
(13, 8, 2, 'Keychron K8 Pro', 'Keychron', 'K8P', 'Wireless mechanical keyboard with hot-swappable switches.', 12500.00, 18, 'active', '2026-09-08 05:34:06', NULL),
(14, 8, 3, 'Logitech G Pro X TKL', 'Logitech', 'G-PRO-X-TKL', 'Tenkeyless wireless gaming keyboard with programmable controls.', 18500.00, 14, 'active', '2026-09-08 05:34:06', NULL),
(15, 8, 4, 'Redragon K552 Kumara', 'Redragon', 'K552', 'Affordable mechanical keyboard with RGB backlighting.', 4500.00, 25, 'active', '2026-09-08 05:34:06', NULL),
(16, 9, 2, 'Sony WH-1000XM5', 'Sony', 'WH1000XM5', 'Premium wireless noise-cancelling headphones.', 34999.00, 10, 'active', '2026-09-08 05:34:06', NULL),
(17, 9, 3, 'JBL Tune 770NC', 'JBL', 'T770NC', 'Wireless noise-cancelling headphones with long battery life.', 12999.00, 16, 'active', '2026-09-08 05:34:06', NULL),
(18, 9, 4, 'Anker Soundcore Q20i', 'Anker', 'Q20i', 'Comfortable wireless headphones with hybrid ANC.', 6999.00, 20, 'active', '2026-09-08 05:34:06', NULL),
(19, 10, 2, 'UGREEN USB-C to USB-C 100W Cable', 'UGREEN', 'USBC100W', 'Durable USB-C cable suitable for fast charging and data transfer.', 1499.00, 40, 'active', '2026-09-08 05:34:06', NULL),
(20, 10, 3, 'Baseus USB-C to HDMI Cable', 'Baseus', 'CAHUB-C', '4K HDMI connection from compatible USB-C devices.', 2299.00, 30, 'active', '2026-09-08 05:34:06', NULL),
(21, 10, 4, 'Anker PowerLine III USB-C Cable', 'Anker', 'A8852', 'Reliable USB-C charging and data cable.', 1299.00, 35, 'active', '2026-09-08 05:34:06', NULL),
(22, 11, 2, 'Rain Design mStand', 'Rain Design', 'mStand', 'Aluminum laptop stand designed to improve desk ergonomics.', 6999.00, 12, 'active', '2026-09-08 05:34:06', NULL),
(23, 11, 3, 'UGREEN Adjustable Laptop Stand', 'UGREEN', 'LP451', 'Foldable adjustable stand for laptops and tablets.', 3299.00, 22, 'active', '2026-09-08 05:34:06', NULL),
(24, 11, 4, 'Baseus Metal Desktop Stand', 'Baseus', 'SUZB', 'Compact metal stand for laptops and tablets.', 2499.00, 18, 'active', '2026-09-08 05:34:06', NULL),
(25, 7, 14, 'S. M Karimul Hassan', 'trewqtrwq', 'afdad', 'adfasd', 444.00, 44, 'active', '2026-09-08 10:09:45', NULL),
(26, 6, 14, 'laptop', 'adfads', 'adasdf', 'asagfassdfs', 44444.00, 0, 'active', '2026-09-08 10:30:58', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `image_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`image_id`, `product_id`, `image_url`, `is_primary`) VALUES
(1, 1, 'images/products/product_1.jpg', 1),
(2, 1, 'images/products/product_1_2.jpg', 0),
(3, 2, 'images/products/product_2.jpg', 1),
(4, 2, 'images/products/product_2_2.jpg', 0),
(5, 3, 'images/products/product_3.jpg', 1),
(6, 3, 'images/products/product_3_2.jpg', 0),
(7, 4, 'images/products/product_4.jpg', 1),
(8, 4, 'images/products/product_4_2.jpg', 0),
(9, 5, 'images/products/product_5.jpg', 1),
(10, 5, 'images/products/product_5_2.jpg', 0),
(11, 6, 'images/products/product_6.jpg', 1),
(12, 6, 'images/products/product_6_2.jpg', 0),
(13, 7, 'images/products/product_7.jpg', 1),
(14, 7, 'images/products/product_7_2.jpg', 0),
(15, 8, 'images/products/product_8.jpg', 1),
(16, 8, 'images/products/product_8_2.jpg', 0),
(17, 9, 'images/products/product_9.jpg', 1),
(18, 9, 'images/products/product_9_2.jpg', 0),
(19, 10, 'images/products/product_10.jpg', 1),
(20, 10, 'images/products/product_10_2.jpg', 0),
(21, 11, 'images/products/product_11.jpg', 1),
(22, 11, 'images/products/product_11_2.jpg', 0),
(23, 12, 'images/products/product_12.jpg', 1),
(24, 12, 'images/products/product_12_2.jpg', 0),
(25, 13, 'images/products/product_13.jpg', 1),
(26, 13, 'images/products/product_13_2.jpg', 0),
(27, 14, 'images/products/product_14.jpg', 1),
(28, 14, 'images/products/product_14_2.jpg', 0),
(29, 15, 'images/products/product_15.jpg', 1),
(30, 15, 'images/products/product_15_2.jpg', 0),
(31, 16, 'images/products/product_16.jpg', 1),
(32, 16, 'images/products/product_16_2.jpg', 0),
(33, 17, 'images/products/product_17.jpg', 1),
(34, 17, 'images/products/product_17_2.jpg', 0),
(35, 18, 'images/products/product_18.jpg', 1),
(36, 18, 'images/products/product_18_2.jpg', 0),
(37, 19, 'images/products/product_19.jpg', 1),
(38, 19, 'images/products/product_19_2.jpg', 0),
(39, 20, 'images/products/product_20.jpg', 1),
(40, 20, 'images/products/product_20_2.jpg', 0),
(41, 21, 'images/products/product_21.jpg', 1),
(42, 21, 'images/products/product_21_2.jpg', 0),
(43, 22, 'images/products/product_22.jpg', 1),
(44, 22, 'images/products/product_22_2.jpg', 0),
(45, 23, 'images/products/product_23.jpg', 1),
(46, 23, 'images/products/product_23_2.jpg', 0),
(47, 24, 'images/products/product_24.jpg', 1),
(48, 24, 'images/products/product_24_2.jpg', 0),
(49, 25, 'https://sumashtech.sgp1.cdn.digitaloceanspaces.com/card_image/None/HP_15-fd0215dx.webp', 1),
(50, 26, 'images/6a9fe3e2bf2981.19627986.jpg', 1);

-- --------------------------------------------------------

--
-- Table structure for table `product_specs`
--

CREATE TABLE `product_specs` (
  `spec_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `spec_key` varchar(50) NOT NULL,
  `spec_value` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_specs`
--

INSERT INTO `product_specs` (`spec_id`, `product_id`, `spec_key`, `spec_value`) VALUES
(1, 1, 'CPU', 'AMD Ryzen 7 7735HS'),
(2, 1, 'RAM', '16GB DDR5'),
(3, 1, 'Storage', '512GB SSD'),
(4, 1, 'GPU', 'NVIDIA RTX 4050 6GB'),
(5, 2, 'CPU', 'Intel Core i7-13650HX'),
(6, 2, 'RAM', '16GB DDR5'),
(7, 2, 'Storage', '1TB SSD'),
(8, 2, 'GPU', 'NVIDIA RTX 4060 8GB'),
(9, 3, 'CPU', 'Intel Core i5-13420H'),
(10, 3, 'RAM', '16GB DDR5'),
(11, 3, 'Storage', '512GB SSD'),
(12, 3, 'Display', '15.6-inch FHD 144Hz'),
(13, 4, 'CPU', 'Intel Core i5-1335U'),
(14, 4, 'RAM', '16GB DDR5'),
(15, 4, 'Storage', '512GB SSD'),
(16, 4, 'Display', '14-inch FHD'),
(17, 5, 'CPU', 'Intel Core i5-1335U'),
(18, 5, 'RAM', '16GB DDR4'),
(19, 5, 'Storage', '512GB SSD'),
(20, 5, 'Display', '14-inch FHD IPS'),
(21, 6, 'CPU', 'Intel Core Ultra 5 125U'),
(22, 6, 'RAM', '16GB DDR5'),
(23, 6, 'Storage', '512GB SSD'),
(24, 6, 'Display', '14-inch FHD'),
(25, 7, 'CPU', 'AMD Ryzen 7 7700'),
(26, 7, 'RAM', '32GB DDR5'),
(27, 7, 'Storage', '1TB NVMe SSD'),
(28, 7, 'GPU', 'NVIDIA RTX 4060'),
(29, 8, 'CPU', 'Intel Core i5-12400'),
(30, 8, 'RAM', '16GB DDR4'),
(31, 8, 'Storage', '512GB NVMe SSD'),
(32, 8, 'GPU', 'Integrated UHD 730'),
(33, 9, 'CPU', 'AMD Ryzen 5 5600G'),
(34, 9, 'RAM', '16GB DDR4'),
(35, 9, 'Storage', '512GB NVMe SSD'),
(36, 9, 'GPU', 'Integrated Radeon Graphics'),
(37, 10, 'CPU', 'AMD Ryzen 7 7800X3D'),
(38, 10, 'RAM', '32GB DDR5'),
(39, 10, 'Storage', '2TB NVMe SSD'),
(40, 10, 'GPU', 'NVIDIA RTX 4070 Super'),
(41, 11, 'CPU', 'Intel Core i5-14400F'),
(42, 11, 'RAM', '16GB DDR5'),
(43, 11, 'Storage', '1TB NVMe SSD'),
(44, 11, 'GPU', 'NVIDIA RTX 4060'),
(45, 12, 'CPU', 'AMD Ryzen 5 7600'),
(46, 12, 'RAM', '16GB DDR5'),
(47, 12, 'Storage', '1TB NVMe SSD'),
(48, 12, 'GPU', 'NVIDIA RTX 4060'),
(49, 13, 'Switch', 'Gateron G Pro Brown'),
(50, 13, 'Layout', 'TKL / 87-key'),
(51, 13, 'Connection', 'Bluetooth + USB-C'),
(52, 13, 'Backlight', 'White LED'),
(53, 14, 'Switch', 'GX Brown Tactile'),
(54, 14, 'Layout', 'TKL'),
(55, 14, 'Connection', 'LIGHTSPEED + Bluetooth'),
(56, 14, 'Polling Rate', '1000 Hz'),
(57, 15, 'Switch', 'Outemu Blue'),
(58, 15, 'Layout', 'TKL / 87-key'),
(59, 15, 'Connection', 'USB'),
(60, 15, 'Backlight', 'RGB'),
(61, 16, 'Driver', '30mm'),
(62, 16, 'Connection', 'Bluetooth 5.2'),
(63, 16, 'Battery', 'Up to 30 hours'),
(64, 16, 'Feature', 'Active Noise Cancellation'),
(65, 17, 'Driver', '40mm'),
(66, 17, 'Connection', 'Bluetooth 5.3'),
(67, 17, 'Battery', 'Up to 70 hours'),
(68, 17, 'Feature', 'Adaptive Noise Cancelling'),
(69, 18, 'Driver', '40mm'),
(70, 18, 'Connection', 'Bluetooth 5.0'),
(71, 18, 'Battery', 'Up to 40 hours'),
(72, 18, 'Feature', 'Hybrid ANC'),
(73, 19, 'Length', '2m'),
(74, 19, 'Connector', 'USB-C to USB-C'),
(75, 19, 'Power', '100W'),
(76, 19, 'Data', 'USB 2.0'),
(77, 20, 'Length', '2m'),
(78, 20, 'Connector', 'USB-C to HDMI'),
(79, 20, 'Resolution', '4K@60Hz'),
(80, 20, 'Feature', 'Plug and Play'),
(81, 21, 'Length', '1.8m'),
(82, 21, 'Connector', 'USB-C to USB-C'),
(83, 21, 'Power', '100W'),
(84, 21, 'Data', 'USB 2.0'),
(85, 22, 'Material', 'Aluminum'),
(86, 22, 'Compatibility', 'Laptop'),
(87, 22, 'Adjustment', 'Fixed height'),
(88, 22, 'Feature', 'Cable management'),
(89, 23, 'Material', 'Aluminum alloy'),
(90, 23, 'Compatibility', 'Laptop / Tablet'),
(91, 23, 'Adjustment', 'Adjustable'),
(92, 23, 'Feature', 'Foldable'),
(93, 24, 'Material', 'Metal'),
(94, 24, 'Compatibility', 'Laptop / Tablet'),
(95, 24, 'Adjustment', 'Fixed angle'),
(96, 24, 'Feature', 'Non-slip base'),
(97, 26, 'afsfsd', '8gb');

-- --------------------------------------------------------

--
-- Table structure for table `product_views`
--

CREATE TABLE `product_views` (
  `view_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_views`
--

INSERT INTO `product_views` (`view_id`, `user_id`, `product_id`, `viewed_at`) VALUES
(1, 5, 13, '2026-09-02 04:00:00'),
(2, 5, 16, '2026-09-02 04:04:00'),
(3, 5, 14, '2026-09-02 04:08:00'),
(4, 6, 4, '2026-09-03 08:20:00'),
(5, 6, 5, '2026-09-03 08:24:00'),
(6, 7, 23, '2026-09-04 05:15:00'),
(7, 7, 22, '2026-09-04 05:18:00'),
(8, 8, 2, '2026-09-05 10:00:00'),
(9, NULL, 15, '2026-09-06 03:30:00'),
(10, NULL, 18, '2026-09-06 03:33:00'),
(11, NULL, 16, '2026-09-08 09:42:28'),
(12, NULL, 22, '2026-09-08 09:43:05'),
(13, 13, 22, '2026-09-08 09:57:43'),
(14, 13, 22, '2026-09-08 09:57:58'),
(15, 14, 21, '2026-09-08 10:03:41'),
(16, 14, 5, '2026-09-08 10:57:16');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `comment` text DEFAULT NULL,
  `is_flagged` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`review_id`, `product_id`, `user_id`, `rating`, `comment`, `is_flagged`, `created_at`) VALUES
(1, 13, 5, 5, 'Excellent mechanical keyboard. The keys feel great and the wireless connection is stable.', 0, '2026-08-16 06:00:00'),
(2, 16, 6, 5, 'Very comfortable and the noise cancellation is excellent for study and travel.', 0, '2026-08-19 12:30:00'),
(3, 20, 10, 4, 'The cable works well with my monitor and gives a stable 4K connection.', 0, '2026-08-25 08:10:00');

-- --------------------------------------------------------

--
-- Table structure for table `search_query_log`
--

CREATE TABLE `search_query_log` (
  `query_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `raw_query` varchar(255) NOT NULL,
  `used_ai` tinyint(1) NOT NULL DEFAULT 0,
  `result_count` int(11) NOT NULL DEFAULT 0,
  `searched_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `search_query_log`
--

INSERT INTO `search_query_log` (`query_id`, `user_id`, `raw_query`, `used_ai`, `result_count`, `searched_at`) VALUES
(1, 5, 'lightweight laptop for programming under 100k', 1, 3, '2026-09-02 04:10:00'),
(2, 6, 'wireless headphones with noise cancellation', 1, 3, '2026-09-02 05:20:00'),
(3, 7, 'mechanical keyboard for gaming', 1, 3, '2026-09-03 03:15:00'),
(4, 8, 'affordable desktop PC for office work', 0, 3, '2026-09-03 09:30:00'),
(5, 9, 'USB C cable for fast charging', 1, 3, '2026-09-04 07:45:00'),
(6, NULL, 'laptop stand', 0, 3, '2026-09-05 03:10:00'),
(7, 10, 'gaming PC with RTX graphics', 1, 3, '2026-09-05 12:00:00'),
(8, 11, 'best laptop for programming and study', 1, 3, '2026-09-06 06:25:00'),
(9, NULL, 'gaming laptop under 140k', 1, 2, '2026-09-08 09:34:53'),
(10, NULL, 'gaming laptop under 140k', 1, 2, '2026-09-08 09:37:31'),
(11, NULL, '<img src=x onerror=alert(1)>', 1, 0, '2026-09-08 09:37:31'),
(12, NULL, 'gaming laptop under 140k', 1, 2, '2026-09-08 09:37:32'),
(13, NULL, '<img src=x onerror=alert(1)>', 1, 0, '2026-09-08 09:37:32'),
(14, NULL, 'gaming laptop under 140k', 1, 2, '2026-09-08 09:46:03'),
(15, 13, 'hello', 1, 0, '2026-09-08 09:58:07'),
(16, 13, 'smart phone', 1, 0, '2026-09-08 09:58:23'),
(17, 13, 'gaming laptop under 60000', 1, 0, '2026-09-08 09:59:58'),
(18, NULL, 'Sony WH-1000XM5', 0, 1, '2026-09-08 10:28:56'),
(19, 14, 'laptop', 0, 10, '2026-09-08 10:31:23'),
(20, NULL, 'Suggest a gaming laptop under 140000 taka.', 1, 2, '2026-09-08 10:40:21'),
(21, NULL, 'Which of those is cheaper?', 1, 0, '2026-09-08 10:40:21'),
(22, NULL, 'Suggest a gaming laptop under 140000 taka.', 1, 3, '2026-09-08 10:42:56'),
(23, NULL, 'Which of those is cheaper?', 1, 1, '2026-09-08 10:42:56'),
(24, NULL, 'বাংলায় বলুন', 1, 1, '2026-09-08 10:42:57'),
(25, NULL, 'gaming laptop under 140k', 1, 3, '2026-09-08 10:43:49'),
(26, NULL, '<img src=x onerror=alert(1)>', 1, 0, '2026-09-08 10:43:49'),
(27, NULL, 'Show me a phone', 1, 0, '2026-09-08 10:43:50'),
(28, NULL, 'gaming laptop under 140k', 1, 3, '2026-09-08 10:43:53'),
(29, NULL, '<img src=x onerror=alert(1)>', 1, 0, '2026-09-08 10:43:53'),
(30, NULL, 'Show me a phone', 1, 0, '2026-09-08 10:43:54'),
(31, NULL, 'gaming laptop under 140k', 1, 2, '2026-09-08 10:43:54'),
(32, 14, 'hello', 1, 0, '2026-09-08 10:57:00'),
(33, 14, 'give me product list', 1, 6, '2026-09-08 10:57:10'),
(34, 14, 'laptop', 0, 10, '2026-09-08 10:57:22'),
(35, NULL, 'Show gaming laptops under 140000 taka', 1, 3, '2026-09-08 11:04:05'),
(36, NULL, 'Find a quantum teleportation machine for sale', 1, 0, '2026-09-08 11:04:06');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','seller','customer') NOT NULL DEFAULT 'customer',
  `status` enum('active','suspended','pending_approval') NOT NULL DEFAULT 'active',
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `role`, `status`, `full_name`, `email`, `phone`, `created_at`) VALUES
(1, 'admin', '$2y$12$bFsTrrNKZJIaSsJPOGpjtOp32y6GYwQO6kz4E69i24qUjPQxTLHXS', 'admin', 'active', 'System Administrator', 'admin@projuktimart.com', '01710000001', '2026-09-08 05:34:06'),
(2, 'techhub_bd', '$2y$12$LM2C4W4/dSycj8veF0RHXeP57wqwjbMH0VxsICOfYMX6RjCC705w2', 'seller', 'active', 'TechHub Bangladesh', 'seller1@projuktimart.com', '01710000002', '2026-09-08 05:34:06'),
(3, 'bytezone', '$2y$12$LM2C4W4/dSycj8veF0RHXeP57wqwjbMH0VxsICOfYMX6RjCC705w2', 'seller', 'active', 'ByteZone Electronics', 'seller2@projuktimart.com', '01710000003', '2026-09-08 05:34:06'),
(4, 'gadget_world', '$2y$12$LM2C4W4/dSycj8veF0RHXeP57wqwjbMH0VxsICOfYMX6RjCC705w2', 'seller', 'active', 'Gadget World BD', 'seller3@projuktimart.com', '01710000004', '2026-09-08 05:34:06'),
(5, 'rahim', '$2y$12$MoW1OMCwylZC3lukgVTGe.DUuRAz5xkb4kXJf9DKoxo8trqO80OOm', 'customer', 'active', 'Rahim Ahmed', 'rahim@example.com', '01711000005', '2026-09-08 05:34:06'),
(6, 'nabila', '$2y$12$MoW1OMCwylZC3lukgVTGe.DUuRAz5xkb4kXJf9DKoxo8trqO80OOm', 'customer', 'active', 'Nabila Sultana', 'nabila@example.com', '01711000006', '2026-09-08 05:34:06'),
(7, 'sadia', '$2y$12$MoW1OMCwylZC3lukgVTGe.DUuRAz5xkb4kXJf9DKoxo8trqO80OOm', 'customer', 'active', 'Sadia Karim', 'sadia@example.com', '01711000007', '2026-09-08 05:34:06'),
(8, 'tanvir', '$2y$12$MoW1OMCwylZC3lukgVTGe.DUuRAz5xkb4kXJf9DKoxo8trqO80OOm', 'customer', 'active', 'Tanvir Hasan', 'tanvir@example.com', '01711000008', '2026-09-08 05:34:06'),
(9, 'farhan', '$2y$12$MoW1OMCwylZC3lukgVTGe.DUuRAz5xkb4kXJf9DKoxo8trqO80OOm', 'customer', 'active', 'Farhan Kabir', 'farhan@example.com', '01711000009', '2026-09-08 05:34:06'),
(10, 'mim', '$2y$12$MoW1OMCwylZC3lukgVTGe.DUuRAz5xkb4kXJf9DKoxo8trqO80OOm', 'customer', 'active', 'Mim Chowdhury', 'mim@example.com', '01711000010', '2026-09-08 05:34:06'),
(11, 'arif', '$2y$12$MoW1OMCwylZC3lukgVTGe.DUuRAz5xkb4kXJf9DKoxo8trqO80OOm', 'customer', 'active', 'Arif Hossain', 'arif@example.com', '01711000011', '2026-09-08 05:34:06'),
(12, 'jannat', '$2y$12$MoW1OMCwylZC3lukgVTGe.DUuRAz5xkb4kXJf9DKoxo8trqO80OOm', 'customer', 'active', 'Jannat Rahman', 'jannat@example.com', '01711000012', '2026-09-08 05:34:06'),
(13, 'karimul', '$2y$10$XzvbHxpmAoHtFmV7TY0iLe4Dv1zUi6ApvEiFVr9/p/4.X1X5adZK6', 'customer', 'active', 'karimul', 'karimul@gmail.com', '123', '2026-09-08 09:57:32'),
(14, 'Shuvo', '$2y$10$CwLV7AF3KAxBSvj0crtcx.SBlrTXCUuSUTOK4J8uBowwspGNavLnK', 'seller', 'active', 'shuvo shuvo', 'shuvo@gmail.com', '123', '2026-09-08 10:02:46');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `activity_action_time` (`action`,`logged_at`),
  ADD KEY `activity_time` (`logged_at`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`cart_item_id`),
  ADD UNIQUE KEY `unique_cart_product` (`cart_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`),
  ADD KEY `parent_category_id` (`parent_category_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `address_id` (`address_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `history_order_time` (`order_id`,`changed_at`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `seller_id` (`seller_id`);
ALTER TABLE `products` ADD FULLTEXT KEY `ft_product_search` (`name`,`description`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_specs`
--
ALTER TABLE `product_specs`
  ADD PRIMARY KEY (`spec_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_views`
--
ALTER TABLE `product_views`
  ADD PRIMARY KEY (`view_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `unique_user_product_review` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `search_query_log`
--
ALTER TABLE `search_query_log`
  ADD PRIMARY KEY (`query_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `cart_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `product_specs`
--
ALTER TABLE `product_specs`
  MODIFY `spec_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT for table `product_views`
--
ALTER TABLE `product_views`
  MODIFY `view_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `search_query_log`
--
ALTER TABLE `search_query_log`
  MODIFY `query_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `cart` (`cart_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`address_id`) ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON UPDATE CASCADE;

--
-- Constraints for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `order_status_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_specs`
--
ALTER TABLE `product_specs`
  ADD CONSTRAINT `product_specs_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_views`
--
ALTER TABLE `product_views`
  ADD CONSTRAINT `product_views_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `product_views_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `search_query_log`
--
ALTER TABLE `search_query_log`
  ADD CONSTRAINT `search_query_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
