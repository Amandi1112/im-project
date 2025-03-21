-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 21, 2025 at 04:20 PM
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
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` char(6) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `created_at`) VALUES
('C00001', 'Baby Products', '2025-03-20 21:24:47'),
('C00002', 'Food Cupboard', '2025-03-20 21:24:57'),
('C00003', 'Dairy', '2025-03-20 21:25:06'),
('C00004', 'Household', '2025-03-20 21:25:13'),
('C00005', 'Cooking Essentials', '2025-03-20 21:25:27'),
('C00006', 'Bakery', '2025-03-20 21:25:36'),
('C00007', 'Snacks & Confectionery', '2025-03-20 21:25:47'),
('C00008', 'Rice', '2025-03-20 21:25:53'),
('C00009', 'Seeds & Spices', '2025-03-20 21:26:00'),
('C00010', 'Desserts & Ingredients', '2025-03-20 21:26:09'),
('C00011', 'Tea & Coffee', '2025-03-20 21:26:17'),
('C00012', 'Gifting', '2025-03-20 21:26:24'),
('C00013', 'Party Shop', '2025-03-20 21:26:31'),
('C00014', 'Health & Beauty', '2025-03-20 21:26:40'),
('C00015', 'Fashion', '2025-03-20 21:26:47'),
('C00016', 'Stationery', '2025-03-20 21:26:54');

-- --------------------------------------------------------

--
-- Table structure for table `education_details`
--

CREATE TABLE `education_details` (
  `email` varchar(100) NOT NULL,
  `qualification` varchar(255) NOT NULL,
  `institute` varchar(255) NOT NULL,
  `study_duration` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

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

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `item_name`, `category_id`, `supplier_id`, `quantity`, `price_per_unit`, `total_price`, `created_at`, `purchase_date`) VALUES
('I00001', 'Samaposha', 'C00002', 'S00006', 50, 140.00, 7000.00, '2025-03-20 21:35:57', '2025-03-18'),
('I00002', 'LankaSoy curry Favor', 'C00002', 'S00006', 100, 90.00, 9000.00, '2025-03-20 21:36:35', '2025-03-10'),
('I00003', 'Marmite', 'C00002', 'S00006', 30, 260.00, 7800.00, '2025-03-20 21:37:13', '2025-03-11'),
('I00004', 'soap', 'C00001', 'S00001', 300, 90.00, 27000.00, '2025-03-20 21:38:07', '2025-03-03'),
('I00005', 'Predia-pro', 'C00001', 'S00002', 10, 2000.00, 20000.00, '2025-03-20 21:38:47', '2025-03-13'),
('I00006', 'Diaper Pants', 'C00001', 'S00001', 30, 300.00, 9000.00, '2025-03-20 21:41:02', '2025-12-03');

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(6) UNSIGNED NOT NULL,
  `membership_number` varchar(6) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `membership_age` int(3) NOT NULL,
  `nic_number` varchar(12) NOT NULL,
  `telephone_number` varchar(15) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `membership_number`, `full_name`, `address`, `membership_age`, `nic_number`, `telephone_number`, `created_at`, `updated_at`) VALUES
(1, 'C37665', 'Charles Johnson', '27 Oak Ave, Lakeside', 17, '133205915v', '0709676642', '2025-03-16 08:06:52', '2025-03-16 11:56:52'),
(2, 'B00002', 'Richard White', '889 Maple Rd, Lakeside', 12, '186456754v', '0755124476', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(3, 'B00003', 'Elizabeth Hernandez', '887 Oak Ave, Northfield', 18, '923757153v', '0714128177', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(4, 'B00004', 'Elizabeth Anderson', '887 Maple Rd, Westwood', 5, '185586197v', '0798228252', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(5, 'B00005', 'Karen Jackson', '674 Park Rd, Springfield', 17, '281114386v', '0700668573', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(6, 'B00006', 'Elizabeth Wilson', '474 Oak Ave, Rivertown', 1, '417940334v', '0771267470', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(7, 'B00007', 'Elizabeth Moore', '777 Washington Ave, Hillcrest', 18, '643191412v', '0721119067', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(8, 'B00008', 'Richard Jones', '14 Main St, Northfield', 2, '468728458v', '0721982579', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(9, 'B00009', 'Margaret Davis', '460 Park Rd, Westwood', 11, '469412265v', '0779899485', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(10, 'B00010', 'Charles Jones', '291 Washington Ave, Oakville', 4, '663082630v', '0729577514', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(11, 'B00011', 'Linda Garcia', '850 Cedar Ln, Rivertown', 14, '571443571v', '0781081803', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(12, 'B00012', 'Thomas Miller', '765 Cedar Ln, Rivertown', 14, '777684047v', '0761411285', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(13, 'B00013', 'Jennifer Smith', '695 Main St, Oakville', 2, '675627836v', '0705038478', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(14, 'B00014', 'Michael White', '311 Maple Rd, Hillcrest', 6, '186914840v', '0766450109', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(15, 'B00015', 'Michael White', '108 Lake Blvd, Lakeside', 15, '134450814v', '0741672245', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(16, 'B00016', 'Richard Martin', '94 Park Rd, Southport', 2, '466369029v', '0768226603', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(17, 'B00017', 'William Hernandez', '841 Pine Dr, Springfield', 17, '805118541v', '0746539492', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(18, 'B00018', 'Susan Thompson', '583 Washington Ave, Springfield', 12, '313263261v', '0743010184', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(19, 'B00019', 'William Rodriguez', '557 Main St, Maplewood', 16, '509555448v', '0754469836', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(20, 'B00020', 'Michael Miller', '961 Maple Rd, Southport', 12, '366812178v', '0737160525', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(21, 'B00021', 'Richard Davis', '535 River Dr, Lakeside', 1, '994782284v', '0776032577', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(22, 'B00022', 'Sarah Moore', '407 Maple Rd, Maplewood', 8, '266995724v', '0744539372', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(23, 'B00023', 'Linda Williams', '701 Lake Blvd, Eastdale', 20, '986167501v', '0711926283', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(24, 'B00024', 'Charles Garcia', '721 Cedar Ln, Northfield', 1, '726828339v', '0790675453', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(25, 'B00025', 'Michael Thompson', '282 River Dr, Lakeside', 7, '361824991v', '0706940791', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(26, 'B00026', 'Charles Thomas', '115 Main St, Northfield', 13, '869431824v', '0792549593', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(27, 'B00027', 'Charles Miller', '140 Oak Ave, Oakville', 16, '145191180v', '0754930312', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(28, 'B00028', 'James Thomas', '816 Oak Ave, Maplewood', 19, '497772147v', '0709425740', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(29, 'B00029', 'James Miller', '47 Park Rd, Northfield', 19, '106309868v', '0799561825', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(30, 'B00030', 'Jane Garcia', '101 Park Rd, Rivertown', 17, '660084048v', '0709653625', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(31, 'B00031', 'Elizabeth Thomas', '864 Pine Dr, Eastdale', 4, '687035815v', '0720441609', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(32, 'B00032', 'Jessica Martin', '678 River Dr, Lakeside', 15, '736197525v', '0700078963', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(33, 'B00033', 'John Rodriguez', '488 Lake Blvd, Lakeside', 18, '887647841v', '0767572554', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(34, 'B00034', 'James Hernandez', '841 River Dr, Northfield', 15, '111249844v', '0704813569', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(35, 'B00035', 'Elizabeth Martinez', '891 Park Rd, Eastdale', 15, '739811562v', '0752408065', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(36, 'B00036', 'Robert Jones', '88 River Dr, Southport', 3, '440223073v', '0773341584', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(37, 'B00037', 'Susan Jackson', '909 Washington Ave, Maplewood', 13, '939419124v', '0755309086', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(38, 'B00038', 'Robert Jones', '554 Pine Dr, Oakville', 20, '289758148v', '0720336140', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(39, 'B00039', 'Jessica Johnson', '177 Oak Ave, Rivertown', 10, '105999596v', '0718004986', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(40, 'B00040', 'Andrew Thomas', '380 Oak Ave, Springfield', 19, '145262326v', '0740025318', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(41, 'B00041', 'Michael Garcia', '791 Cedar Ln, Eastdale', 15, '573284416v', '0719004452', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(42, 'B00042', 'Elizabeth Davis', '637 River Dr, Oakville', 18, '984840692v', '0700460188', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(43, 'B00043', 'David Martinez', '946 Maple Rd, Westwood', 20, '935914703v', '0779137142', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(44, 'B00044', 'Andrew Thompson', '518 Lake Blvd, Southport', 17, '492779820v', '0760947457', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(45, 'B00045', 'Susan Miller', '618 Washington Ave, Northfield', 3, '832301074v', '0713442529', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(46, 'B00046', 'James Jones', '361 Elm St, Southport', 9, '969667754v', '0759775818', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(47, 'B00047', 'Charles Hernandez', '179 Main St, Northfield', 18, '350075441v', '0724881902', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(48, 'B00048', 'Jessica Jones', '520 Cedar Ln, Westwood', 3, '672782849v', '0777518804', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(49, 'B00049', 'Jane Smith', '432 Oak Ave, Northfield', 6, '393385757v', '0771447030', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(50, 'B00050', 'Emily Rodriguez', '11 Maple Rd, Hillcrest', 9, '758937525v', '0738457938', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(51, 'B00051', 'Charles Smith', '509 Elm St, Northfield', 12, '934167852v', '0747181213', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(52, 'B00052', 'Robert Moore', '680 Maple Rd, Eastdale', 13, '763549754v', '0729966309', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(53, 'B00053', 'John Jones', '248 Cedar Ln, Eastdale', 11, '666519749v', '0771196870', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(54, 'B00054', 'Michael Thompson', '404 River Dr, Northfield', 11, '265104971v', '0786788268', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(55, 'B00055', 'John Hernandez', '38 Maple Rd, Maplewood', 9, '721731870v', '0769980201', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(56, 'B00056', 'Emily Miller', '62 Cedar Ln, Rivertown', 20, '327972165v', '0741325503', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(57, 'B00057', 'Karen Moore', '506 Oak Ave, Rivertown', 20, '850078419v', '0788478183', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(58, 'B00058', 'Jennifer Miller', '41 Oak Ave, Lakeside', 16, '690289304v', '0789411548', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(59, 'B00059', 'Linda Miller', '455 Main St, Springfield', 12, '121683416v', '0747508986', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(60, 'B00060', 'Charles Hernandez', '942 Main St, Westwood', 3, '409285243v', '0771632165', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(61, 'B00061', 'Jessica Garcia', '752 Lake Blvd, Eastdale', 7, '373877251v', '0730086193', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(62, 'B00062', 'Jessica Davis', '318 Oak Ave, Maplewood', 9, '636423293v', '0773686895', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(63, 'B00063', 'David Hernandez', '651 Washington Ave, Rivertown', 1, '142273479v', '0796346412', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(64, 'B00064', 'Elizabeth Williams', '944 Elm St, Westwood', 6, '845793466v', '0738497079', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(65, 'B00065', 'Elizabeth White', '194 Maple Rd, Springfield', 2, '525542182v', '0780089927', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(66, 'B00066', 'David Anderson', '232 Lake Blvd, Eastdale', 17, '463825966v', '0798192260', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(67, 'B00067', 'Susan Martinez', '645 Main St, Southport', 5, '925858314v', '0738866710', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(68, 'B00068', 'Andrew Hernandez', '896 Lake Blvd, Maplewood', 15, '154688592v', '0775129843', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(69, 'B00069', 'Jessica Smith', '825 Pine Dr, Westwood', 15, '595745349v', '0726841449', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(70, 'B00070', 'Susan Wilson', '987 Cedar Ln, Maplewood', 10, '238103336v', '0754351295', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(71, 'B00071', 'Jessica Williams', '724 Maple Rd, Southport', 8, '809057869v', '0770793122', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(72, 'B00072', 'Susan Anderson', '491 Pine Dr, Northfield', 20, '409215519v', '0706215340', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(73, 'B00073', 'Karen Hernandez', '309 Washington Ave, Southport', 16, '678244377v', '0731821959', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(74, 'B00074', 'Andrew Thompson', '754 Main St, Springfield', 8, '176328506v', '0741191478', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(75, 'B00075', 'Linda White', '421 Pine Dr, Eastdale', 11, '271764243v', '0730574302', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(76, 'B00076', 'Jennifer Miller', '736 Pine Dr, Northfield', 19, '505238761v', '0728353552', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(77, 'B00077', 'Jessica Garcia', '199 Lake Blvd, Maplewood', 13, '425091965v', '0717101444', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(78, 'B00078', 'Emily White', '242 Lake Blvd, Springfield', 10, '242289565v', '0777011900', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(79, 'B00079', 'David Martinez', '483 Park Rd, Eastdale', 3, '377166139v', '0726157712', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(80, 'B00080', 'Emily Martin', '841 River Dr, Springfield', 15, '929950354v', '0757021334', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(81, 'B00081', 'Richard Miller', '890 Washington Ave, Oakville', 4, '986834529v', '0735648261', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(82, 'B00082', 'Robert Williams', '431 Lake Blvd, Northfield', 16, '911367886v', '0718117180', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(83, 'B00083', 'Elizabeth Thompson', '629 Pine Dr, Lakeside', 3, '443840521v', '0790090911', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(84, 'B00084', 'James Williams', '14 Cedar Ln, Northfield', 14, '725253543v', '0720238245', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(85, 'B00085', 'Jessica Miller', '402 Lake Blvd, Maplewood', 15, '597101705v', '0798262033', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(86, 'B00086', 'David Jones', '716 River Dr, Hillcrest', 6, '965008586v', '0795460281', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(87, 'B00087', 'William Garcia', '266 Park Rd, Maplewood', 14, '823713269v', '0754418012', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(88, 'B00088', 'Thomas Wilson', '888 Cedar Ln, Oakville', 10, '805684942v', '0772479235', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(89, 'B00089', 'Michael Anderson', '111 Lake Blvd, Westwood', 20, '374226272v', '0784084805', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(90, 'B00090', 'Jane Thompson', '36 Cedar Ln, Oakville', 19, '666049542v', '0772490993', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(91, 'B00091', 'Emily Wilson', '687 Elm St, Eastdale', 19, '927819388v', '0748797128', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(92, 'B00092', 'James Davis', '106 Elm St, Eastdale', 16, '984635041v', '0799018766', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(93, 'B00093', 'Michael Davis', '871 Lake Blvd, Lakeside', 11, '903601815v', '0791457190', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(94, 'B00094', 'Michael Thomas', '37 Park Rd, Springfield', 3, '151893145v', '0784343899', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(95, 'B00095', 'Robert Brown', '405 Lake Blvd, Oakville', 11, '848263055v', '0792560576', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(96, 'B00096', 'Robert Martin', '847 Lake Blvd, Oakville', 9, '350924813v', '0721297781', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(97, 'B00097', 'Michael Jones', '571 Oak Ave, Westwood', 3, '518761081v', '0738087071', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(98, 'B00098', 'Margaret Rodriguez', '457 Elm St, Maplewood', 16, '311116850v', '0799088693', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(99, 'B00099', 'Sarah Williams', '409 River Dr, Northfield', 18, '645527540v', '0783051113', '2025-03-16 08:06:52', '2025-03-16 08:06:52'),
(100, 'B00100', 'Andrew Miller', '745 Maple Rd, Southport', 17, '537532782v', '0798587659', '2025-03-16 08:06:52', '2025-03-16 08:06:52');

-- --------------------------------------------------------

--
-- Table structure for table `membership_numbers`
--

CREATE TABLE `membership_numbers` (
  `id` int(6) UNSIGNED NOT NULL,
  `membership_number` varchar(6) NOT NULL,
  `nic_number` varchar(12) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `membership_numbers`
--

INSERT INTO `membership_numbers` (`id`, `membership_number`, `nic_number`, `created_at`) VALUES
(16, 'C15488', '311116850v', '2025-03-16 14:51:51'),
(17, 'C55319', '105999596v', '2025-03-16 15:00:02'),
(18, 'C49956', '106309868v', '2025-03-16 15:00:13'),
(19, 'C10617', '111249844v', '2025-03-16 15:00:20'),
(20, 'C24515', '121683416v', '2025-03-16 15:00:26'),
(21, 'C83705', '133205915v', '2025-03-16 15:00:33'),
(22, 'C46223', '134450814v', '2025-03-16 15:00:40'),
(23, 'C77137', '142273479v', '2025-03-16 15:00:48'),
(24, 'C41409', '145191180v', '2025-03-16 15:00:56'),
(25, 'C75502', '145262326v', '2025-03-16 15:01:02'),
(26, 'C99359', '151893145v', '2025-03-16 15:01:08'),
(27, 'C93940', '154688592v', '2025-03-16 15:01:14'),
(28, 'C45974', '176328506v', '2025-03-16 15:01:19'),
(29, 'C88281', '185586197v', '2025-03-16 15:01:25'),
(30, 'C69396', '186456754v', '2025-03-16 15:01:32'),
(31, 'C57363', '186914840v', '2025-03-16 15:01:40'),
(32, 'C89947', '238103336v', '2025-03-16 15:02:14'),
(33, 'C77002', '242289565v', '2025-03-16 15:02:21'),
(36, 'C08977', '200187459632', '2025-03-20 03:25:28');

-- --------------------------------------------------------

--
-- Table structure for table `personal_details`
--

CREATE TABLE `personal_details` (
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `gender` varchar(10) NOT NULL,
  `age` int(11) NOT NULL,
  `marital_status` varchar(20) NOT NULL,
  `date_of_birth` date NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `religion` varchar(50) NOT NULL,
  `nic` varchar(50) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `spouse_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `personal_details`
--

INSERT INTO `personal_details` (`full_name`, `email`, `gender`, `age`, `marital_status`, `date_of_birth`, `address`, `religion`, `nic`, `contact_number`, `spouse_name`) VALUES
('Amanda Silva', 'amanda.silva@example.com', 'Female', 25, 'Single', '1999-08-20', 'No. 12, Kandy Road, Kurunegala', 'Christianity', '992345678V', '0712345678', NULL),
('Amandi Rashini', 'amandi@gmail.com', 'Female', 24, 'unmarried', '2001-11-12', 'No. 45, Kalawana Road, Ratnapura', 'Buddhism', '942345678V', '0771234567', '-'),
('Ruwan Fernando', 'ruwan.fernando@example.com', 'Male', 40, 'Married', '1984-02-10', 'No. 88, Beach Road, Negombo', 'Catholic', '842345678V', '0769876543', 'Shanika Fernando');

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `supplier_id` varchar(10) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `nic` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `registration_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`supplier_id`, `supplier_name`, `nic`, `address`, `registration_date`) VALUES
('S00001', 'Unilever Sri Lanka - Mr. Saman Kumara', '200154659726', 'No. 25, Colombo 07, Sri Lanka', '2025-03-20 16:58:56'),
('S00002', 'NestlÃ© Lanka PLC - Mr. Kumara Sampath', '987654321V', 'No. 440, T.B. Jayah Mawatha, Colombo 10, Sri Lanka', '2025-03-20 16:59:30'),
('S00003', 'Prima Ceylon - Mr. Dunith Kumara', '654987321V', 'No. 100, Katunayake, Sri Lanka', '2025-03-20 17:00:01'),
('S00004', 'Kist Products-Mr. Sujeewa Sampath', '789456123V', 'No. 78, Galle Road, Colombo 06, Sri Lanka', '2025-03-20 17:00:34'),
('S00005', 'Maliban Biscuit Manufactories - Mr. fernando', '456123789V', 'No. 45, Homagama, Sri Lanka', '2025-03-20 17:01:14'),
('S00006', 'CBL - Mr. Chamod Gunarathne', '451233789V', 'No. 45, Ratnapura, Sri Lanka', '2025-03-20 17:05:26');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `position` enum('admin','client','accountant') NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `position`, `created_at`, `updated_at`) VALUES
(14, 'amandi rashini', 'amandi@gmail.com', '$2y$10$jxtxoMoKokG2uptPxaXIru0muDZZpoB/g18rMJH5GazerZa0XkKPu', 'admin', '2025-03-20 20:38:02', '2025-03-20 20:38:02'),
(15, 'Amanda Silva', 'amanda.silva@example.com', '$2y$10$80yUBorfA50bOlwaKlMOv.xo68XnLfy7jIJ15sygi4SZ6tITRc0rG', 'accountant', '2025-03-20 20:43:21', '2025-03-20 20:43:21'),
(16, 'Ruwan Fernando', 'ruwan.fernando@example.com', '$2y$10$7TczEYe0QshnscuSIQQ7qOKQGYTpBpl/6IFCOgyyb3741VUFarBm.', 'client', '2025-03-20 20:44:27', '2025-03-20 20:44:27');

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `user_id` int(11) NOT NULL,
  `theme` varchar(20) NOT NULL DEFAULT 'light',
  `language` varchar(10) NOT NULL DEFAULT 'en'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `user_preferences`
--

INSERT INTO `user_preferences` (`user_id`, `theme`, `language`) VALUES
(11, 'dark', 'en');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `education_details`
--
ALTER TABLE `education_details`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `membership_number` (`membership_number`),
  ADD UNIQUE KEY `nic_number` (`nic_number`);

--
-- Indexes for table `membership_numbers`
--
ALTER TABLE `membership_numbers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `membership_number` (`membership_number`);

--
-- Indexes for table `personal_details`
--
ALTER TABLE `personal_details`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`supplier_id`),
  ADD UNIQUE KEY `supplier_nic` (`nic`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `membership_numbers`
--
ALTER TABLE `membership_numbers`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `education_details`
--
ALTER TABLE `education_details`
  ADD CONSTRAINT `education_details_ibfk_1` FOREIGN KEY (`email`) REFERENCES `users` (`email`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `items_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
