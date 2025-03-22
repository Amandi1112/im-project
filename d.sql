CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
CREATE TABLE `categories` (
  `category_id` char(6) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `items` (
  `item_id` char(6) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category_id` char(6) NOT NULL,
  `supplier_id` varchar(10) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_per_unit` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `purchase_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--CREATE TABLE `supplier` (
  `supplier_id` varchar(10) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `nic` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `registration_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create a table to store supplier performance metrics
CREATE TABLE IF NOT EXISTS `supplier_performance` (
  `performance_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` varchar(10) NOT NULL,
  `evaluation_period_start` date NOT NULL,
  `evaluation_period_end` date NOT NULL,
  `total_purchases` int(11) NOT NULL,
  `total_value` decimal(10,2) NOT NULL,
  `avg_price` decimal(10,2) NOT NULL,
  `price_rating` int(11) NOT NULL,
  `diversity_rating` int(11) NOT NULL,
  `consistency_rating` int(11) NOT NULL,
  `overall_rating` decimal(3,1) NOT NULL,
  `evaluation_date` datetime DEFAULT current_timestamp(),
  `evaluated_by` int(11) NOT NULL,
  PRIMARY KEY (`performance_id`),
  KEY `supplier_id` (`supplier_id`),
  CONSTRAINT `fk_performance_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `supplier_reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` varchar(10) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `report_file` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  PRIMARY KEY (`report_id`),
  KEY `supplier_id` (`supplier_id`),
  CONSTRAINT `fk_reports_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `supplier_delivery` (
  `delivery_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` varchar(10) NOT NULL,
  `item_id` char(6) NOT NULL,
  `expected_date` date NOT NULL,
  `actual_date` date DEFAULT NULL,
  `delivery_status` enum('pending','on_time','delayed','incomplete','complete') NOT NULL DEFAULT 'pending',
  `delay_days` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`delivery_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `fk_delivery_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_delivery_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create a table to store supplier quality metrics
CREATE TABLE IF NOT EXISTS `supplier_quality` (
  `quality_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` varchar(10) NOT NULL,
  `item_id` char(6) NOT NULL,
  `inspection_date` date NOT NULL,
  `quality_score` int(11) NOT NULL COMMENT 'Score from 1-5',
  `defect_rate` decimal(5,2) DEFAULT NULL COMMENT 'Percentage',
  `return_quantity` int(11) DEFAULT NULL,
  `inspector_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`quality_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `fk_quality_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_quality_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `safety_stock` (
  `safety_stock_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` char(6) NOT NULL,
  `safety_stock_quantity` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`safety_stock_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `fk_safety_stock_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `customer_transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `membership_number` int(11) NOT NULL,
  `item_id` char(6) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_per_unit` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `transaction_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`transaction_id`),
  KEY `membership_number` (`membership_number`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `fk_transaction_customer` FOREIGN KEY (`membership_number`) REFERENCES `membership_numbers` (`membership_number`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_transaction_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `membership_numbers` (
  `id` int(6) UNSIGNED NOT NULL,
  `membership_number` varchar(6) PRI NOT NULL,
  `nic_number` varchar(12) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  credit_limit decimal(10,2) NOT NULL,
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;