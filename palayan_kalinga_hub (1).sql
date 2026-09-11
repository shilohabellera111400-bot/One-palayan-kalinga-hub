-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 02, 2026 at 07:50 AM
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
-- Database: `palayan_kalinga_hub`
--

-- --------------------------------------------------------

--
-- Table structure for table `aics_cash_relief_details`
--

CREATE TABLE `aics_cash_relief_details` (
  `cash_relief_id` int(11) NOT NULL,
  `aics_id` int(11) DEFAULT NULL,
  `emergency_type` enum('medical','disaster','accident','crime','financial','other') NOT NULL,
  `affected_members` int(11) DEFAULT 1,
  `incident_date` date DEFAULT NULL,
  `incident_report_no` varchar(100) DEFAULT NULL,
  `police_report_no` varchar(100) DEFAULT NULL,
  `purpose` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aics_cash_relief_details`
--

INSERT INTO `aics_cash_relief_details` (`cash_relief_id`, `aics_id`, `emergency_type`, `affected_members`, `incident_date`, `incident_report_no`, `police_report_no`, `purpose`) VALUES
(1, 14, 'disaster', 13, '0000-00-00', '', '', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `aics_categories`
--

CREATE TABLE `aics_categories` (
  `aics_category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aics_categories`
--

INSERT INTO `aics_categories` (`aics_category_id`, `category_name`, `description`, `is_active`) VALUES
(1, 'Medical Assistance', 'Financial assistance for hospital bills, medicines, laboratory tests, and medical procedures', 1),
(2, 'Funeral/Burial Assistance', 'Financial support for funeral services and burial expenses', 1),
(3, 'Educational Assistance', 'Support for school supplies, tuition fees, and educational needs', 1),
(4, 'Transportation Assistance', 'Help with transportation expenses for medical appointments or urgent needs', 1),
(5, 'Food Assistance', 'Food packs and grocery items for families in crisis', 1),
(6, 'Cash Relief Assistance', 'Financial assistance for individuals and families in crisis', 1),
(7, 'Material Assistance', 'Non-food items, hygiene kits, and other material support', 1);

-- --------------------------------------------------------

--
-- Table structure for table `aics_documents`
--

CREATE TABLE `aics_documents` (
  `aics_doc_id` int(11) NOT NULL,
  `aics_id` int(11) DEFAULT NULL,
  `document_type` varchar(100) DEFAULT NULL,
  `document_name` varchar(200) DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aics_documents`
--

INSERT INTO `aics_documents` (`aics_doc_id`, `aics_id`, `document_type`, `document_name`, `document_path`, `upload_date`) VALUES
(1, 2, 'supporting_document', NULL, 'uploads/aics/69ba08dee6635_1773799646.png', '2026-03-18 02:07:26'),
(2, 3, 'supporting_document', NULL, 'uploads/aics/69ba115854de6_1773801816.png', '2026-03-18 02:43:36'),
(4, 7, 'valid_id', 'Valid ID', 'uploads/aics/69dca8d7602ee_1776068823.jpg', '2026-04-13 08:27:03'),
(5, 7, 'authorization_letter', 'Authorization Letter', 'uploads/aics/69dca8d761dfa_1776068823.jpg', '2026-04-13 08:27:03'),
(6, 7, 'death_certificate', 'Death Certificate', 'uploads/aics/69dca8d76264c_1776068823.jpg', '2026-04-13 08:27:03'),
(7, 7, 'promissory_note', 'Promissory Note', 'uploads/aics/69dca8d7631e3_1776068823.jpg', '2026-04-13 08:27:03'),
(8, 7, 'funeral_contract', 'Funeral Contract', 'uploads/aics/69dca8d7639ad_1776068823.jpg', '2026-04-13 08:27:03'),
(9, 12, 'valid_id', 'Valid ID', 'uploads/aics/69dcac1c32559_1776069660.jpg', '2026-04-13 08:41:00'),
(10, 12, 'authorization_letter', 'Authorization Letter', 'uploads/aics/69dcac1c3302b_1776069660.jpg', '2026-04-13 08:41:00'),
(11, 12, 'school_id', 'Validated School ID', 'uploads/aics/69dcac1c33883_1776069660.jpg', '2026-04-13 08:41:00'),
(12, 12, 'enrollment_form', 'Enrollment Assessment Form', 'uploads/aics/69dcac1c340dd_1776069660.jpg', '2026-04-13 08:41:00'),
(13, 12, 'statement_of_account', 'Statement of Account', 'uploads/aics/69dcac1c349cf_1776069660.jpg', '2026-04-13 08:41:00'),
(14, 14, 'valid_id', 'Valid ID', 'uploads/aics/69dcadc89b17e_1776070088.jpg', '2026-04-13 08:48:08'),
(15, 14, 'barangay_certificate', 'Barangay Certificate', 'uploads/aics/69dcadc89cc95_1776070088.jpg', '2026-04-13 08:48:08'),
(16, 16, 'valid_id', 'Valid ID', 'uploads/aics/69dcf456b4108_1776088150.jpg', '2026-04-13 13:49:10'),
(17, 16, 'authorization_letter', 'Authorization Letter', 'uploads/aics/69dcf456b4c4d_1776088150.jpg', '2026-04-13 13:49:10'),
(18, 16, 'court_order', 'Court Order or Subpoena', 'uploads/aics/69dcf456b5469_1776088150.jpg', '2026-04-13 13:49:10'),
(19, 17, 'valid_id', 'Valid ID', 'uploads/aics/69dcf556ca267_1776088406.jpg', '2026-04-13 13:53:26'),
(20, 17, 'barangay_certificate', 'Barangay Certificate', 'uploads/aics/69dcf556cabe9_1776088406.jpg', '2026-04-13 13:53:26'),
(21, 17, 'proof_of_income', 'Proof of Income', 'uploads/aics/69dcf556cb475_1776088406.jpg', '2026-04-13 13:53:26'),
(22, 17, 'house_photo', 'House Photo', 'uploads/aics/69dcf556cbcfe_1776088406.jpg', '2026-04-13 13:53:26'),
(23, 18, 'general_intake', 'General Intake Sheet (GIS)', 'uploads/aics/69dcf66a08888_1776088682.jpg', '2026-04-13 13:58:02'),
(24, 18, 'material_sheet', 'Material Distribution Sheet (MDS)', 'uploads/aics/69dcf66a091fb_1776088682.jpg', '2026-04-13 13:58:02'),
(25, 19, 'valid_id', 'Valid ID', 'uploads/aics/69dda04bf36f0_1776132171.png', '2026-04-14 02:02:52'),
(26, 19, 'authorization_letter', 'Authorization Letter', 'uploads/aics/69dda04c02600_1776132172.png', '2026-04-14 02:02:52'),
(27, 19, 'school_id', 'Validated School ID', 'uploads/aics/69dda04c02e97_1776132172.png', '2026-04-14 02:02:52'),
(28, 19, 'enrollment_form', 'Enrollment Assessment Form', 'uploads/aics/69dda04c035fe_1776132172.png', '2026-04-14 02:02:52'),
(29, 19, 'statement_of_account', 'Statement of Account', 'uploads/aics/69dda04c03c3c_1776132172.png', '2026-04-14 02:02:52'),
(30, 20, 'valid_id', 'Valid ID', 'uploads/aics/69df3379ef0b1_1776235385.png', '2026-04-15 06:43:05'),
(31, 20, 'barangay_certificate', 'Barangay Certificate', 'uploads/aics/69df3379f1a29_1776235385.png', '2026-04-15 06:43:05'),
(32, 20, 'proof_of_income', 'Proof of Income', 'uploads/aics/69df3379f2110_1776235385.png', '2026-04-15 06:43:05'),
(33, 20, 'house_photo', 'House Photo', 'uploads/aics/69df3379f26fd_1776235385.png', '2026-04-15 06:43:05');

-- --------------------------------------------------------

--
-- Table structure for table `aics_document_requirements`
--

CREATE TABLE `aics_document_requirements` (
  `req_id` int(11) NOT NULL,
  `request_type` enum('medical','funeral','educational','transportation','food','cash_relief','material') NOT NULL,
  `document_code` varchar(50) NOT NULL,
  `document_name` varchar(100) NOT NULL,
  `is_required` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aics_document_requirements`
--

INSERT INTO `aics_document_requirements` (`req_id`, `request_type`, `document_code`, `document_name`, `is_required`, `description`, `sort_order`) VALUES
(1, 'medical', 'valid_id', 'Valid ID', 1, NULL, 0),
(2, 'medical', 'medical_certificate', 'Medical Certificate / Clinical Abstract', 1, NULL, 0),
(3, 'medical', 'hospital_bill', 'Hospital Bill / Statement of Account', 0, NULL, 0),
(4, 'medical', 'prescription', 'Doctor\'s Prescription', 0, NULL, 0),
(5, 'medical', 'laboratory_request', 'Laboratory Request', 0, NULL, 0),
(6, 'medical', 'quotation', 'Quotation (for amount > ₱10,000)', 0, NULL, 0),
(7, 'funeral', 'valid_id', 'Valid ID', 1, NULL, 0),
(8, 'funeral', 'death_certificate', 'Death Certificate', 1, NULL, 0),
(9, 'funeral', 'funeral_contract', 'Funeral Contract', 0, NULL, 0),
(10, 'funeral', 'promissory_note', 'Promissory Note / Certificate of Balance', 0, NULL, 0),
(11, 'funeral', 'transfer_permit', 'Transfer Permit', 0, NULL, 0),
(12, 'educational', 'valid_id', 'Valid ID (Parent/Guardian)', 1, NULL, 0),
(13, 'educational', 'school_id', 'Validated School ID', 1, NULL, 0),
(14, 'educational', 'enrollment_form', 'Enrollment Assessment Form', 1, NULL, 0),
(15, 'educational', 'statement_of_account', 'Statement of Account', 0, NULL, 0),
(16, 'transportation', 'valid_id', 'Valid ID', 1, NULL, 0),
(17, 'transportation', 'medical_certificate', 'Medical Certificate', 0, NULL, 0),
(18, 'transportation', 'death_certificate', 'Death Certificate', 0, NULL, 0),
(19, 'transportation', 'court_order', 'Court Order / Subpoena', 0, NULL, 0),
(20, 'food', 'valid_id', 'Valid ID', 1, NULL, 0),
(21, 'food', 'barangay_certificate', 'Barangay Certificate', 1, NULL, 0),
(22, 'food', 'proof_of_income', 'Proof of Income', 0, NULL, 0),
(23, 'cash_relief', 'valid_id', 'Valid ID', 1, NULL, 0),
(24, 'cash_relief', 'barangay_certificate', 'Barangay Certificate', 1, NULL, 0),
(25, 'cash_relief', 'incident_report', 'Incident Report', 0, NULL, 0),
(26, 'cash_relief', 'medical_certificate', 'Medical Certificate', 0, NULL, 0),
(27, 'cash_relief', 'police_report', 'Police Report', 0, NULL, 0),
(28, 'material', 'general_intake', 'General Intake Sheet (GIS)', 1, NULL, 0),
(29, 'material', 'material_sheet', 'Material Distribution Sheet (MDS)', 1, NULL, 0),
(30, 'material', 'case_summary', 'Case Summary', 0, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `aics_educational_details`
--

CREATE TABLE `aics_educational_details` (
  `educational_id` int(11) NOT NULL,
  `aics_id` int(11) DEFAULT NULL,
  `student_name` varchar(100) NOT NULL,
  `school_name` varchar(200) NOT NULL,
  `grade_level` varchar(50) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `enrollment_status` enum('enrolled','pending','graduate') DEFAULT 'enrolled',
  `purpose` text DEFAULT NULL,
  `tuition_fee` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aics_educational_details`
--

INSERT INTO `aics_educational_details` (`educational_id`, `aics_id`, `student_name`, `school_name`, `grade_level`, `school_year`, `enrollment_status`, `purpose`, `tuition_fee`) VALUES
(1, 12, 'vfdvr', 'dvfdv', 'Kindergarten', '2024-2025', 'enrolled', 'grdbdtg', 2000.00),
(2, 19, 'Ashiro', 'NEUST', '3rd Year College', '2025-2026', 'enrolled', 'just need it', 800.00);

-- --------------------------------------------------------

--
-- Table structure for table `aics_food_details`
--

CREATE TABLE `aics_food_details` (
  `food_id` int(11) NOT NULL,
  `aics_id` int(11) DEFAULT NULL,
  `household_size` int(11) NOT NULL,
  `monthly_income` decimal(10,2) DEFAULT NULL,
  `special_needs` text DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `food_pack_type` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aics_food_details`
--

INSERT INTO `aics_food_details` (`food_id`, `aics_id`, `household_size`, `monthly_income`, `special_needs`, `purpose`, `food_pack_type`) VALUES
(1, 17, 12, 2000.00, 'awdwad', 'awdaw', NULL),
(2, 20, 18, 5000.00, 'may pilay', 'Batugan ang kasama', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `aics_funeral_details`
--

CREATE TABLE `aics_funeral_details` (
  `funeral_id` int(11) NOT NULL,
  `aics_id` int(11) DEFAULT NULL,
  `assistance_type` enum('funeral_bill','transfer_assistance') NOT NULL,
  `deceased_name` varchar(100) NOT NULL,
  `relationship` varchar(50) NOT NULL,
  `funeral_home` varchar(200) DEFAULT NULL,
  `death_certificate_no` varchar(100) DEFAULT NULL,
  `funeral_contract_no` varchar(100) DEFAULT NULL,
  `transfer_permit_no` varchar(100) DEFAULT NULL,
  `purpose` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aics_funeral_details`
--

INSERT INTO `aics_funeral_details` (`funeral_id`, `aics_id`, `assistance_type`, `deceased_name`, `relationship`, `funeral_home`, `death_certificate_no`, `funeral_contract_no`, `transfer_permit_no`, `purpose`) VALUES
(4, 7, 'funeral_bill', 'nkeskjfew', 'rmkrlkre', 'rnrnrgl', 'frgrdg', 'tfthth', '', 'dgrgrdgrd');

-- --------------------------------------------------------

--
-- Table structure for table `aics_material_details`
--

CREATE TABLE `aics_material_details` (
  `material_id` int(11) NOT NULL,
  `aics_id` int(11) DEFAULT NULL,
  `client_type` enum('individual','group') NOT NULL,
  `group_name` varchar(100) DEFAULT NULL,
  `number_of_members` int(11) DEFAULT NULL,
  `materials_requested` text DEFAULT NULL,
  `estimated_value` decimal(10,2) DEFAULT NULL,
  `purpose` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aics_material_details`
--

INSERT INTO `aics_material_details` (`material_id`, `aics_id`, `client_type`, `group_name`, `number_of_members`, `materials_requested`, `estimated_value`, `purpose`) VALUES
(1, 18, 'individual', '', 0, 'sfesf', 2000.00, 'fadawd');

-- --------------------------------------------------------

--
-- Table structure for table `aics_medical_details`
--

CREATE TABLE `aics_medical_details` (
  `medical_id` int(11) NOT NULL,
  `aics_id` int(11) DEFAULT NULL,
  `assistance_type` enum('hospital_bill','medicine','laboratory') NOT NULL,
  `hospital_name` varchar(200) DEFAULT NULL,
  `doctor_name` varchar(100) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `prescription_details` text DEFAULT NULL,
  `laboratory_request` text DEFAULT NULL,
  `quotation_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `aics_requests`
--

CREATE TABLE `aics_requests` (
  `aics_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `request_type` enum('medical','funeral','educational','transportation','food','cash_relief','material') NOT NULL,
  `amount_requested` decimal(10,2) DEFAULT NULL,
  `amount_approved` decimal(10,2) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `status` enum('submitted','reviewing','approved','rejected','released') DEFAULT 'submitted',
  `submission_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `review_date` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `release_date` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aics_requests`
--

INSERT INTO `aics_requests` (`aics_id`, `user_id`, `request_type`, `amount_requested`, `amount_approved`, `purpose`, `status`, `submission_date`, `reviewed_by`, `review_date`, `approved_by`, `approval_date`, `release_date`, `rejection_reason`) VALUES
(1, 1, 'medical', 5000.00, NULL, NULL, 'submitted', '2026-03-03 05:28:36', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 1, 'medical', 500.00, NULL, NULL, 'submitted', '2026-03-18 02:07:26', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 3, 'medical', 100000.00, NULL, NULL, 'submitted', '2026-03-18 02:43:36', NULL, NULL, NULL, NULL, NULL, NULL),
(7, 1, 'funeral', 2000.00, NULL, NULL, 'submitted', '2026-04-13 08:27:03', NULL, NULL, NULL, NULL, NULL, NULL),
(12, 1, 'educational', 1900.00, NULL, NULL, 'rejected', '2026-04-13 08:41:00', NULL, NULL, NULL, NULL, NULL, 'Cancelled by applicant'),
(14, 1, 'cash_relief', 1200.00, NULL, NULL, 'rejected', '2026-04-13 08:48:08', NULL, NULL, NULL, NULL, NULL, 'Cancelled by applicant'),
(16, 1, 'transportation', 2000.00, NULL, NULL, 'rejected', '2026-04-13 13:49:10', NULL, NULL, NULL, NULL, NULL, 'Cancelled by applicant'),
(17, 1, 'food', 2000.00, 300.00, NULL, 'approved', '2026-04-13 13:53:26', 15, '2026-04-15 13:34:14', 15, '2026-04-15 13:35:04', NULL, NULL),
(18, 1, 'material', 2000.00, 0.00, NULL, 'approved', '2026-04-13 13:58:02', NULL, NULL, 27, '2026-04-16 09:01:00', NULL, NULL),
(19, 14, 'educational', 1000.00, 1000.00, NULL, 'approved', '2026-04-14 02:02:51', NULL, NULL, 15, '2026-04-14 10:05:16', NULL, NULL),
(20, 20, 'food', 5000.00, NULL, NULL, 'submitted', '2026-04-15 06:43:05', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `aics_requests_backup`
--

CREATE TABLE `aics_requests_backup` (
  `aics_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `request_type` enum('medical','financial','burial','food','transportation','other') NOT NULL,
  `amount_requested` decimal(10,2) DEFAULT NULL,
  `amount_approved` decimal(10,2) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `status` enum('submitted','reviewing','approved','rejected','released') DEFAULT 'submitted',
  `submission_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `review_date` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `release_date` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aics_requests_backup`
--

INSERT INTO `aics_requests_backup` (`aics_id`, `user_id`, `request_type`, `amount_requested`, `amount_approved`, `purpose`, `status`, `submission_date`, `reviewed_by`, `review_date`, `approved_by`, `approval_date`, `release_date`, `rejection_reason`) VALUES
(1, 1, 'medical', 5000.00, NULL, 'nakagat po ng aso', 'submitted', '2026-03-03 05:28:36', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 1, 'medical', 500.00, NULL, 'gfh', 'submitted', '2026-03-18 02:07:26', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 3, 'medical', 100000.00, NULL, 'inoperahan po tatay ko huhuhuhuhu', 'submitted', '2026-03-18 02:43:36', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `aics_transportation_details`
--

CREATE TABLE `aics_transportation_details` (
  `transportation_id` int(11) NOT NULL,
  `aics_id` int(11) DEFAULT NULL,
  `reason_type` enum('medical','legal','funeral','emergency','other') NOT NULL,
  `destination` varchar(200) NOT NULL,
  `travel_date` date NOT NULL,
  `number_of_persons` int(11) DEFAULT 1,
  `estimated_fare` decimal(10,2) DEFAULT NULL,
  `purpose` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aics_transportation_details`
--

INSERT INTO `aics_transportation_details` (`transportation_id`, `aics_id`, `reason_type`, `destination`, `travel_date`, `number_of_persons`, `estimated_fare`, `purpose`) VALUES
(1, 16, 'legal', 'philippines', '2026-04-14', 1, 2000.00, 'awdawdwa');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `service_type` varchar(100) DEFAULT 'General',
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `purpose` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `user_id`, `service_id`, `appointment_date`, `appointment_time`, `service_type`, `status`, `purpose`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2026-04-02', '10:00:00', 'General', 'cancelled', '', NULL, '2026-03-03 05:13:07', '2026-03-18 01:52:41'),
(2, 1, 5, '2026-03-09', '09:00:00', 'General', 'cancelled', 'application', NULL, '2026-03-09 01:47:37', '2026-03-16 04:52:52'),
(3, 1, 4, '2026-03-18', '09:00:00', 'General', 'cancelled', 'wejr', '\n[Staff Update] ', '2026-03-18 02:00:20', '2026-03-18 02:19:26'),
(4, 1, 1, '2026-03-23', '10:00:00', 'General', 'cancelled', 'uiuio', NULL, '2026-03-18 02:04:21', '2026-03-30 13:27:28'),
(5, 14, 14, '2026-04-12', '09:00:00', 'General', 'pending', 'just that', NULL, '2026-04-12 03:10:32', '2026-04-12 03:10:32'),
(6, 1, 5, '2026-04-16', '13:00:00', 'General', 'pending', 'SISISI', NULL, '2026-04-13 07:14:56', '2026-04-13 07:14:56'),
(7, 1, 2, '2026-04-14', '09:00:00', 'General', 'confirmed', 'ehhehe', '\n[Staff Update] sigee\n[Staff Update] awd', '2026-04-13 07:31:33', '2026-04-13 07:48:01'),
(8, 1, 11, '2026-04-15', '11:00:00', 'General', 'cancelled', 'wdwd', NULL, '2026-04-13 07:32:16', '2026-07-01 05:07:00'),
(9, 1, 16, '2026-04-27', '13:00:00', 'General', 'cancelled', 'wadawd', NULL, '2026-04-13 07:45:08', '2026-07-02 05:30:43'),
(10, 1, 13, '2026-04-14', '10:00:00', 'General', 'cancelled', 'awdad', NULL, '2026-04-13 07:46:07', '2026-04-14 13:03:15'),
(11, 1, 13, '2026-04-15', '15:00:00', 'General', 'pending', 'wda', NULL, '2026-04-13 07:53:36', '2026-04-13 07:53:36'),
(12, 1, 11, '2026-04-14', '10:00:00', 'General', 'cancelled', 'fafaw', NULL, '2026-04-13 07:55:53', '2026-04-14 13:10:11'),
(13, 1, 9, '2026-04-15', '10:00:00', 'General', 'pending', 'nefneflef', NULL, '2026-04-14 03:33:33', '2026-04-14 03:33:33'),
(14, 1, 16, '2026-04-23', '15:00:00', 'General', 'pending', 'Penge bahay hehe', NULL, '2026-04-15 05:47:46', '2026-04-15 05:47:46'),
(15, 20, 6, '2026-05-08', '09:00:00', 'General', 'cancelled', 'Needd', NULL, '2026-04-15 06:37:24', '2026-04-16 01:04:44'),
(16, 20, 10, '2026-04-20', '13:00:00', 'General', 'confirmed', '', NULL, '2026-04-16 05:25:29', '2026-04-16 05:28:45'),
(17, 14, 15, '2026-04-20', '11:00:00', 'General', 'pending', '', NULL, '2026-04-16 06:38:44', '2026-04-16 06:38:44'),
(18, 1, 12, '2026-07-31', '10:00:00', 'General', 'cancelled', 'awdawd', NULL, '2026-07-01 07:36:51', '2026-07-02 02:03:15');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `table_affected` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_agent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `table_affected`, `record_id`, `old_values`, `new_values`, `ip_address`, `created_at`, `user_agent`) VALUES
(1, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-03 04:57:47', NULL),
(2, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-03 05:34:20', NULL),
(3, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-03 05:44:16', NULL),
(4, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-03 06:21:41', NULL),
(5, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-03 06:30:18', NULL),
(6, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-03 06:30:28', NULL),
(7, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-03 06:31:12', NULL),
(8, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-03 06:31:22', NULL),
(9, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-03 06:35:03', NULL),
(10, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-03 06:35:13', NULL),
(11, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-03 06:43:00', NULL),
(12, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-03 06:46:29', NULL),
(13, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-03 06:46:40', NULL),
(14, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-03 06:49:20', NULL),
(15, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-03 07:10:05', NULL),
(16, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-03 07:13:59', NULL),
(17, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-03 07:39:42', NULL),
(18, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-03 07:41:16', NULL),
(19, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-03 07:41:48', NULL),
(20, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-03 09:08:53', NULL),
(21, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-03 09:14:59', NULL),
(22, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-03 09:15:10', NULL),
(23, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-03 09:18:57', NULL),
(24, 3, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-03 09:19:16', NULL),
(25, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-03 09:19:28', NULL),
(26, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-03 09:19:40', NULL),
(27, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-09 01:40:13', NULL),
(28, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-09 01:47:48', NULL),
(29, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-09 01:48:04', NULL),
(30, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-09 01:52:25', NULL),
(31, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-09 02:20:51', NULL),
(32, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-09 02:21:53', NULL),
(33, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-09 02:22:23', NULL),
(34, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-16 03:25:34', NULL),
(35, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-16 03:28:40', NULL),
(36, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-16 03:28:53', NULL),
(37, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-16 03:33:47', NULL),
(38, 4, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-16 03:39:46', NULL),
(39, 4, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-16 03:41:45', NULL),
(40, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-16 03:42:08', NULL),
(41, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-16 03:44:59', NULL),
(42, 4, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-16 03:45:20', NULL),
(43, 4, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-16 03:48:27', NULL),
(44, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-16 03:50:37', NULL),
(45, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-16 03:51:32', NULL),
(46, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-16 03:51:46', NULL),
(47, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-16 04:47:34', NULL),
(48, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-16 04:47:48', NULL),
(49, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-16 06:55:56', NULL),
(50, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-16 06:56:16', NULL),
(51, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-16 07:01:26', NULL),
(52, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-16 07:01:37', NULL),
(53, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-18 01:48:09', NULL),
(54, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-18 01:48:14', NULL),
(55, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-18 01:48:22', NULL),
(56, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-18 01:49:54', NULL),
(57, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-18 01:50:48', NULL),
(58, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-18 01:53:20', NULL),
(59, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-18 01:56:24', NULL),
(60, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-18 02:10:51', NULL),
(61, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-18 02:11:00', NULL),
(62, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-18 02:29:25', NULL),
(63, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-18 02:30:27', NULL),
(64, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-18 02:57:27', NULL),
(65, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-19 04:34:03', NULL),
(66, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-19 07:09:45', NULL),
(67, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-19 07:09:58', NULL),
(68, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-19 07:12:43', NULL),
(69, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-19 07:13:14', NULL),
(70, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-19 07:13:38', NULL),
(71, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-19 07:13:48', NULL),
(72, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-19 07:14:27', NULL),
(73, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-19 07:16:22', NULL),
(74, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-19 07:27:02', NULL),
(75, 5, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-19 07:29:22', NULL),
(76, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-30 10:07:01', NULL),
(77, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-30 10:07:50', NULL),
(78, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-30 10:19:57', NULL),
(79, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-30 10:20:54', NULL),
(80, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-03-30 10:21:09', NULL),
(81, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-30 10:21:46', NULL),
(82, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-30 10:22:03', NULL),
(83, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-30 10:23:28', NULL),
(84, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-30 12:19:18', NULL),
(85, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-03-30 13:44:51', NULL),
(86, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-30 13:45:24', NULL),
(87, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-03-31 12:54:14', NULL),
(88, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-06 01:50:51', NULL),
(89, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-06 01:52:40', NULL),
(90, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-06 01:52:53', NULL),
(91, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-06 01:56:05', NULL),
(92, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-06 04:08:04', NULL),
(93, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-06 04:12:36', NULL),
(94, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-06 04:12:44', NULL),
(95, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-06 04:13:16', NULL),
(96, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-06 05:51:29', NULL),
(97, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-06 06:08:45', NULL),
(98, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-07 05:41:49', NULL),
(99, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-07 06:25:54', NULL),
(100, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-07 06:27:36', NULL),
(101, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-07 06:31:13', NULL),
(102, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-07 06:32:09', NULL),
(103, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-07 06:34:50', NULL),
(104, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-07 06:36:35', NULL),
(105, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-07 06:37:00', NULL),
(106, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-07 06:37:18', NULL),
(107, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-07 06:43:13', NULL),
(108, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-07 06:43:28', NULL),
(109, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-07 06:44:02', NULL),
(110, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-07 06:44:11', NULL),
(111, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-07 06:52:19', NULL),
(112, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-07 06:56:15', NULL),
(113, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-07 06:58:49', NULL),
(114, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-07 06:58:58', NULL),
(115, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-07 07:00:07', NULL),
(116, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-07 07:00:14', NULL),
(117, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-07 07:08:53', NULL),
(118, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-07 07:09:06', NULL),
(119, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-07 07:09:46', NULL),
(120, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-07 07:20:26', NULL),
(121, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-07 07:40:36', NULL),
(122, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-07 07:49:56', NULL),
(123, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-07 08:16:09', NULL),
(124, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-07 08:16:27', NULL),
(125, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-07 08:16:53', NULL),
(126, 6, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-07 08:19:37', NULL),
(127, 6, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-07 08:19:44', NULL),
(128, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 00:11:28', NULL),
(129, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 00:11:42', NULL),
(130, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 00:18:00', NULL),
(131, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 00:18:08', NULL),
(132, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 00:18:30', NULL),
(133, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 00:54:57', NULL),
(134, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 00:57:51', NULL),
(135, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 00:58:00', NULL),
(136, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 01:02:40', NULL),
(137, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 01:02:46', NULL),
(138, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 01:03:20', NULL),
(139, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 01:04:27', NULL),
(140, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 01:07:04', NULL),
(141, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 01:07:13', NULL),
(142, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 01:08:21', NULL),
(143, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 01:08:26', NULL),
(144, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 01:12:26', NULL),
(145, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 01:13:04', NULL),
(146, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 02:04:04', NULL),
(147, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 02:04:20', NULL),
(148, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 02:13:27', NULL),
(149, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 02:13:37', NULL),
(150, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 02:14:05', NULL),
(151, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 02:23:11', NULL),
(152, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 02:33:27', NULL),
(153, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 02:46:45', NULL),
(154, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 02:46:59', NULL),
(155, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 02:47:08', NULL),
(156, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 02:52:22', NULL),
(157, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 02:52:25', NULL),
(158, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 02:54:56', NULL),
(159, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 02:54:58', NULL),
(160, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:04:00', NULL),
(161, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:04:42', NULL),
(162, 11, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:05:46', NULL),
(163, 11, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:06:13', NULL),
(164, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:06:19', NULL),
(165, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:06:29', NULL),
(166, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:08:41', NULL),
(167, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:08:57', NULL),
(168, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:09:08', NULL),
(169, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:13:25', NULL),
(170, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:13:51', NULL),
(171, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:13:54', NULL),
(172, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:14:01', NULL),
(173, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:14:03', NULL),
(174, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:14:10', NULL),
(175, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:14:13', NULL),
(176, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:19:38', NULL),
(177, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:20:49', NULL),
(178, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:20:58', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(179, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:21:10', NULL),
(180, 1, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:21:20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(181, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:21:23', NULL),
(182, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:21:36', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(183, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:26:33', NULL),
(184, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:27:03', NULL),
(185, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:27:06', NULL),
(186, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:31:06', NULL),
(187, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:31:17', NULL),
(188, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:31:29', NULL),
(189, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:31:31', NULL),
(190, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:31:55', NULL),
(191, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:31:57', NULL),
(192, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:37:10', NULL),
(193, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:37:20', NULL),
(194, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:37:33', NULL),
(195, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:37:40', NULL),
(196, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:40:35', NULL),
(197, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:44:31', NULL),
(198, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:46:27', NULL),
(199, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:46:56', NULL),
(200, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:57:48', NULL),
(203, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 03:58:40', NULL),
(204, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 04:07:34', NULL),
(205, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 04:07:43', NULL),
(206, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 04:13:51', NULL),
(209, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 23:28:19', NULL),
(210, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 23:33:22', NULL),
(211, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 23:37:01', NULL),
(212, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 23:37:08', NULL),
(213, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-08 23:37:51', NULL),
(214, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-08 23:38:45', NULL),
(215, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-11 15:33:49', NULL),
(216, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-11 15:54:40', NULL),
(217, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-11 15:55:05', NULL),
(218, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-11 16:15:52', NULL),
(219, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-11 16:17:46', NULL),
(220, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-11 16:19:20', NULL),
(221, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-11 17:19:53', NULL),
(222, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-11 17:55:37', NULL),
(223, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-11 17:58:55', NULL),
(224, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-11 19:03:31', NULL),
(225, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-11 19:06:23', NULL),
(226, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-11 19:36:19', NULL),
(227, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-11 19:36:46', NULL),
(228, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-11 20:08:28', NULL),
(229, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-11 20:13:55', NULL),
(230, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-11 20:16:47', NULL),
(231, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-11 20:18:40', NULL),
(232, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-11 20:19:58', NULL),
(233, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-11 20:20:42', NULL),
(234, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-11 20:21:07', NULL),
(235, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-11 20:23:51', NULL),
(236, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-12 02:51:19', NULL),
(237, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 06:54:58', NULL),
(238, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 07:00:06', NULL),
(239, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 07:00:15', NULL),
(240, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 07:01:10', NULL),
(241, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 07:14:15', NULL),
(242, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 07:15:21', NULL),
(243, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 07:15:34', NULL),
(244, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 07:16:17', NULL),
(245, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 07:16:25', NULL),
(246, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 07:26:53', NULL),
(247, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 07:30:47', NULL),
(248, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 07:47:10', NULL),
(249, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 07:47:26', NULL),
(250, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 07:48:28', NULL),
(251, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 07:48:37', NULL),
(252, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 08:48:23', NULL),
(253, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 08:49:53', NULL),
(254, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 09:25:19', NULL),
(255, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 13:40:32', NULL),
(256, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 13:59:39', NULL),
(257, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 14:00:17', NULL),
(258, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 14:02:03', NULL),
(259, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 14:02:11', NULL),
(260, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 14:04:20', NULL),
(261, 15, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 14:04:41', NULL),
(262, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 14:04:58', NULL),
(263, 15, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 14:09:22', NULL),
(264, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 14:24:49', NULL),
(265, 15, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 14:25:02', NULL),
(266, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 14:27:36', NULL),
(267, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 14:27:47', NULL),
(268, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 14:32:12', NULL),
(269, 3, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 14:32:20', NULL),
(270, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 14:32:34', NULL),
(271, 15, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 14:32:52', NULL),
(272, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 14:38:27', NULL),
(273, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 14:38:34', NULL),
(274, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 15:07:12', NULL),
(275, 15, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 15:07:28', NULL),
(276, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 15:07:43', NULL),
(277, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 15:07:52', NULL),
(278, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 15:42:52', NULL),
(279, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 15:43:03', NULL),
(280, 15, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 15:50:57', NULL),
(281, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 16:55:12', NULL),
(282, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 16:55:24', NULL),
(283, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 16:55:33', NULL),
(284, 15, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 16:55:50', NULL),
(285, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 17:28:12', NULL),
(286, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 17:28:26', NULL),
(287, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 17:28:50', NULL),
(288, 15, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 17:29:02', NULL),
(289, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 17:40:39', NULL),
(290, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 23:23:54', NULL),
(291, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 23:48:42', NULL),
(292, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 23:50:25', NULL),
(293, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 23:57:06', NULL),
(294, 15, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 23:57:15', NULL),
(295, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-13 23:58:43', NULL),
(296, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-13 23:58:50', NULL),
(297, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 00:05:17', NULL),
(298, 15, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 00:05:37', NULL),
(299, 15, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 00:54:00', NULL),
(300, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 00:56:01', NULL),
(301, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 01:39:53', NULL),
(302, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 01:48:07', NULL),
(303, 15, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 01:48:59', NULL),
(304, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 01:54:47', NULL),
(305, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 01:55:19', NULL),
(306, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 01:58:02', NULL),
(307, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 01:58:23', NULL),
(308, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 02:03:51', NULL),
(309, 15, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 02:04:19', NULL),
(310, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 02:12:50', NULL),
(311, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 02:28:24', NULL),
(312, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 02:28:39', NULL),
(313, 15, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 02:28:53', NULL),
(314, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 02:29:24', NULL),
(315, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 02:29:40', NULL),
(316, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 02:31:06', NULL),
(317, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 02:32:40', NULL),
(318, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 02:47:36', NULL),
(319, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 02:48:15', NULL),
(320, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 02:48:27', NULL),
(321, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 02:51:13', NULL),
(322, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 02:57:11', NULL),
(323, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 03:00:41', NULL),
(324, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 03:03:40', NULL),
(325, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 03:04:46', NULL),
(326, 15, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 03:04:55', NULL),
(327, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 03:05:56', NULL),
(328, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 03:06:08', NULL),
(329, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 03:06:36', NULL),
(330, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 03:32:53', NULL),
(331, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 03:38:19', NULL),
(332, 15, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 03:38:40', NULL),
(333, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 03:41:43', NULL),
(334, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 03:41:50', NULL),
(335, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 06:53:12', NULL),
(336, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 06:53:24', NULL),
(337, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 07:09:27', NULL),
(338, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 07:30:00', NULL),
(339, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 08:17:55', NULL),
(340, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 12:51:59', NULL),
(341, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 13:28:22', NULL),
(342, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 13:28:32', NULL),
(343, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 13:51:05', NULL),
(344, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 13:51:16', NULL),
(345, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 13:53:56', NULL),
(346, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 13:54:03', NULL),
(347, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 13:58:41', NULL),
(348, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 13:59:02', NULL),
(349, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 14:00:15', NULL),
(350, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 14:00:22', NULL),
(351, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 14:01:24', NULL),
(352, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 14:04:37', NULL),
(353, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 14:07:07', NULL),
(354, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 14:07:35', NULL),
(355, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 14:32:11', NULL),
(356, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 14:32:37', NULL),
(357, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 14:33:40', NULL),
(358, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 14:33:49', NULL),
(359, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 14:45:12', NULL),
(360, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 14:45:29', NULL),
(361, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 14:49:45', NULL),
(362, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 14:49:57', NULL),
(363, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 14:50:07', NULL),
(364, 15, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 14:50:18', NULL),
(365, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 15:03:58', NULL),
(366, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 15:04:51', NULL),
(367, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 15:09:55', NULL),
(368, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 23:51:47', NULL),
(369, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-14 23:59:18', NULL),
(370, 3, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-14 23:59:50', NULL),
(371, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 00:12:50', NULL),
(372, 3, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 00:13:37', NULL),
(373, 3, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 00:36:54', NULL),
(374, 3, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 00:39:38', NULL),
(375, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 00:42:39', NULL),
(376, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 00:42:53', NULL),
(377, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 00:46:45', NULL),
(378, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 00:46:57', NULL),
(379, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 00:52:15', NULL),
(380, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 00:52:26', NULL),
(381, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 02:24:49', NULL),
(382, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:15:17', NULL),
(383, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:15:37', NULL),
(384, 18, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:17:00', NULL),
(385, 18, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:18:45', NULL),
(386, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:18:53', NULL),
(387, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:20:13', NULL),
(388, 18, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:20:25', NULL),
(389, 18, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:27:26', NULL),
(390, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:31:01', NULL),
(391, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:32:20', NULL),
(392, 15, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:33:51', NULL),
(393, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:35:45', NULL),
(394, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:38:18', NULL),
(395, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:38:57', NULL),
(396, 19, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:41:25', NULL),
(397, 19, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:43:31', NULL),
(398, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:43:45', NULL),
(399, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:45:28', NULL),
(400, 19, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:45:43', NULL),
(401, 19, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:46:03', NULL),
(402, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:46:10', NULL),
(403, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:50:55', NULL),
(404, 3, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:51:07', NULL),
(405, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 05:51:51', NULL),
(406, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 06:03:55', NULL),
(407, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 06:06:36', NULL),
(408, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 06:06:54', NULL),
(409, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 06:07:08', NULL),
(410, 20, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 06:09:29', NULL),
(411, 20, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 06:21:50', NULL),
(412, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 06:21:59', NULL),
(413, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 06:22:25', NULL),
(414, 20, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 06:22:42', NULL),
(415, 20, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 06:49:25', NULL),
(416, 3, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 06:49:43', NULL),
(417, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 13:49:34', NULL),
(418, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 13:49:59', NULL),
(419, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 14:10:00', NULL),
(420, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 14:10:23', NULL),
(421, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 15:02:21', NULL),
(422, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 15:09:10', NULL),
(423, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 15:45:24', NULL),
(424, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 15:45:59', NULL),
(425, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 16:40:46', NULL),
(426, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 16:40:51', NULL),
(427, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 16:41:04', NULL),
(428, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 16:41:19', NULL),
(429, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 17:08:58', NULL),
(430, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 17:20:04', NULL),
(431, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 17:20:17', NULL),
(432, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 17:20:45', NULL),
(433, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 17:21:50', NULL),
(434, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 17:31:41', NULL),
(435, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 17:31:51', NULL),
(436, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 17:37:01', NULL),
(437, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 17:37:12', NULL),
(438, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:02:20', NULL),
(439, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:02:27', NULL),
(440, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:04:41', NULL),
(441, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:04:53', NULL),
(442, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:05:58', NULL),
(443, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:06:07', NULL),
(444, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:07:16', NULL),
(445, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:07:24', NULL),
(446, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:14:23', NULL),
(447, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:14:34', NULL),
(448, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:14:58', NULL),
(449, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:15:18', NULL),
(450, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:20:38', NULL),
(451, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:21:00', NULL),
(452, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:26:58', NULL),
(453, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:27:10', NULL),
(454, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:27:32', NULL),
(455, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:27:47', NULL),
(456, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:37:57', NULL),
(457, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:38:16', NULL),
(458, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:38:38', NULL),
(459, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:38:50', NULL),
(460, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:39:53', NULL),
(461, 3, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:40:02', NULL),
(462, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:47:38', NULL),
(463, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:47:59', NULL),
(464, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:51:43', NULL),
(467, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 18:53:04', NULL),
(468, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 19:10:48', NULL),
(469, 3, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 19:10:57', NULL),
(470, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 19:18:14', NULL),
(471, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 19:18:23', NULL),
(472, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 19:32:11', NULL),
(473, 22, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 19:32:24', NULL),
(474, 22, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 19:38:37', NULL),
(475, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 19:38:53', NULL),
(476, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 19:39:27', NULL),
(477, 22, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 19:39:39', NULL),
(478, 22, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 20:14:09', NULL),
(479, 22, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 20:14:42', NULL),
(480, 22, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-15 20:15:57', NULL),
(481, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-15 23:44:23', NULL),
(482, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:00:47', NULL),
(485, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:01:29', NULL),
(486, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:07:02', NULL),
(487, 24, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:07:22', NULL),
(488, 24, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:11:14', NULL),
(489, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:11:25', NULL),
(490, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:13:30', NULL),
(491, 25, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:14:06', NULL),
(492, 25, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:14:36', NULL),
(493, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:15:23', NULL),
(494, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:15:50', NULL),
(495, 25, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:16:26', NULL),
(496, 25, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:23:15', NULL),
(497, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:23:31', NULL),
(498, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:24:56', NULL),
(499, 26, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:25:11', NULL),
(500, 26, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:29:48', NULL),
(501, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:29:58', NULL),
(502, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:30:21', NULL),
(503, 26, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:30:35', NULL),
(504, 26, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:40:55', NULL),
(505, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:41:04', NULL),
(506, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:44:41', NULL),
(507, 27, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:45:02', NULL),
(508, 27, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:54:40', NULL),
(509, 27, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 00:55:17', NULL),
(510, 27, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 01:14:13', NULL),
(511, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 01:14:28', NULL),
(512, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 01:17:32', NULL),
(513, 26, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 01:17:45', NULL),
(514, 26, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 02:34:08', NULL),
(515, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 02:45:51', NULL),
(516, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 02:46:23', NULL),
(517, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 04:29:59', NULL),
(518, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 04:30:50', NULL),
(519, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 04:31:25', NULL),
(520, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 05:22:13', NULL),
(521, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 05:22:32', NULL),
(522, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 05:23:04', NULL),
(523, 20, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 05:23:40', NULL),
(524, 20, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 05:27:03', NULL),
(525, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 05:27:53', NULL),
(526, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 05:28:17', NULL),
(527, 15, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 05:28:31', NULL),
(528, 15, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 05:28:50', NULL),
(529, 20, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 05:29:06', NULL),
(530, 20, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 05:33:17', NULL),
(531, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 05:33:48', NULL),
(532, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 05:34:35', NULL),
(533, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 05:50:35', NULL),
(534, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 05:50:50', NULL),
(535, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 05:51:05', NULL),
(536, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:10:15', NULL),
(537, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:10:27', NULL),
(538, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:10:55', NULL),
(539, 26, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:11:22', NULL),
(540, 26, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:11:55', NULL),
(541, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:12:06', NULL),
(542, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:29:36', NULL),
(543, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:30:59', NULL),
(544, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:31:21', NULL),
(545, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:32:35', NULL),
(546, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:34:41', NULL),
(547, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:35:00', NULL),
(548, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:36:53', NULL),
(549, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:37:18', NULL),
(550, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:40:25', NULL),
(551, 26, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:41:23', NULL),
(552, 26, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:42:41', NULL),
(553, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 06:42:53', NULL),
(554, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 12:55:54', NULL),
(555, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 12:56:22', NULL),
(556, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-16 13:11:03', NULL),
(557, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-16 13:14:40', NULL),
(558, 14, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-04-18 16:08:38', NULL),
(559, 14, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-18 16:09:30', NULL),
(560, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-04-18 16:09:43', NULL),
(561, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-18 16:10:58', NULL),
(562, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-04-23 13:11:23', NULL),
(563, 28, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-05-11 06:30:25', NULL),
(564, 28, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-05-11 06:31:53', NULL),
(565, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-05-11 06:32:03', NULL),
(566, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-05-11 06:32:58', NULL),
(567, 28, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-05-11 06:33:07', NULL),
(568, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-26 06:18:00', NULL),
(569, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-26 06:20:58', NULL),
(570, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-26 06:27:10', NULL),
(571, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-26 06:31:15', NULL),
(572, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-29 03:05:04', NULL),
(573, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-29 03:05:59', NULL),
(574, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-29 03:06:11', NULL),
(575, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-29 05:50:59', NULL),
(576, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-29 06:13:56', NULL),
(577, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-29 06:14:42', NULL),
(578, 30, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-29 06:16:46', NULL),
(579, 30, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-29 06:18:12', NULL),
(580, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-29 06:18:21', NULL),
(581, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-29 06:18:40', NULL),
(582, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-29 06:19:04', NULL),
(583, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-29 06:19:39', NULL),
(584, 30, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-29 06:19:48', NULL),
(585, 30, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-29 06:20:22', NULL),
(586, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-29 07:52:30', NULL),
(587, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-29 07:53:34', NULL),
(588, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-29 07:57:26', NULL),
(589, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-29 07:59:21', NULL),
(590, 3, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-29 07:59:29', NULL),
(591, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-29 07:59:58', NULL),
(592, 15, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-29 08:00:06', NULL),
(593, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-29 08:43:21', NULL),
(594, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-29 08:44:21', NULL),
(595, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-29 08:51:16', NULL),
(596, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 00:27:29', NULL),
(597, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:12:36', NULL),
(598, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:12:48', NULL),
(599, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:14:28', NULL),
(600, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:14:47', NULL),
(601, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:15:20', NULL),
(602, 3, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:18:15', NULL),
(603, 3, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:19:09', NULL),
(604, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:19:22', NULL),
(605, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:21:35', NULL),
(606, 31, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:21:46', NULL),
(607, 31, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:22:10', NULL),
(608, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:22:54', NULL),
(609, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:25:27', NULL),
(610, 31, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:25:45', NULL),
(611, 31, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:32:34', NULL),
(612, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:32:41', NULL),
(613, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:43:43', NULL),
(614, 32, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 01:43:54', NULL),
(615, 32, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 02:04:35', NULL),
(616, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 02:04:45', NULL),
(617, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 02:05:25', NULL),
(618, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 02:08:01', NULL),
(619, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 02:10:24', NULL),
(620, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 02:10:32', NULL),
(621, 33, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 02:55:51', NULL),
(622, 33, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 02:56:26', NULL),
(623, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 02:57:58', NULL),
(624, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 03:01:46', NULL),
(625, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 03:03:01', NULL),
(626, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 03:17:18', NULL),
(627, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 03:18:47', NULL);
INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `table_affected`, `record_id`, `old_values`, `new_values`, `ip_address`, `created_at`, `user_agent`) VALUES
(628, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 03:22:04', NULL),
(629, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 05:25:51', NULL),
(630, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 05:30:58', NULL),
(631, 34, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 05:32:28', NULL),
(632, 34, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 05:33:54', NULL),
(633, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 05:34:02', NULL),
(634, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 05:40:32', NULL),
(635, 34, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 05:40:49', NULL),
(636, 34, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 05:42:01', NULL),
(637, 2, 'login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 06:03:01', NULL),
(638, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 06:04:32', NULL),
(639, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 06:08:54', NULL),
(640, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 06:11:21', NULL),
(641, 36, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 06:11:30', NULL),
(642, 36, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 06:33:21', NULL),
(643, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 06:43:34', NULL),
(644, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 07:07:02', NULL),
(645, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 07:07:08', NULL),
(646, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 07:09:27', NULL),
(647, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 07:09:34', NULL),
(648, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 07:16:29', NULL),
(649, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 07:16:56', NULL),
(650, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 07:37:59', NULL),
(651, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 07:38:44', NULL),
(652, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 07:39:00', NULL),
(653, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 07:39:06', NULL),
(654, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-06-30 07:43:32', NULL),
(655, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-06-30 07:43:39', NULL),
(656, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 01:16:47', NULL),
(657, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 01:16:55', NULL),
(658, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 01:18:37', NULL),
(659, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 01:18:52', NULL),
(660, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 02:56:48', NULL),
(661, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 02:57:00', NULL),
(662, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 03:17:31', NULL),
(663, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 03:18:22', NULL),
(664, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 05:04:31', NULL),
(665, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 05:04:45', NULL),
(666, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 05:18:06', NULL),
(667, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 07:35:53', NULL),
(668, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 07:38:55', NULL),
(669, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 07:39:32', NULL),
(670, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 07:42:19', NULL),
(671, 37, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 07:42:33', NULL),
(672, 37, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 07:45:35', NULL),
(673, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 07:45:46', NULL),
(674, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 07:46:04', NULL),
(675, 38, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 07:50:36', NULL),
(676, 38, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:09:23', NULL),
(677, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:09:37', NULL),
(678, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:10:21', NULL),
(679, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:10:34', NULL),
(680, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:10:58', NULL),
(681, 38, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:11:08', NULL),
(682, 38, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:17:39', NULL),
(683, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:17:50', NULL),
(684, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:23:12', NULL),
(685, 38, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:23:22', NULL),
(686, 38, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:24:00', NULL),
(687, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:24:08', NULL),
(688, 38, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:26:34', NULL),
(689, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:27:53', NULL),
(690, 38, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:28:03', NULL),
(691, 38, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:29:18', NULL),
(692, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:29:30', NULL),
(693, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-01 08:48:27', NULL),
(694, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 01:34:32', NULL),
(695, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 01:36:24', NULL),
(696, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 01:36:30', NULL),
(697, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 01:36:48', NULL),
(698, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 01:41:02', NULL),
(699, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 01:57:58', NULL),
(700, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 01:58:31', NULL),
(701, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 01:58:37', NULL),
(702, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 01:58:47', NULL),
(703, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:02:35', NULL),
(704, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:02:52', NULL),
(705, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:07:10', NULL),
(706, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:12:31', NULL),
(707, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:27:35', NULL),
(708, 39, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:29:01', NULL),
(709, 39, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:30:09', NULL),
(710, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:30:18', NULL),
(711, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:30:43', NULL),
(712, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:30:49', NULL),
(713, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:30:57', NULL),
(714, 39, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:31:12', NULL),
(715, 39, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:33:41', NULL),
(716, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:33:48', NULL),
(717, 38, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:39:36', NULL),
(718, 39, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:42:20', NULL),
(719, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:42:36', NULL),
(720, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:42:42', NULL),
(721, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:51:56', NULL),
(722, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:52:02', NULL),
(723, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:52:34', NULL),
(724, 39, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:52:45', NULL),
(725, 39, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:54:21', NULL),
(726, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:54:27', NULL),
(727, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:54:51', NULL),
(728, 39, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:55:02', NULL),
(729, 39, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:56:09', NULL),
(730, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:56:22', NULL),
(731, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:56:32', NULL),
(732, 39, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 02:56:41', NULL),
(733, 39, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:02:16', NULL),
(734, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:02:31', NULL),
(735, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:02:49', NULL),
(736, 39, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:03:00', NULL),
(737, 39, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:03:51', NULL),
(738, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:04:01', NULL),
(739, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:05:00', NULL),
(740, 40, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:06:17', NULL),
(741, 40, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:06:45', NULL),
(742, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:06:51', NULL),
(743, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:07:28', NULL),
(744, 40, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:07:37', NULL),
(745, 40, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:09:25', NULL),
(746, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:09:34', NULL),
(747, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:09:46', NULL),
(748, 40, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:10:01', NULL),
(749, 40, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:11:27', NULL),
(750, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:11:31', NULL),
(751, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:34:22', NULL),
(752, 2, 'staff_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 03:34:30', NULL),
(753, 2, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 04:07:49', NULL),
(754, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 04:08:48', NULL),
(755, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 04:09:09', NULL),
(756, 1, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 05:29:57', NULL),
(757, 1, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 05:31:03', NULL),
(758, 39, 'resident_login', NULL, NULL, NULL, NULL, '::1', '2026-07-02 05:31:24', NULL),
(759, 39, 'logout', NULL, NULL, NULL, NULL, '::1', '2026-07-02 05:42:25', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `document_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `document_type` varchar(100) DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `verification_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verification_date` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`document_id`, `user_id`, `appointment_id`, `document_type`, `document_path`, `upload_date`, `verification_status`, `verified_by`, `verification_date`, `remarks`) VALUES
(1, 4, NULL, 'housing_requirement', 'uploads/housing/69b77bea639a1_1773632490.jpg', '2026-03-16 03:41:30', 'pending', NULL, NULL, NULL),
(3, 20, NULL, 'Letter of Intent', 'uploads/housing/69df347599da6_1776235637.png', '2026-04-15 06:47:17', 'pending', NULL, NULL, NULL),
(4, 20, NULL, 'Barangay Clearance', 'uploads/housing/69df34759b018_1776235637.png', '2026-04-15 06:47:17', 'pending', NULL, NULL, NULL),
(5, 20, NULL, 'Valid ID', 'uploads/housing/69df34759bd40_1776235637.png', '2026-04-15 06:47:17', 'pending', NULL, NULL, NULL),
(6, 20, NULL, 'Affidavit of Consent', 'uploads/housing/69df34759d2c4_1776235637.png', '2026-04-15 06:47:17', 'pending', NULL, NULL, NULL),
(7, 20, NULL, 'House Photo', 'uploads/housing/69df34759dee1_1776235637.png', '2026-04-15 06:47:17', 'pending', NULL, NULL, NULL),
(8, 20, NULL, 'Proof of Billing', 'uploads/housing/69df34759efa9_1776235637.png', '2026-04-15 06:47:17', 'pending', NULL, NULL, NULL),
(9, 20, NULL, 'Tax Declaration', 'uploads/housing/69df3475a12b1_1776235637.png', '2026-04-15 06:47:17', 'pending', NULL, NULL, NULL),
(10, 20, NULL, 'Additional Document', 'uploads/housing/69df3475a253d_1776235637.png', '2026-04-15 06:47:17', 'pending', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `log_id` int(11) NOT NULL,
  `recipient` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('sent','failed') DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `housing_beneficiaries`
--

CREATE TABLE `housing_beneficiaries` (
  `beneficiary_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `program_id` int(11) DEFAULT NULL,
  `application_date` date DEFAULT NULL,
  `status` enum('pending','qualified','approved','rejected','released') DEFAULT 'pending',
  `household_composition` text DEFAULT NULL,
  `monthly_income` decimal(10,2) DEFAULT NULL,
  `housing_need_assessment` text DEFAULT NULL,
  `interviewed_by` int(11) DEFAULT NULL,
  `interview_date` date DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` date DEFAULT NULL,
  `unit_assigned` varchar(50) DEFAULT NULL,
  `move_in_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `housing_beneficiaries`
--

INSERT INTO `housing_beneficiaries` (`beneficiary_id`, `user_id`, `program_id`, `application_date`, `status`, `household_composition`, `monthly_income`, `housing_need_assessment`, `interviewed_by`, `interview_date`, `approved_by`, `approval_date`, `unit_assigned`, `move_in_date`) VALUES
(1, 1, 2, NULL, 'pending', '10', 15000.00, 'nasunugan', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 4, 1, NULL, 'approved', '2', 5000.00, 'medyo okay', NULL, '2026-07-09', 3, '2026-03-16', 'blk 4 lot 5', NULL),
(3, 1, 1, NULL, 'rejected', '20', 10000.00, 'huhuhuhu\n\nApplication withdrawn by applicant on 2026-04-16 13:15:21', NULL, NULL, NULL, NULL, NULL, NULL),
(4, 20, 2, NULL, 'qualified', '8', 3000.00, 'nasunugan po kami kasi yung kapatid ko nagsindi ng', NULL, '0000-00-00', NULL, NULL, '', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `housing_programs`
--

CREATE TABLE `housing_programs` (
  `program_id` int(11) NOT NULL,
  `program_name` varchar(100) NOT NULL,
  `program_code` varchar(50) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `total_units` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `status` enum('active','completed','pending') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `housing_programs`
--

INSERT INTO `housing_programs` (`program_id`, `program_name`, `program_code`, `location`, `total_units`, `description`, `requirements`, `status`, `created_at`) VALUES
(1, 'Akap Village Housing Project', 'AKAP-001', 'Akap Village, Palayan City', 150, 'Low-cost housing project for qualified beneficiaries', NULL, 'active', '2026-03-03 04:53:22'),
(2, 'San Lorenzo NHA Housing', 'NHA-SL-001', 'San Lorenzo, Palayan City', 200, 'NHA housing project for qualified applicants', NULL, 'active', '2026-03-03 04:53:22');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `type` enum('appointment','aics','housing','general') DEFAULT 'general',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, 'Appointment Confirmed', 'Your appointment has been scheduled for April 02, 2026 at 10:00 AM', 'appointment', 1, '2026-03-03 05:13:07'),
(2, 1, 'AICS Application Submitted', 'Your AICS application has been submitted. Tracking number: AICS-000001', 'aics', 1, '2026-03-03 05:28:36'),
(3, 1, 'Housing Application Submitted', 'Your housing application has been submitted and is pending review.', 'housing', 1, '2026-03-03 05:46:23'),
(5, 4, 'Housing Application Submitted', 'Your housing application has been submitted and is pending review.', 'housing', 1, '2026-03-16 03:41:30'),
(6, 4, 'Housing Application Updated', 'Your housing application status has been updated to: qualified', 'housing', 1, '2026-03-16 03:42:35'),
(7, 4, 'Housing Application Updated', 'Your housing application status has been updated to: approved', 'housing', 1, '2026-03-16 03:44:48'),
(8, 1, 'Housing Application Submitted', 'Your housing application has been submitted and is pending review.', 'housing', 1, '2026-03-16 03:51:29'),
(9, 1, 'Appointment Cancelled', 'Your appointment has been cancelled successfully.', 'appointment', 1, '2026-03-16 04:52:52'),
(10, 1, 'Appointment Cancelled', 'Your appointment has been cancelled', 'appointment', 1, '2026-03-18 01:52:41'),
(11, 1, 'Appointment Confirmed', 'Your appointment has been scheduled for March 18, 2026 at 09:00 AM', 'appointment', 1, '2026-03-18 02:00:20'),
(12, 1, 'Appointment Confirmed', 'Your appointment has been scheduled for March 23, 2026 at 10:00 AM', 'appointment', 1, '2026-03-18 02:04:21'),
(13, 1, 'AICS Application Submitted', 'Your AICS application has been submitted. Tracking number: AICS-000002', 'aics', 1, '2026-03-18 02:07:26'),
(14, 1, 'Appointment Updated', 'Your appointment status has been updated to: cancelled', 'appointment', 1, '2026-03-18 02:19:26'),
(15, 3, 'AICS Application Submitted', 'Your AICS application has been submitted. Tracking number: AICS-000003', 'aics', 1, '2026-03-18 02:43:36'),
(16, 1, 'Appointment Cancelled', 'Your appointment has been cancelled', 'appointment', 1, '2026-03-30 13:27:28'),
(17, 14, 'Appointment Booked', 'Your appointment has been scheduled for April 12, 2026 at 09:00 AM', 'appointment', 1, '2026-04-12 03:10:32'),
(18, 1, 'Appointment Booked', 'Your appointment has been scheduled for April 16, 2026 at 01:00 PM', 'appointment', 1, '2026-04-13 07:14:56'),
(19, 1, 'Appointment Booked', 'Your appointment has been scheduled for April 14, 2026 at 09:00 AM', 'appointment', 1, '2026-04-13 07:31:33'),
(20, 1, 'Appointment Booked', 'Your appointment has been scheduled for April 15, 2026 at 11:00 AM', 'appointment', 1, '2026-04-13 07:32:16'),
(21, 1, 'Appointment Booked', 'Your appointment has been scheduled for April 27, 2026 at 01:00 PM', 'appointment', 1, '2026-04-13 07:45:08'),
(22, 1, 'Appointment Booked', 'Your appointment has been scheduled for April 14, 2026 at 10:00 AM', 'appointment', 1, '2026-04-13 07:46:07'),
(23, 1, 'Appointment Updated', 'Your appointment status has been updated to: confirmed', 'appointment', 1, '2026-04-13 07:47:51'),
(24, 1, 'Appointment Updated', 'Your appointment status has been updated to: confirmed', 'appointment', 1, '2026-04-13 07:48:01'),
(25, 1, 'Appointment Booked', 'Your appointment has been scheduled for April 15, 2026 at 03:00 PM', 'appointment', 1, '2026-04-13 07:53:36'),
(26, 1, 'Appointment Booked', 'Your appointment has been scheduled for April 14, 2026 at 10:00 AM', 'appointment', 1, '2026-04-13 07:55:53'),
(27, 1, 'Funeral Assistance Submitted', 'Your application has been submitted. Tracking #: AICS-000007', 'aics', 1, '2026-04-13 08:27:03'),
(28, 1, 'Educational Assistance Submitted', 'Your application has been submitted. Tracking #: AICS-000012', 'aics', 1, '2026-04-13 08:41:00'),
(29, 1, 'Cash Relief Submitted', 'Your application has been submitted. Tracking #: AICS-000014', 'aics', 1, '2026-04-13 08:48:08'),
(30, 1, 'Transportation Assistance Submitted', 'Your application has been submitted. Tracking #: AICS-000016', 'aics', 1, '2026-04-13 13:49:10'),
(31, 1, 'Food Assistance Submitted', 'Your application has been submitted. Tracking #: AICS-000017', 'aics', 1, '2026-04-13 13:53:26'),
(32, 1, 'Material Assistance Submitted', 'Your application has been submitted. Tracking #: AICS-000018', 'aics', 1, '2026-04-13 13:58:02'),
(33, 14, 'Educational Assistance Submitted', 'Your application has been submitted. Tracking #: AICS-000019', 'aics', 1, '2026-04-14 02:02:52'),
(34, 14, 'AICS Status Updated', 'Your AICS request status has been updated to: approved', 'aics', 1, '2026-04-14 02:05:16'),
(35, 1, 'AICS Status Updated', 'Your AICS request status has been updated to: approved', 'aics', 1, '2026-04-14 02:06:56'),
(36, 1, 'Appointment Booked', 'Your appointment has been scheduled for April 15, 2026 at 10:00 AM', 'appointment', 1, '2026-04-14 03:33:33'),
(37, 1, 'Appointment Cancelled', 'Your appointment has been cancelled successfully.', 'appointment', 1, '2026-04-14 13:03:15'),
(38, 1, 'Appointment Cancelled', 'Your appointment has been cancelled', 'appointment', 1, '2026-04-14 13:10:11'),
(39, 1, 'Verification Approved', 'Your residency verification has been approved! You can now access all services.', '', 1, '2026-04-14 14:00:02'),
(40, 18, 'Verification Approved', 'Your residency verification has been approved! You can now access all services.', '', 1, '2026-04-15 05:19:54'),
(41, 1, 'AICS Status Updated', 'Your AICS request status has been updated to: reviewing', 'aics', 1, '2026-04-15 05:34:14'),
(42, 1, 'AICS Status Updated', 'Your AICS request status has been updated to: approved', 'aics', 1, '2026-04-15 05:34:40'),
(43, 1, 'AICS Status Updated', 'Your AICS request status has been updated to: approved', 'aics', 1, '2026-04-15 05:35:04'),
(44, 19, 'Verification Rejected', 'Your residency verification was rejected. Reason: Mali ang Id please review', '', 0, '2026-04-15 05:45:09'),
(45, 1, 'Appointment Booked', 'Your appointment has been scheduled for April 23, 2026 at 03:00 PM', 'appointment', 1, '2026-04-15 05:47:46'),
(46, 20, 'Verification Approved', 'Your residency verification has been approved! You can now access all services.', '', 1, '2026-04-15 06:22:05'),
(48, 20, 'Food Assistance Submitted', 'Your application has been submitted. Tracking #: AICS-000020', 'aics', 1, '2026-04-15 06:43:05'),
(49, 20, 'Housing Application Submitted', 'Your housing application has been submitted and is pending review.', 'housing', 1, '2026-04-15 06:47:17'),
(50, 20, 'Housing Application Updated', 'Your housing application status has been updated to: Qualified', 'housing', 1, '2026-04-15 18:43:09'),
(51, 1, 'AICS Status Updated', 'Your AICS request status has been updated to: approved', 'aics', 1, '2026-04-16 01:01:00'),
(52, 1, 'Application Withdrawn', 'Your housing application has been withdrawn successfully.', 'housing', 1, '2026-04-16 05:15:21'),
(53, 20, 'Appointment Booked', 'Your appointment has been scheduled for April 20, 2026 at 01:00 PM', 'appointment', 1, '2026-04-16 05:25:29'),
(54, 14, 'Verification Approved', 'Your residency verification has been approved! You can now access all services.', '', 1, '2026-04-16 06:36:31'),
(55, 14, 'Appointment Booked', 'Your appointment has been scheduled for April 20, 2026 at 11:00 AM', 'appointment', 1, '2026-04-16 06:38:44'),
(56, 28, 'Verification Approved', 'Your residency verification has been approved! You can now access all services.', '', 1, '2026-05-11 06:32:24'),
(57, 30, 'Verification Approved', 'Your residency verification has been approved! You can now access all services.', '', 1, '2026-06-29 06:19:33'),
(58, 34, 'Verification Approved', 'Your residency verification has been approved! You can now access all services.', '', 1, '2026-06-30 05:40:05'),
(59, 1, 'AICS Request Cancelled', 'Your AICS request has been cancelled.', 'aics', 1, '2026-06-30 07:09:54'),
(60, 1, 'AICS Request Cancelled', 'Your AICS request has been cancelled.', 'aics', 1, '2026-06-30 07:09:56'),
(61, 1, 'AICS Request Cancelled', 'Your AICS request has been cancelled.', 'aics', 1, '2026-06-30 07:09:59'),
(62, 1, 'Appointment Cancelled', 'Your appointment has been cancelled', 'appointment', 1, '2026-07-01 05:07:00'),
(63, 1, 'Appointment Booked', 'Your appointment has been scheduled for July 31, 2026 at 10:00 AM', 'appointment', 1, '2026-07-01 07:36:51'),
(64, 38, 'Verification Approved', 'Your residency verification has been approved! You can now access all services.', '', 1, '2026-07-01 08:10:50'),
(65, 1, 'Appointment Cancelled', 'Your appointment has been cancelled', 'appointment', 1, '2026-07-02 02:03:15'),
(66, 39, 'Verification Approved', 'Your residency verification has been approved! You can now access all services.', '', 1, '2026-07-02 02:42:58'),
(67, 40, 'Verification Approved', 'Your residency verification has been approved! You can now access all services.', '', 1, '2026-07-02 03:09:41'),
(68, 1, 'Appointment Cancelled', 'Your appointment has been cancelled', 'appointment', 0, '2026-07-02 05:30:43');

-- --------------------------------------------------------

--
-- Table structure for table `request_documents`
--

CREATE TABLE `request_documents` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_type` varchar(100) DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected','needs_revision') NOT NULL DEFAULT 'pending',
  `verification_remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `residents`
--

CREATE TABLE `residents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `resident_number` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `civil_status` enum('Single','Married','Widowed','Divorced','Separated') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `household_number` varchar(50) DEFAULT NULL,
  `senior_citizen` tinyint(1) DEFAULT 0,
  `pwd` tinyint(1) DEFAULT 0,
  `occupation` varchar(100) DEFAULT NULL,
  `monthly_income` decimal(10,2) DEFAULT 0.00,
  `educational_attainment` varchar(100) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_number` varchar(20) DEFAULT NULL,
  `relationship` varchar(50) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `residents`
--

INSERT INTO `residents` (`id`, `user_id`, `resident_number`, `first_name`, `last_name`, `middle_name`, `suffix`, `birth_date`, `gender`, `civil_status`, `address`, `barangay`, `city`, `province`, `postal_code`, `household_number`, `senior_citizen`, `pwd`, `occupation`, `monthly_income`, `educational_attainment`, `emergency_contact_name`, `emergency_contact_number`, `relationship`, `contact_number`, `email`, `street`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 1, 'RES-2026-0002', 'Mon Aaron', 'Marcelo', 'Villena', '', '2005-09-24', 'Male', 'Single', NULL, 'Atate', 'Palayan City', 'Nueva Ecija', '3132', '', 0, 0, 'Student', 0.00, 'College Level', 'Ramon Marcelo', '09910549953', 'Father', '09454983003', '', 'Sapphihre Street, Blk 4  Lot 5', 1, '2026-04-15 18:14:12', '2026-07-02 03:04:37'),
(3, 14, 'RES-2026-0003', 'Shiloh', 'Abellera', '', '', '2000-11-14', 'Male', 'Single', NULL, 'Singalat', 'Palayan City', 'Nueva Ecija', '3132', '', 0, 0, 'Student', 0.00, 'College Level', 'Febelyn Abellera', '09774873957', 'Mother', '09998887776', 'shiloh14@gmail.com', 'Bayabas Street', 1, '2026-04-15 18:30:44', '2026-07-01 08:24:36'),
(7, 38, 'RES-2026-0004', 'John', 'Cena', 'Aaron', '', '2018-02-15', 'Male', 'Married', NULL, 'Mapaet', 'Palayan City', 'Nueva Ecija', '3132', '', 0, 0, 'Janitor', 10000.00, 'Elementary Graduate', 'Mon Aaron Marcelo', '099199397732', 'Sibling', '09923132233', 'johncena123@gmail.com', 'Saphire Street', 1, '2026-07-01 08:17:05', '2026-07-01 08:24:32'),
(8, 39, 'RES-2026-0005', 'Joshua', 'Garcia', 'Aaron', '', '2010-06-22', 'Male', 'Single', NULL, 'Mapaet', 'Palayan City', 'Nueva Ecija', '3132', '', 0, 0, 'Artist', 2000.00, 'College Graduate', 'Jing', 'Jing', 'Mother', '09889213123', 'joshuagarcia123@gmail.com', 'Mapaet Street', 1, '2026-07-02 03:03:46', '2026-07-02 03:03:46'),
(9, 40, 'RES-2026-0006', 'Jackie', 'Chan', '', '', '1959-02-24', 'Male', 'Married', NULL, 'Aulo', 'Palayan City', 'Nueva Ecija', '3132', '', 1, 0, 'Martial Arts', 20000.00, 'College Graduate', 'Manny', 'Pacquiao', 'Best Friends', '09299939192', 'jackiechan123@gmail.com', 'Mapaet Street', 1, '2026-07-02 03:11:25', '2026-07-02 03:11:25');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `service_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `processing_time` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `service_code` varchar(50) DEFAULT NULL,
  `category` varchar(100) DEFAULT 'General',
  `duration_minutes` int(11) DEFAULT 30
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `category_id`, `service_name`, `description`, `requirements`, `processing_time`, `is_active`, `service_code`, `category`, `duration_minutes`) VALUES
(1, 1, 'Assistance to Individuals in Crisis Situation (AICS)', 'Financial and material assistance for individuals in crisis', NULL, '3-5 working days', 1, 'AICS', 'General', 30),
(2, 1, 'Senior Citizen Pension', 'Monthly pension and benefits for senior citizens', NULL, '5-7 working days', 1, 'SCP', 'General', 30),
(3, 1, 'PWD Registration and Benefits', 'Registration and benefits for Persons with Disability', NULL, '2-3 working days', 1, 'PWD', 'General', 30),
(4, 2, 'Housing Application - Akap Village', 'Application for housing in Akap Village', NULL, '7-10 working days', 1, 'HOUSING_AKAP', 'General', 30),
(5, 2, 'Housing Application - San Lorenzo (NHA)', 'Application for NHA housing in San Lorenzo', NULL, '7-10 working days', 1, 'HOUSING_NHA', 'General', 30),
(6, 1, 'AICS - Medical Assistance', 'Financial assistance for hospital bills, medicines, laboratory tests, and medical procedures', NULL, '3-5 days', 1, NULL, 'General', 30),
(7, 1, 'AICS - Funeral/Burial Assistance', 'Financial support for funeral services and burial expenses', NULL, '2-3 days', 1, NULL, 'General', 30),
(8, 1, 'AICS - Educational Assistance', 'Support for school supplies, tuition fees, and other educational needs', NULL, '5-7 days', 1, NULL, 'General', 30),
(9, 1, 'AICS - Transportation Assistance', 'Help with transportation expenses for medical appointments or urgent needs', NULL, '1-2 days', 1, NULL, 'General', 30),
(10, 1, 'AICS - Food Assistance', 'Food packs and grocery items for families in crisis', NULL, '1-2 days', 1, NULL, 'General', 30),
(11, 1, 'AICS - Cash Relief Assistance', 'Financial assistance for individuals and families in crisis situation', NULL, '3-5 days', 1, NULL, 'General', 30),
(12, 1, 'AICS - Material Assistance', 'Non-food items, hygiene kits, and other material support', NULL, '2-3 days', 1, NULL, 'General', 30),
(13, 2, 'Senior Citizen Pension', 'Monthly pension and benefits for senior citizens aged 60 and above', NULL, '5-7 days', 1, NULL, 'General', 30),
(14, 3, 'PWD Registration', 'Registration and issuance of PWD ID for persons with disability', NULL, '3-5 days', 1, NULL, 'General', 30),
(15, 4, 'Housing Program - Akap Village', 'Application for housing in Akap Village', NULL, '7-10 days', 1, NULL, 'General', 30),
(16, 4, 'Housing Program - San Lorenzo NHA', 'Application for NHA housing in San Lorenzo', NULL, '7-10 days', 1, NULL, 'General', 30);

-- --------------------------------------------------------

--
-- Table structure for table `service_categories`
--

CREATE TABLE `service_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_categories`
--

INSERT INTO `service_categories` (`category_id`, `category_name`, `description`, `is_active`) VALUES
(1, 'Social Assistance', 'Various social assistance programs including AICS, senior citizen benefits, and PWD support', 0),
(2, 'Housing', 'Government housing programs and beneficiary management', 1),
(3, 'Family Services', 'Family counseling, support services, and interventions', 0),
(4, 'Community Development', 'Community-based programs and initiatives', 0),
(5, 'AICS', 'Medical, Funeral, Educational, Transportation, Food, Cash Relief, and Material Assistance', 1),
(6, 'Senior Citizen', 'Monthly pension and benefits for senior citizens', 1),
(7, 'PWD', 'Registration and benefits for Persons with Disability', 1);

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

CREATE TABLE `service_requests` (
  `id` int(11) NOT NULL,
  `request_number` varchar(50) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `assigned_staff_id` int(11) DEFAULT NULL,
  `status` enum('pending','for_review','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `scheduled_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `processed_date` timestamp NULL DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `staff_number` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `specialization` varchar(100) DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `profile_image` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `user_id`, `staff_number`, `first_name`, `last_name`, `middle_name`, `position`, `department`, `contact_number`, `is_active`, `created_at`, `updated_at`, `specialization`, `employee_id`, `hire_date`, `profile_image`) VALUES
(1, 6, '', '', '', NULL, 'Administrative Officer', NULL, NULL, 1, '2026-04-07 08:18:57', '2026-04-07 08:18:57', NULL, 'STAFF11', '2026-04-07', NULL),
(8, 15, 'STF01', 'sarah', 'cabuhat', '', 'Social Worker', 'AICS', '09123123232', 1, '2026-04-13 14:03:23', '2026-04-13 14:03:23', 'Housing', 'EMP-0001', '2026-04-13', NULL),
(11, 22, 'STF-09', 'Sheena', 'Pascual', 'Villegas', 'AICS', 'CSWDO', '0992992921', 1, '2026-04-15 19:19:50', '2026-04-15 19:19:50', 'AICS', 'EMP-0023', '2026-04-16', NULL),
(13, 24, 'STF-0092', 'John', 'Sales', '', 'AICS', 'CSWDO', '09877655432', 1, '2026-04-16 00:03:58', '2026-04-16 00:03:58', 'AICS', 'Emp-0090', '2026-04-16', NULL),
(15, 26, 'STF-123', 'Charles', 'Darwin', '', 'AICS Staff', 'CSWDO', '0956738394', 1, '2026-04-16 00:24:37', '2026-04-16 00:24:37', 'AICS', 'EMP-123', '2026-04-16', NULL),
(16, 27, 'STF-0013', 'Enchong', 'Dee', '', 'AICS Staff', 'CSWDO', '09888123112', 1, '2026-04-16 00:42:20', '2026-06-26 06:18:50', 'AICS', 'EMP-091', '2026-04-16', NULL),
(17, 31, 'STF-092', 'mon', 'aaron', 'villena', 'AICS Staff', 'CSWDO', '09011232313', 1, '2026-06-30 01:21:08', '2026-06-30 01:21:08', 'AICS', 'EMP-0202931331', '2026-06-30', NULL),
(18, 32, 'STF-0921', 'Juan', 'Delacruz', 'CARDO', 'Housing Staff', 'CSWDO', '0992313223', 0, '2026-06-30 01:43:05', '2026-07-01 08:30:11', 'HOUSING', 'EMP-09934324', '2026-06-18', NULL),
(19, 36, 'STF-09211', 'Daniel', 'Padilla', 'Pineda', 'AICS Staff, Housing Staff, Senior Citizen Staff, PWD Staff', '', '091123312332', 1, '2026-06-30 06:11:03', '2026-06-30 06:11:03', 'AICS, HOUSING, SENIOR, PWD', 'EMP-02029313312', '2026-06-18', NULL),
(20, 37, 'STF-2026-0010', 'Thor', 'Son', 'Of Odin', 'AICS Staff, Housing Staff, Senior Citizen Staff, PWD Staff', NULL, '09113223245', 1, '2026-07-01 07:41:56', '2026-07-01 07:41:56', 'AICS, HOUSING, SENIOR, PWD', 'EMP-2026-0010', '2026-06-10', NULL),
(21, 41, 'STF-2026-0011', 'Captain', 'America', '', 'AICS Staff, Housing Staff, PWD Staff', NULL, '094549830032', 1, '2026-07-02 03:36:05', '2026-07-02 03:36:05', 'AICS, HOUSING, PWD', 'EMP-2026-0011', '2026-07-02', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff_service_assignments`
--

CREATE TABLE `staff_service_assignments` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `assigned_date` timestamp NULL DEFAULT current_timestamp(),
  `assigned_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_service_assignments`
--

INSERT INTO `staff_service_assignments` (`id`, `staff_id`, `category_id`, `assigned_date`, `assigned_by`, `is_active`, `assigned_at`) VALUES
(1, 16, 5, '2026-04-16 00:42:20', NULL, 1, '2026-04-16 00:42:20'),
(2, 15, 2, '2026-04-16 01:17:28', NULL, 1, '2026-04-16 01:17:28'),
(4, 13, 2, '2026-06-30 01:07:22', NULL, 1, '2026-06-30 01:07:22'),
(6, 17, 5, '2026-06-30 01:21:08', NULL, 1, '2026-06-30 01:21:08'),
(7, 18, 2, '2026-06-30 01:43:05', NULL, 1, '2026-06-30 01:43:05'),
(8, 17, 2, '2026-06-30 06:04:05', NULL, 1, '2026-06-30 06:04:05'),
(9, 17, 6, '2026-06-30 06:04:07', NULL, 1, '2026-06-30 06:04:07'),
(10, 17, 7, '2026-06-30 06:04:08', NULL, 1, '2026-06-30 06:04:08'),
(11, 18, 5, '2026-06-30 06:04:25', NULL, 1, '2026-06-30 06:04:25'),
(12, 18, 6, '2026-06-30 06:04:26', NULL, 1, '2026-06-30 06:04:26'),
(13, 18, 7, '2026-06-30 06:04:27', NULL, 1, '2026-06-30 06:04:27'),
(14, 19, 5, '2026-06-30 06:11:03', NULL, 1, '2026-06-30 06:11:03'),
(15, 19, 2, '2026-06-30 06:11:03', NULL, 1, '2026-06-30 06:11:03'),
(16, 19, 6, '2026-06-30 06:11:03', NULL, 1, '2026-06-30 06:11:03'),
(17, 19, 7, '2026-06-30 06:11:03', NULL, 1, '2026-06-30 06:11:03'),
(22, 20, 5, '2026-07-01 08:30:30', NULL, 1, '2026-07-01 08:30:30'),
(23, 20, 2, '2026-07-01 08:30:30', NULL, 1, '2026-07-01 08:30:30'),
(24, 20, 6, '2026-07-01 08:30:30', NULL, 1, '2026-07-01 08:30:30'),
(25, 20, 7, '2026-07-01 08:30:30', NULL, 1, '2026-07-01 08:30:30'),
(26, 21, 5, '2026-07-02 03:36:05', NULL, 1, '2026-07-02 03:36:05'),
(27, 21, 2, '2026-07-02 03:36:05', NULL, 1, '2026-07-02 03:36:05'),
(28, 21, 7, '2026-07-02 03:36:05', NULL, 1, '2026-07-02 03:36:05');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'One Palayan-Kalinga Hub', '2026-04-15 00:51:07', '2026-04-15 00:51:07'),
(2, 'site_description', 'Integrated Social Services Platform for Palayan City Residents', '2026-04-15 00:51:07', '2026-04-15 00:51:07'),
(3, 'contact_email', 'cswdo@palayancity.gov.ph', '2026-04-15 00:51:07', '2026-04-15 00:51:07'),
(4, 'contact_phone', '+63 (44) 123 4567', '2026-04-15 00:51:07', '2026-04-15 00:51:07'),
(5, 'address', 'City Social Welfare and Development Office, Palayan City, Nueva Ecija', '2026-04-15 00:51:07', '2026-04-15 00:51:07'),
(6, 'maintenance_mode', '0', '2026-04-15 00:51:07', '2026-04-15 00:51:07'),
(7, 'maintenance_message', 'The system is currently under maintenance. Please check back later.', '2026-04-15 00:51:07', '2026-04-15 00:51:07'),
(8, 'appointment_lead_days', '30', '2026-04-15 00:51:07', '2026-04-15 00:51:07'),
(9, 'max_file_size', '5', '2026-04-15 00:51:07', '2026-04-15 00:51:07'),
(10, 'allowed_file_types', 'pdf,jpg,jpeg,png', '2026-04-15 00:51:07', '2026-04-15 00:51:07');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('resident','staff','admin') DEFAULT 'resident',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `profile_picture` varchar(255) DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `verification_date` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `first_name`, `last_name`, `contact_number`, `address`, `role`, `status`, `created_at`, `updated_at`, `last_login`, `is_active`, `profile_picture`, `verification_status`, `verification_date`, `verified_by`) VALUES
(1, 'monaaron123', '$2y$10$teoWV2I9pL8zuBtrm24a3ONh81e6d5OUYH2/uUiKDIvgM38i/WNjC', '', 'Mon Aaron', 'Marcelo', '09454983003', 'Brgy Atate Palayan City', 'resident', 'active', '2026-03-03 04:57:26', '2026-07-02 05:29:57', '2026-07-02 13:29:57', 1, 'uploads/profiles/user_1_5dfa84508b96.jpg', 'verified', '2026-04-14 14:00:02', 2),
(2, 'admin', '$2y$10$fuKmlzWVgw00vduB9Rxdp.C3vlvzIrcLQJmohkt7s/B/xmDeU/MYa', 'admin@palayan.gov.ph', 'System', 'Administrator', NULL, NULL, 'admin', 'active', '2026-03-03 06:04:00', '2026-07-02 03:34:30', '2026-07-02 11:34:30', 1, NULL, 'pending', NULL, NULL),
(3, 'staff1', '$2y$10$1EqUmd2V5U0Tu/DImiJmoeLK9GF93XcLvStIYighJwHweED9h8hfO', 'staff@palayan.gov.ph', 'CSWDO', 'Staff', NULL, NULL, 'staff', 'active', '2026-03-03 06:04:00', '2026-06-30 01:18:15', '2026-06-30 09:18:15', 1, NULL, 'pending', NULL, NULL),
(4, 'jaja', '$2y$10$xDvyx87S4wxbtcc716OyY.1Z8mxqk.tUm6pkSSh6ja41E.D2UrEle', 'jaja@gmail.com', 'Ivy', 'Pancho', '09910549953', 'BOOM BONGABON', 'resident', 'active', '2026-03-16 03:38:49', '2026-03-16 03:38:49', NULL, 1, NULL, 'pending', NULL, NULL),
(5, 'shiloh123', '$2y$10$CIChzKLpoFCWvSR1XBUN3.VXAmor/OMEwS823OcKqPus9LJhVRosO', 'shiloh@gmail.com', 'shiloh', 'abellera', '09464646464', 'sinipit', 'resident', 'active', '2026-03-19 07:28:46', '2026-03-19 07:28:46', NULL, 1, NULL, 'pending', NULL, NULL),
(6, 'shiilloh', '$2y$10$h1QDduu5XG0vQHwYG0i13.zBaQlM8a03ZsYwMWAZ7dVm11/hnDn2e', 'shiloh1231@gmail.com', 'Shiloh', 'Abellera', '09090332322', NULL, 'staff', 'active', '2026-04-07 08:18:57', '2026-04-07 08:19:16', NULL, 1, NULL, 'pending', NULL, NULL),
(11, 'princess_sarah', '$2y$10$XvDMaNJL5myyQ0xDJvmAteZMp/mr4P3w.adNfkm.g3lYCXLTAbln6', 'sarahcabuhat@gmail.com', 'Sarah', 'Cabuhat', '09123456789', 'Brgy Singalat', 'resident', 'active', '2026-04-08 02:38:04', '2026-04-08 02:38:04', NULL, 1, NULL, 'pending', NULL, NULL),
(14, 'shiloh1400', '$2y$10$RtUv9ccgwRZNuL8tuewNt.dvDcsk8JSYitvkW/mUAmU85RuP0I1D.', 'shiloh14@gmail.com', 'Shiloh', 'Abellera', '09998887776', 'Brgy. Sapang Buho, Palayan City Nueva Ecija', 'resident', 'active', '2026-04-11 20:16:19', '2026-04-18 16:08:38', '2026-04-19 00:08:38', 1, 'uploads/profiles/user_14_1776273639.jpg', 'verified', '2026-04-16 06:36:31', 2),
(15, 'sarah123', '$2y$10$y2b2kiLQJTyVBXPt.BBekerezxOjnZoT9adptp5UfVlTHPdjlNZiC', 'sarah@gmail.com', 'sarah', 'cabuhat', '09123123232', NULL, 'staff', 'active', '2026-04-13 14:03:23', '2026-06-29 08:00:06', '2026-06-29 16:00:06', 1, NULL, 'pending', NULL, NULL),
(16, 'jake123', '$2y$10$ARxZWiNajIWsbqssP0fPLecHD1evpjJOzHqhJfV5gcBluEdqe9qeO', 'jake@gmail.com', 'Jake', 'Cuenca', '09466108203', 'atate', 'resident', 'active', '2026-04-14 14:03:02', '2026-04-14 14:03:02', NULL, 1, NULL, 'pending', NULL, NULL),
(18, 'johnpaul123', '$2y$10$HcVDzJvvIzxWJp8Tb6bQNuGYTDNe8hwmTCcF7ptHRH6EARqFbjzn.', 'johnpaul@gmail.com', 'John', 'Paul', '09454983003', 'Brgy Singalat', 'resident', 'active', '2026-04-15 05:16:46', '2026-04-15 05:20:25', '2026-04-15 13:20:25', 1, NULL, 'verified', '2026-04-15 05:19:54', 2),
(19, 'justin123', '$2y$10$azKbYVRyjPFittBo0jFwF.jR3hlcilYz8ggQ6UU/56BPPE3OWgxbq', 'justin@gmail.com', 'Justin', 'Bieber', '09919239449', 'Brgy Santon Palayan City', 'resident', 'active', '2026-04-15 05:40:13', '2026-04-15 05:45:43', '2026-04-15 13:45:43', 1, NULL, 'rejected', '2026-04-15 05:45:09', 2),
(20, 'ivypancho123', '$2y$10$J6ggAFd/khJ4ihh/rBF8Ne5ZkcejINc2empmS5FjQAouj2cdgKUhi', 'ivypancho@gmail.com', 'ivy', 'pancho', '09707678890', 'Brgy Manacnac Palayan City', 'resident', 'active', '2026-04-15 06:09:01', '2026-04-16 05:29:06', '2026-04-16 13:29:06', 1, NULL, 'verified', '2026-04-15 06:22:05', 2),
(22, 'sheena123', '$2y$10$2KvhFSjZXB2ueGVaTmJcKOfC.YursuaRszJbfNQYZU8eF2EgsJUPW', 'sheena@gmail.com', 'Sheena', 'Pascual', '0992992921', NULL, 'staff', 'active', '2026-04-15 19:19:50', '2026-04-15 20:14:42', '2026-04-16 04:14:42', 1, NULL, 'pending', NULL, NULL),
(24, 'john123', '$2y$10$TxYw2HKy3kAkrBaHdnRUXe7GvUqdpOnsHnHeU8VtLGge7szAkBnlK', 'john@gmail.com', 'John', 'Sales', '09877655432', NULL, 'staff', 'active', '2026-04-16 00:03:58', '2026-04-16 00:07:22', '2026-04-16 08:07:22', 1, NULL, 'pending', NULL, NULL),
(25, 'shane123', '$2y$10$6iHsZj5TJcx8JDX4mo56jOjwdP99d0TKzyi4hVPlHfzFeditJvHsa', 'shane@gmail.com', 'Shane', 'Mosley', '09991232321', NULL, 'staff', 'active', '2026-04-16 00:12:53', '2026-04-16 00:16:26', '2026-04-16 08:16:26', 1, NULL, 'pending', NULL, NULL),
(26, 'charles123', '$2y$10$MjfWk0h/avFD9YYNnhkeweHdjOjAMlZYwAul4SKa/0IiDoa4I9k4q', 'charles@gmail.com', 'Charles', 'Darwin', '0956738394', NULL, 'staff', 'active', '2026-04-16 00:24:37', '2026-04-16 06:41:23', '2026-04-16 14:41:23', 1, NULL, 'pending', NULL, NULL),
(27, 'enhong123', '$2y$10$N.1HCfvRFUoqPH.k0jGkp.JHWYFA31B4Gfja9UIzokrV2XjYQOX6e', 'enchong@gmail.com', 'Enchong', 'Dee', '09888123112', NULL, 'staff', 'active', '2026-04-16 00:42:20', '2026-06-26 06:18:50', '2026-04-16 08:55:17', 1, NULL, 'pending', NULL, NULL),
(28, 'juan123', '$2y$10$olVtDN5YRTQ9Rr.tUc8e1eIK6Fsgzt48fwYWFdXImONHm7ZREpvHa', 'juandelacruz@gmail.com', 'Juan', 'Delacruz', '09910549953', 'Brgy Popolon', 'resident', 'active', '2026-05-11 06:30:16', '2026-05-11 06:33:55', '2026-05-11 14:33:07', 1, 'uploads/profiles/user_28_1778481235.png', 'verified', '2026-05-11 06:32:24', 2),
(29, 'tes12345', '$2y$10$gxAsJMK46VrVzDV8fIGxCOKnVmu0AVyiATwA3qMrUAcRbc9uvg6Qa', 'test123@gmail.com', 'Mon Aaron', 'Test', '09231231412', 'Brgy, Atate, Palalayan', 'resident', 'active', '2026-06-26 06:22:57', '2026-06-26 06:22:57', NULL, 1, NULL, 'pending', NULL, NULL),
(30, 'johnwick123', '$2y$10$H1OCJ1kwYR1pie2PafEcD.mAM5l2G4PkRjCUYAoixBe3S05n1Hjxe', 'johnwick123@gmail.com', 'John', 'Wick', '09458965442', 'brgy santolan', 'resident', 'active', '2026-06-29 06:16:33', '2026-06-29 06:19:48', '2026-06-29 14:19:48', 1, 'uploads/profiles/user_30_1782713832.png', 'verified', '2026-06-29 06:19:33', 2),
(31, 'monaaron12345', '$2y$10$1hQIGqymw9mi4LsZ3RSoR.zk5iyLVpuVu6qWIqv24vV4JJMdQaWVq', 'mon@gmail.com', 'mon', 'aaron', '09011232313', NULL, 'staff', 'active', '2026-06-30 01:21:08', '2026-06-30 01:25:45', '2026-06-30 09:25:45', 1, NULL, 'pending', NULL, NULL),
(32, 'cocodalisay123', '$2y$10$oyF4W3pfbT46c8FssGKHNO1wWPanvTWndHqSSaIiepJw5kb5XjBta', 'coco@gmail.com', 'Juan', 'Delacruz', '0992313223', NULL, 'staff', 'inactive', '2026-06-30 01:43:05', '2026-07-01 08:30:11', '2026-06-30 09:43:54', 1, 'uploads/profiles/staff_1782783785_6a431f29a7f14.jpg', 'pending', NULL, NULL),
(33, 'test3', '$2y$10$XTH.NWBzrtVUeeKaolZoqumVK8FVvVAclMS62rKrXXmUA2BBGMi7q', 'test3@gmail.com', 'test', 'test', '09123456789', 'test', 'resident', 'active', '2026-06-30 02:55:39', '2026-06-30 02:55:51', '2026-06-30 10:55:51', 1, NULL, 'pending', NULL, NULL),
(34, 'peterparker123', '$2y$10$gVPL5KHjR7qCX.j5JITdbeq9qPPIwWd9y25tSYMyVcYLtRDZUC8RW', 'peterparker123@gmail.com', 'Peter', 'Parker', '09458965442', 'ganaderia', 'resident', 'active', '2026-06-30 05:32:08', '2026-06-30 05:40:49', '2026-06-30 13:40:49', 1, 'uploads/profiles/user_34_1782797619.jpg', 'verified', '2026-06-30 05:40:05', 2),
(35, 'monaaron12323', '$2y$10$VAOCuqAe4jj2fp3VPidDvu4LKLwYSw4AbsaJ99SGgygcAQYnstak.', 'monaaronmarcelo0924@gmail.com', 'Mon', 'Marcelo', '09123456789', 'brgy Atate', 'resident', 'active', '2026-06-30 05:53:48', '2026-06-30 05:53:48', NULL, 1, NULL, 'pending', NULL, NULL),
(36, 'daniel12345', '$2y$10$k89..AzcXqXemlkmJv.uHuhy5FXusLZ3URwLsxIjuEfHG05F.9u7G', 'daniel123@gmail.com', 'Daniel', 'Padilla', '091123312332', NULL, 'staff', 'active', '2026-06-30 06:11:03', '2026-06-30 06:11:30', '2026-06-30 14:11:30', 1, '', 'pending', NULL, NULL),
(37, 'thor123', '$2y$10$ezgq3Alu1FtsNVNc1e6Y8uWmS7IZog4WLQDdCOm3yjA0pk7zl9TO.', 'thor123@gmail.com', 'Thor', 'Son', '09113223245', NULL, 'staff', 'active', '2026-07-01 07:41:56', '2026-07-01 07:42:33', '2026-07-01 15:42:33', 1, '', 'pending', NULL, NULL),
(38, 'johncena123', '$2y$10$bejqOBdNVRb.haOXTcLezO2CmTks3AhGGJFTPRdvjbwfrfRFWen46', 'johncena123@gmail.com', 'John', 'Cena', '09923132233', 'brgy manacnac', 'resident', 'active', '2026-07-01 07:50:08', '2026-07-01 08:28:08', '2026-07-01 16:28:03', 1, 'uploads/profiles/user_38_1782894488.jpg', 'verified', '2026-07-01 08:10:50', 2),
(39, 'joshuagarcia123', '$2y$10$WDQDjwOND4XtVHcCRso6juchlK1C0jaYphTn63MEoYvKzTmgFSGru', 'joshuagarcia123@gmail.com', 'Joshua', 'Garcia', '09889213123', 'Brgy Malate', 'resident', 'active', '2026-07-02 02:28:46', '2026-07-02 05:31:24', '2026-07-02 13:31:24', 1, 'uploads/profiles/user_39_421025885d7b.jpg', 'verified', '2026-07-02 02:42:58', 2),
(40, 'jackiechan123', '$2y$10$VAPEj.RYj3I/OlPJuwmnGOaoOa.GxCS4RgE2.c13enu8MII4lX4pu', 'jackiechan123@gmail.com', 'Jackie', 'Chan', '09299939192', 'Brgy Aulo', 'resident', 'active', '2026-07-02 03:06:06', '2026-07-02 03:10:01', '2026-07-02 11:10:01', 1, 'uploads/profiles/user_40_764cdbd569a4.jpg', 'verified', '2026-07-02 03:09:41', 2),
(41, 'captainamerica123', '$2y$10$IKvCRtIZ2KokTTi/p94OBeThYpy2JzGgmsHM5V8wmbeazxnH5q7Ca', 'captainamerica123@gmail.com', 'Captain', 'America', '094549830032', NULL, 'staff', 'active', '2026-07-02 03:36:05', '2026-07-02 03:36:05', NULL, 1, '', 'pending', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `verification_requests`
--

CREATE TABLE `verification_requests` (
  `verification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `id_type` varchar(100) NOT NULL,
  `id_number` varchar(100) NOT NULL,
  `id_file_path` varchar(255) NOT NULL,
  `proof_of_residency_path` varchar(255) NOT NULL,
  `barangay_certificate_path` varchar(255) DEFAULT NULL,
  `barangay_name` varchar(100) NOT NULL,
  `complete_address` text NOT NULL,
  `submitted_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `review_date` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verification_requests`
--

INSERT INTO `verification_requests` (`verification_id`, `user_id`, `status`, `id_type`, `id_number`, `id_file_path`, `proof_of_residency_path`, `barangay_certificate_path`, `barangay_name`, `complete_address`, `submitted_date`, `reviewed_by`, `review_date`, `rejection_reason`, `remarks`) VALUES
(1, 1, 'verified', 'PhilSys ID', '99299292', 'uploads/verification/69de480909f8a_1776175113.png', 'uploads/verification/69de48090aca4_1776175113.png', 'uploads/verification/69de48090b52d_1776175113.png', 'Atate', 'Blk 4 lot 5 PagibigHousing', '2026-04-14 13:58:33', 2, '2026-04-14 14:00:02', '', 'Resident of Barangay Atate'),
(2, 18, 'verified', 'PhilSys ID', '93303939', 'uploads/verification/69df1faa7d035_1776230314.png', 'uploads/verification/69df1faa7dd95_1776230314.png', 'uploads/verification/69df1faa7e618_1776230314.png', 'Singalat', 'Purok 3 Brgy Singalat', '2026-04-15 05:18:34', 2, '2026-04-15 05:19:54', '', 'Resident of Barangay Singalat'),
(3, 19, 'rejected', 'PhilSys ID', '0939393', 'uploads/verification/69df25789807f_1776231800.png', 'uploads/verification/69df257898c90_1776231800.png', 'uploads/verification/69df257899762_1776231800.png', 'Atate', 'Purok 1 Brgy Atate Palayan City', '2026-04-15 05:43:20', 2, '2026-04-15 05:45:09', 'Mali ang Id please review', 'Resident of Barangay Atate'),
(4, 20, 'verified', 'Driver&#039;s License', '0991192929', 'uploads/verification/69df2e799b94d_1776234105.png', 'uploads/verification/69df2e799c6f3_1776234105.png', 'uploads/verification/69df2e799ceaf_1776234105.png', 'Manacnac', 'Brgy, Manacnac Palayan City', '2026-04-15 06:21:45', 2, '2026-04-15 06:22:05', '', 'Resident of Barangay Manacnac'),
(5, 14, 'verified', 'UMID', '200-199-00', 'uploads/verification/69e082cf7693d_1776321231.png', 'uploads/verification/69e082cf78645_1776321231.png', NULL, 'Caimito', 'Caimito, Palayan CCity', '2026-04-16 06:33:51', 2, '2026-04-16 06:36:31', '', 'Resident of Barangay Caimito'),
(6, 28, 'verified', 'PhilSys ID', '337377737234343', 'uploads/verification/6a0177d2b1bad_1778481106.jpg', 'uploads/verification/6a0177d2b217c_1778481106.png', NULL, 'Popolon(Pagas)', 'Zone 3 Brgy Popolon, Palayan City', '2026-05-11 06:31:46', 2, '2026-05-11 06:32:24', '', 'Resident of Barangay Popolon(Pagas)'),
(7, 30, 'verified', 'PhilSys ID', '9284728472837281', 'uploads/verification/6a420e17e9b30_1782713879.png', 'uploads/verification/6a420e17e9f21_1782713879.png', 'uploads/verification/6a420e17ea28d_1782713879.png', 'Maligaya', 'Brgy Maligaya', '2026-06-29 06:17:59', 2, '2026-06-29 06:19:33', '', 'Resident of Barangay Maligaya'),
(8, 34, 'verified', 'PhilSys ID', '234234123', 'uploads/verification/6a43550df1876_1782797581.jpg', 'uploads/verification/6a43550df1b60_1782797581.jpg', 'uploads/verification/6a43550df1f2e_1782797581.png', 'Ganaderia', 'Palayan City', '2026-06-30 05:33:01', 2, '2026-06-30 05:40:05', '', 'Resident of Barangay Ganaderia'),
(9, 38, 'verified', 'PhilSys ID', '968587404-4455', 'uploads/verification/6a44cb1b2288e_1782893339.jpg', '', NULL, 'Mapaet', 'Saphire Street, Mapaet, Palayan City, Nueva Ecija 3132', '2026-07-01 08:08:59', 2, '2026-07-01 08:10:50', '', 'Resident verification request submitted.'),
(10, 39, 'verified', 'PhilSys ID', 'weq', 'uploads/verification/6a45cd7c2441a_1782959484.jpg', '', NULL, 'Popolon(Pagas)', 'Saphire Street, Popolon(Pagas), Palayan City, Nueva Ecija 3132', '2026-07-02 02:31:24', 2, '2026-07-02 02:42:58', '', 'Resident verification request submitted.'),
(11, 40, 'verified', 'Senior Citizen ID', '7864553235454', 'uploads/verification/6a45d6542495f_1782961748.jpg', '', NULL, 'Aulo', 'Mapaet Street, Aulo, Palayan City, Nueva Ecija 3132', '2026-07-02 03:09:08', 2, '2026-07-02 03:09:41', '', 'Resident verification request submitted.');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `aics_cash_relief_details`
--
ALTER TABLE `aics_cash_relief_details`
  ADD PRIMARY KEY (`cash_relief_id`),
  ADD UNIQUE KEY `aics_id` (`aics_id`);

--
-- Indexes for table `aics_categories`
--
ALTER TABLE `aics_categories`
  ADD PRIMARY KEY (`aics_category_id`);

--
-- Indexes for table `aics_documents`
--
ALTER TABLE `aics_documents`
  ADD PRIMARY KEY (`aics_doc_id`),
  ADD KEY `aics_documents_ibfk_1` (`aics_id`);

--
-- Indexes for table `aics_document_requirements`
--
ALTER TABLE `aics_document_requirements`
  ADD PRIMARY KEY (`req_id`);

--
-- Indexes for table `aics_educational_details`
--
ALTER TABLE `aics_educational_details`
  ADD PRIMARY KEY (`educational_id`),
  ADD UNIQUE KEY `aics_id` (`aics_id`);

--
-- Indexes for table `aics_food_details`
--
ALTER TABLE `aics_food_details`
  ADD PRIMARY KEY (`food_id`),
  ADD UNIQUE KEY `aics_id` (`aics_id`);

--
-- Indexes for table `aics_funeral_details`
--
ALTER TABLE `aics_funeral_details`
  ADD PRIMARY KEY (`funeral_id`),
  ADD UNIQUE KEY `aics_id` (`aics_id`);

--
-- Indexes for table `aics_material_details`
--
ALTER TABLE `aics_material_details`
  ADD PRIMARY KEY (`material_id`),
  ADD UNIQUE KEY `aics_id` (`aics_id`);

--
-- Indexes for table `aics_medical_details`
--
ALTER TABLE `aics_medical_details`
  ADD PRIMARY KEY (`medical_id`),
  ADD UNIQUE KEY `aics_id` (`aics_id`);

--
-- Indexes for table `aics_requests`
--
ALTER TABLE `aics_requests`
  ADD PRIMARY KEY (`aics_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `aics_requests_backup`
--
ALTER TABLE `aics_requests_backup`
  ADD PRIMARY KEY (`aics_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_aics_status` (`status`);

--
-- Indexes for table `aics_transportation_details`
--
ALTER TABLE `aics_transportation_details`
  ADD PRIMARY KEY (`transportation_id`),
  ADD UNIQUE KEY `aics_id` (`aics_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `idx_appointments_date` (`appointment_date`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_email_logs_user` (`user_id`),
  ADD KEY `idx_email_logs_status` (`status`),
  ADD KEY `idx_email_logs_created` (`created_at`);

--
-- Indexes for table `housing_beneficiaries`
--
ALTER TABLE `housing_beneficiaries`
  ADD PRIMARY KEY (`beneficiary_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `interviewed_by` (`interviewed_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_housing_status` (`status`);

--
-- Indexes for table `housing_programs`
--
ALTER TABLE `housing_programs`
  ADD PRIMARY KEY (`program_id`),
  ADD UNIQUE KEY `program_code` (`program_code`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notifications_user` (`user_id`,`is_read`);

--
-- Indexes for table `request_documents`
--
ALTER TABLE `request_documents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `residents`
--
ALTER TABLE `residents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_resident_number` (`resident_number`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`),
  ADD UNIQUE KEY `service_code` (`service_code`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `service_categories`
--
ALTER TABLE `service_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_request_number` (`request_number`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- Indexes for table `staff_service_assignments`
--
ALTER TABLE `staff_service_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_staff_assignments_staff` (`staff_id`),
  ADD KEY `fk_staff_assignments_category` (`category_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `verification_requests`
--
ALTER TABLE `verification_requests`
  ADD PRIMARY KEY (`verification_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `aics_cash_relief_details`
--
ALTER TABLE `aics_cash_relief_details`
  MODIFY `cash_relief_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `aics_categories`
--
ALTER TABLE `aics_categories`
  MODIFY `aics_category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `aics_documents`
--
ALTER TABLE `aics_documents`
  MODIFY `aics_doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `aics_document_requirements`
--
ALTER TABLE `aics_document_requirements`
  MODIFY `req_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `aics_educational_details`
--
ALTER TABLE `aics_educational_details`
  MODIFY `educational_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `aics_food_details`
--
ALTER TABLE `aics_food_details`
  MODIFY `food_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `aics_funeral_details`
--
ALTER TABLE `aics_funeral_details`
  MODIFY `funeral_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `aics_material_details`
--
ALTER TABLE `aics_material_details`
  MODIFY `material_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `aics_medical_details`
--
ALTER TABLE `aics_medical_details`
  MODIFY `medical_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `aics_requests`
--
ALTER TABLE `aics_requests`
  MODIFY `aics_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `aics_requests_backup`
--
ALTER TABLE `aics_requests_backup`
  MODIFY `aics_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `aics_transportation_details`
--
ALTER TABLE `aics_transportation_details`
  MODIFY `transportation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=760;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `housing_beneficiaries`
--
ALTER TABLE `housing_beneficiaries`
  MODIFY `beneficiary_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `housing_programs`
--
ALTER TABLE `housing_programs`
  MODIFY `program_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `request_documents`
--
ALTER TABLE `request_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `service_categories`
--
ALTER TABLE `service_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `service_requests`
--
ALTER TABLE `service_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `staff_service_assignments`
--
ALTER TABLE `staff_service_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `verification_requests`
--
ALTER TABLE `verification_requests`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `aics_cash_relief_details`
--
ALTER TABLE `aics_cash_relief_details`
  ADD CONSTRAINT `aics_cash_relief_details_ibfk_1` FOREIGN KEY (`aics_id`) REFERENCES `aics_requests` (`aics_id`) ON DELETE CASCADE;

--
-- Constraints for table `aics_documents`
--
ALTER TABLE `aics_documents`
  ADD CONSTRAINT `aics_documents_ibfk_1` FOREIGN KEY (`aics_id`) REFERENCES `aics_requests` (`aics_id`) ON DELETE CASCADE;

--
-- Constraints for table `aics_educational_details`
--
ALTER TABLE `aics_educational_details`
  ADD CONSTRAINT `aics_educational_details_ibfk_1` FOREIGN KEY (`aics_id`) REFERENCES `aics_requests` (`aics_id`) ON DELETE CASCADE;

--
-- Constraints for table `aics_food_details`
--
ALTER TABLE `aics_food_details`
  ADD CONSTRAINT `aics_food_details_ibfk_1` FOREIGN KEY (`aics_id`) REFERENCES `aics_requests` (`aics_id`) ON DELETE CASCADE;

--
-- Constraints for table `aics_funeral_details`
--
ALTER TABLE `aics_funeral_details`
  ADD CONSTRAINT `aics_funeral_details_ibfk_1` FOREIGN KEY (`aics_id`) REFERENCES `aics_requests` (`aics_id`) ON DELETE CASCADE;

--
-- Constraints for table `aics_material_details`
--
ALTER TABLE `aics_material_details`
  ADD CONSTRAINT `aics_material_details_ibfk_1` FOREIGN KEY (`aics_id`) REFERENCES `aics_requests` (`aics_id`) ON DELETE CASCADE;

--
-- Constraints for table `aics_medical_details`
--
ALTER TABLE `aics_medical_details`
  ADD CONSTRAINT `aics_medical_details_ibfk_1` FOREIGN KEY (`aics_id`) REFERENCES `aics_requests` (`aics_id`) ON DELETE CASCADE;

--
-- Constraints for table `aics_requests`
--
ALTER TABLE `aics_requests`
  ADD CONSTRAINT `aics_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `aics_requests_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `aics_requests_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `aics_requests_backup`
--
ALTER TABLE `aics_requests_backup`
  ADD CONSTRAINT `aics_requests_backup_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `aics_requests_backup_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `aics_requests_backup_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `aics_transportation_details`
--
ALTER TABLE `aics_transportation_details`
  ADD CONSTRAINT `aics_transportation_details_ibfk_1` FOREIGN KEY (`aics_id`) REFERENCES `aics_requests` (`aics_id`) ON DELETE CASCADE;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`),
  ADD CONSTRAINT `documents_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `housing_beneficiaries`
--
ALTER TABLE `housing_beneficiaries`
  ADD CONSTRAINT `housing_beneficiaries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `housing_beneficiaries_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `housing_programs` (`program_id`),
  ADD CONSTRAINT `housing_beneficiaries_ibfk_3` FOREIGN KEY (`interviewed_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `housing_beneficiaries_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`category_id`);

--
-- Constraints for table `staff_service_assignments`
--
ALTER TABLE `staff_service_assignments`
  ADD CONSTRAINT `fk_staff_assignments_category` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`category_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_staff_assignments_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `verification_requests`
--
ALTER TABLE `verification_requests`
  ADD CONSTRAINT `verification_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `verification_requests_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
