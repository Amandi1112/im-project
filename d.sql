-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 01, 2025 at 06:54 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mywebsite`
--

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `item_id` int(11) NOT NULL,
  `item_code` varchar(15) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `price_per_unit` decimal(10,2) NOT NULL,
  `current_quantity` int(11) NOT NULL DEFAULT 0,
  `supplier_id` varchar(10) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_purchases`
--

CREATE TABLE `item_purchases` (
  `purchase_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_per_unit` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `purchase_date` date DEFAULT NULL,
  `expire_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_status` enum('paid','unpaid','partial') NOT NULL,
  `supplier_id` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `full_name` varchar(255) NOT NULL,
  `bank_membership_number` char(6) NOT NULL,
  `id` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `nic` varchar(20) NOT NULL,
  `date_of_birth` date NOT NULL,
  `age` int(11) NOT NULL,
  `telephone_number` varchar(20) NOT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `monthly_income` decimal(10,2) NOT NULL,
  `credit_limit` int(10) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `current_credit_balance` decimal(10,2) DEFAULT 0.00,
  `available_credit` decimal(10,2) GENERATED ALWAYS AS (`credit_limit` - `current_credit_balance`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`full_name`, `bank_membership_number`, `id`, `address`, `nic`, `date_of_birth`, `age`, `telephone_number`, `occupation`, `monthly_income`, `credit_limit`, `registration_date`, `last_updated`, `current_credit_balance`) VALUES
('amandi rashini', 'B00001', 'C00001', 'karawita, uda karawita', '200148591848', '2001-11-12', 23, '0704859118', 'teacher', 50000.00, 15000, '2025-04-01 14:41:33', '2025-04-01 14:41:33', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `purchase_id` int(11) NOT NULL,
  `member_id` varchar(50) NOT NULL,
  `item_id` int(11) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price_per_unit` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `purchase_date` date NOT NULL,
  `current_credit_balance` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `supplier_id` varchar(10) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `nic` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `registration_date` datetime NOT NULL,
  `contact_number` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`supplier_id`, `supplier_name`, `nic`, `address`, `registration_date`, `contact_number`) VALUES
('S00001', 'ABC Traders - John Doe', '987654321V', '123, Main Street, Colombo, Sri Lanka', '2025-03-30 06:40:32', '0751236454'),
('S00002', 'XYZ Distributors - Jane Smith', '123456789012', '45, Galle Road, Gampaha, Sri Lanka', '2025-03-30 06:41:09', '0704512336'),
('S00003', 'FreshMart Suppliers - Alex Ray', '876543219V', '78, Kandy Road, Kandy, Sri Lanka', '2025-03-30 06:41:38', '0704512336'),
('S00004', 'Sunlight Goods - Mary Anne', '200145874958', '56, Beach Road, Negombo, Sri Lanka', '2025-03-30 06:42:50', '0784591226'),
('S00005', 'GreenLeaf Exports - Tom Silva', '198452634595', '90, High Street, Kurunegala, Sri Lanka', '2025-03-30 06:43:17', '0704815997'),
('S00006', 'Oceanic Products - Sarah Lee', '765432189V', '12, Lakeview Road, Matara, Sri Lanka', '2025-03-30 06:43:43', '0784519663'),
('S00007', 'AgriPro Solutions - Mark Dias', '200015489562', '34, Hilltop Avenue, Nuwara Eliya, Sri Lanka', '2025-03-30 06:44:08', '0784518774'),
('S00008', 'City Wholesale - David Perera', '198748152635', '22, Market Street, Ratnapura, Sri Lanka', '2025-03-30 06:45:31', '0704515228'),
('S00009', 'BlueSky Logistics - Nina Gomez', '199920415123', '88, Tower Lane, Jaffna, Sri Lanka', '2025-03-30 06:46:03', '0451245778'),
('S00010', 'Elite Supplies - Kevin Brown', '789012345V', '15, Kingsway Road, Batticaloa, Sri Lanka', '2025-03-30 06:46:30', '0704515228'),
('S00011', 'Maliban - sawindu', '200154871535', 'colombo 7, high way road', '2025-03-31 16:37:51', '0705615448');

--
-- Triggers `supplier`
--
DELIMITER $$
CREATE TRIGGER `validate_contact_number` BEFORE INSERT ON `supplier` FOR EACH ROW BEGIN  
    IF NEW.contact_number NOT REGEXP '^0[0-9]{9}$' THEN  
        SIGNAL SQLSTATE '45000'  
        SET MESSAGE_TEXT = 'Invalid contact number! Must start with 0 and have exactly 10 digits.';  
    END IF;  
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_payments`
--

CREATE TABLE `supplier_payments` (
  `id` int(11) NOT NULL,
  `supplier_id` varchar(10) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `position` enum('admin','clerk','accountant') NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `position`, `created_at`, `updated_at`) VALUES
(1, 'amandi rashini', 'amandi@gmail.com', '$2y$10$sFC0fR0InmmoNX90c26fTuNgSrfak4g1bbWJWK6xRGXYiEayA/kU2', 'admin', '2025-03-23 18:15:38', '2025-03-24 08:05:10'),
(2, 'Ruwan Fernando', 'ruwan.fernando@example.com', '$2y$10$kuCm2iVw21W5EUyl4XliNu/m400/O1i451SCOutv.IO0tWbj79.E.', 'clerk', '2025-03-23 18:16:02', '2025-03-31 20:07:12'),
(3, 'Amanda Silva', 'amanda.silva@example.com', '$2y$10$Wwj/9vxuoWxsAJe2NzhxNOTkyvyGTrEOwcYcctq5oUwZSiuPxU/sK', 'accountant', '2025-03-23 18:16:26', '2025-03-24 08:06:05');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`),
  ADD UNIQUE KEY `item_code` (`item_code`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `item_purchases`
--
ALTER TABLE `item_purchases`
  ADD PRIMARY KEY (`purchase_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `idx_supplier_payment` (`supplier_id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bank_membership_number` (`bank_membership_number`),
  ADD UNIQUE KEY `nic` (`nic`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`purchase_id`),
  ADD KEY `fk_member` (`member_id`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`supplier_id`),
  ADD UNIQUE KEY `supplier_nic` (`nic`);

--
-- Indexes for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `item_purchases`
--
ALTER TABLE `item_purchases`
  MODIFY `purchase_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `purchase_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`) ON DELETE SET NULL;

--
-- Constraints for table `item_purchases`
--
ALTER TABLE `item_purchases`
  ADD CONSTRAINT `item_purchases_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `fk_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  ADD CONSTRAINT `supplier_payments_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
