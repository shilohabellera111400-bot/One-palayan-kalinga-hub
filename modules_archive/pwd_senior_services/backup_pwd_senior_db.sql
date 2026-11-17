-- Palayan Kalinga Hub - PWD & Senior Citizens Schema & Data Archive
-- Created: 2026-11-10 22:02:39

DROP TABLE IF EXISTS `senior_applications`;
CREATE TABLE `senior_applications` (
  `application_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `tracking_number` varchar(50) NOT NULL,
  `application_type` enum('id_issuance','social_pension','purchase_booklet','financial_aid') NOT NULL,
  `details` text DEFAULT NULL,
  `osca_id_number` varchar(50) DEFAULT NULL,
  `booklet_number` varchar(50) DEFAULT NULL,
  `valid_id_path` varchar(255) DEFAULT NULL,
  `id_picture_path` varchar(255) DEFAULT NULL,
  `barangay_indigency_path` varchar(255) DEFAULT NULL,
  `status` enum('submitted','reviewing','approved','rejected','released') DEFAULT 'submitted',
  `rejection_reason` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`application_id`),
  UNIQUE KEY `tracking_number` (`tracking_number`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `senior_applications` (`application_id`, `user_id`, `resident_id`, `tracking_number`, `application_type`, `details`, `osca_id_number`, `booklet_number`, `valid_id_path`, `id_picture_path`, `barangay_indigency_path`, `status`, `rejection_reason`, `remarks`, `reviewed_by`, `created_at`, `updated_at`) VALUES ('2', '52', NULL, 'SC-2026-903A0', 'id_issuance', 'Application for Senior Citizen ID and Purchase Booklet', 'OSCA-2026-0046', 'BK-2026-0331', 'uploads/senior/6aa395f08f824_1789105648.png', 'uploads/senior/6aa395f08fd2d_1789105648.png', 'uploads/senior/6aa395f09016d_1789105648.png', 'approved', '', 'Step 5 | [ADMIN OVERRIDE: szcszczcszcszc]', '15', '2026-09-11 13:47:28', '2026-11-10 01:20:09');

DROP TABLE IF EXISTS `pwd_applications`;
CREATE TABLE `pwd_applications` (
  `application_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `tracking_number` varchar(50) NOT NULL,
  `application_type` enum('pwd_id','assistive_device','medical_aid') NOT NULL,
  `disability_type` varchar(100) NOT NULL,
  `device_requested` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `pwd_id_number` varchar(50) DEFAULT NULL,
  `booklet_number` varchar(50) DEFAULT NULL,
  `medical_certificate_path` varchar(255) DEFAULT NULL,
  `valid_id_path` varchar(255) DEFAULT NULL,
  `id_picture_path` varchar(255) DEFAULT NULL,
  `status` enum('submitted','reviewing','approved','rejected','released') DEFAULT 'submitted',
  `rejection_reason` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`application_id`),
  UNIQUE KEY `tracking_number` (`tracking_number`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pwd_applications` (`application_id`, `user_id`, `resident_id`, `tracking_number`, `application_type`, `disability_type`, `device_requested`, `details`, `pwd_id_number`, `booklet_number`, `medical_certificate_path`, `valid_id_path`, `id_picture_path`, `status`, `rejection_reason`, `remarks`, `reviewed_by`, `created_at`, `updated_at`) VALUES ('2', '52', NULL, 'PWD-2026-70DAD', 'assistive_device', 'Learning Disability', 'Hearing Aid', 'test', NULL, NULL, 'uploads/pwd/6aa3924e6dda7_1789104718.png', 'uploads/pwd/6aa3924e6f2de_1789104718.png', 'uploads/pwd/6aa3924e701e5_1789104718.png', 'submitted', NULL, NULL, NULL, '2026-09-11 13:31:58', '2026-09-11 13:31:58');

