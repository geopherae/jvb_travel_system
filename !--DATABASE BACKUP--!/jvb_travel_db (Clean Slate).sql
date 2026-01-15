-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 07, 2025 at 04:09 PM
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
-- Database: `jvb_travel_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_accounts`
--

CREATE TABLE `admin_accounts` (
  `id` int(10) UNSIGNED NOT NULL,
  `admin_photo` varchar(255) DEFAULT NULL,
  `first_name` varchar(30) NOT NULL,
  `last_name` varchar(30) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone_number` varchar(20) DEFAULT NULL,
  `email` varchar(50) NOT NULL,
  `messenger_link` varchar(255) DEFAULT NULL,
  `is_primary_contact` tinyint(1) DEFAULT 0,
  `role` varchar(50) DEFAULT 'Read-Only',
  `admin_profile` longtext DEFAULT NULL CHECK (json_valid(`admin_profile`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `session_timeout` int(11) DEFAULT 30
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_accounts`
--

INSERT INTO `admin_accounts` (`id`, `admin_photo`, `first_name`, `last_name`, `username`, `password_hash`, `created_at`, `phone_number`, `email`, `messenger_link`, `is_primary_contact`, `role`, `admin_profile`, `is_active`, `session_timeout`) VALUES
(1, 'superadmin.jpg', 'Christopher', 'Branzuela', 'chriscahill', '$2y$10$Z4HBi.OVFtIZCfsFDXj6huZ/Bh.fwxrPQxfu0kCEmkBsKRsnQhGOa', '2025-09-18 13:56:58', '09951478944', 'chrisgeph@gmail.com', NULL, 1, 'superadmin', NULL, 1, 120),
(2, 'admin_68e006ecbb5d78.21670144.png', 'Jennifer', 'Belleza', 'jvb', '$2y$10$nIRQV54DJqo8h2lHP5EZmeHxsa.bohfF8okcx8EsubXNPEdmDoZAe', '2025-09-24 09:43:58', '09123456789', 'jvb@jvb.com', '', 0, 'admin', '{\"bio\":\"\"}', 1, 120);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `actor_id` int(10) UNSIGNED NOT NULL,
  `actor_role` varchar(50) NOT NULL,
  `target_id` int(10) UNSIGNED DEFAULT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `changes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`changes`)),
  `severity` enum('Low','Medium','High','Critical') DEFAULT 'Low',
  `module` varchar(100) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `session_id` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `kpi_tag` varchar(255) DEFAULT NULL,
  `business_impact` text DEFAULT NULL,
  `kpi_subtag` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `checklist_templates`
--

CREATE TABLE `checklist_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `checklist_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`checklist_json`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `checklist_templates`
--

INSERT INTO `checklist_templates` (`id`, `name`, `checklist_json`, `created_at`) VALUES
(1, 'Client Onboarding & Travel Flow', '[\r\n    {\r\n      \"id\": \"take_survey\",\r\n      \"label\": \"Take Survey\",\r\n      \"description\": \"Help us understand your travel preferences.\",\r\n      \"status_key\": \"survey_taken\",\r\n      \"required\": true,\r\n      \"visible_to\": [\"client\"],\r\n      \"depends_on\": [],\r\n      \"points\": 10,\r\n      \"action_url\": \"/client/survey\"\r\n    },\r\n    {\r\n      \"id\": \"upload_id\",\r\n      \"label\": \"Upload ID/Passport\",\r\n      \"description\": \"Required for booking and verification.\",\r\n      \"status_key\": \"id_uploaded\",\r\n      \"required\": true,\r\n      \"visible_to\": [\"client\"],\r\n      \"depends_on\": [\"survey_taken\"],\r\n      \"points\": 15,\r\n      \"action_url\": \"/client/upload-id\"\r\n    },\r\n    {\r\n      \"id\": \"approve_id\",\r\n      \"label\": \"Have ID/Passport be Approved\",\r\n      \"description\": \"Admin must verify your document.\",\r\n      \"status_key\": \"id_approved\",\r\n      \"required\": true,\r\n      \"visible_to\": [\"client\"],\r\n      \"depends_on\": [\"id_uploaded\"],\r\n      \"points\": 20\r\n    },\r\n    {\r\n      \"id\": \"confirm_itinerary\",\r\n      \"label\": \"Confirm Itinerary\",\r\n      \"description\": \"Review and confirm your travel plan.\",\r\n      \"status_key\": \"itinerary_confirmed\",\r\n      \"required\": true,\r\n      \"visible_to\": [\"client\"],\r\n      \"depends_on\": [\"id_approved\"],\r\n      \"points\": 10,\r\n      \"action_url\": \"/client/itinerary\"\r\n    },\r\n    {\r\n      \"id\": \"upload_photos\",\r\n      \"label\": \"Upload Travel Photos\",\r\n      \"description\": \"Share your memories with us!\",\r\n      \"status_key\": \"photos_uploaded\",\r\n      \"required\": false,\r\n      \"visible_to\": [\"client\"],\r\n      \"depends_on\": [\"itinerary_confirmed\"],\r\n      \"points\": 15,\r\n      \"action_url\": \"/client/gallery\"\r\n    },\r\n    {\r\n      \"id\": \"trip_survey\",\r\n      \"label\": \"Take Trip Completed Survey\",\r\n      \"description\": \"Tell us how your trip went.\",\r\n      \"status_key\": \"trip_survey_taken\",\r\n      \"required\": false,\r\n      \"visible_to\": [\"client\"],\r\n      \"depends_on\": [\"photos_uploaded\"],\r\n      \"points\": 10,\r\n      \"action_url\": \"/client/trip-survey\"\r\n    },\r\n    {\r\n      \"id\": \"visit_facebook\",\r\n      \"label\": \"Check out JVB Travel & Tours Facebook Page\",\r\n      \"description\": \"Stay connected and see other travelers’ stories.\",\r\n      \"status_key\": \"facebook_visited\",\r\n      \"required\": false,\r\n      \"visible_to\": [\"client\"],\r\n      \"depends_on\": [],\r\n      \"points\": 5,\r\n      \"action_url\": \"https://www.facebook.com/JVBTravelTours\"\r\n    }\r\n  ]', '2025-09-21 11:10:50');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `assigned_admin_id` int(10) UNSIGNED DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `client_profile_photo` varchar(255) DEFAULT NULL,
  `access_code` varchar(100) NOT NULL,
  `assigned_package_id` int(11) DEFAULT NULL,
  `booking_number` varchar(100) DEFAULT NULL,
  `trip_date_start` date DEFAULT NULL,
  `trip_date_end` date DEFAULT NULL,
  `booking_date` date DEFAULT NULL,
  `status` enum('Trip Completed','Confirmed','Trip Ongoing','Awaiting Docs','Resubmit Files','Under Review','Cancelled','No Assigned Package','Archived') DEFAULT 'Awaiting Docs',
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `checklist_template_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_checklist_progress`
--

CREATE TABLE `client_checklist_progress` (
  `client_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `progress_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`progress_json`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_itinerary`
--

CREATE TABLE `client_itinerary` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `itinerary_json` text NOT NULL,
  `is_confirmed` tinyint(1) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_trip_photos`
--

CREATE TABLE `client_trip_photos` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `assigned_package_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(512) NOT NULL,
  `mime_type` enum('image/jpeg','image/png') NOT NULL,
  `caption` text DEFAULT NULL,
  `document_type` varchar(50) DEFAULT 'Trip Photo',
  `compression_status` varchar(50) DEFAULT 'original',
  `document_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `day` tinyint(3) UNSIGNED DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `status_updated_by` varchar(100) DEFAULT NULL,
  `location_tag` varchar(100) DEFAULT NULL,
  `scope_tag` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `photo_path` text DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `thread_id` int(11) DEFAULT NULL,
  `sender_id` int(10) UNSIGNED NOT NULL,
  `sender_type` enum('admin','client') NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `recipient_type` enum('admin','client') NOT NULL,
  `message_text` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `reply_to_id` int(11) DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL CHECK (json_valid(`metadata_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `recipient_type` varchar(50) NOT NULL,
  `recipient_id` int(10) UNSIGNED NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `action_url` varchar(255) DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `status` enum('unread','read','archived') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `expires_at` datetime DEFAULT NULL,
  `dismissed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `photo_tags`
--

CREATE TABLE `photo_tags` (
  `id` int(11) NOT NULL,
  `tag_name` varchar(100) NOT NULL,
  `tag_type` enum('location','scope') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `photo_tags_map`
--

CREATE TABLE `photo_tags_map` (
  `photo_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `actor_role` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `severity_level` varchar(20) DEFAULT 'info',
  `module` varchar(50) DEFAULT 'general',
  `created_at` datetime DEFAULT current_timestamp(),
  `kpi_tag` varchar(50) NOT NULL,
  `business_impact` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `threads`
--

CREATE TABLE `threads` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `recipient_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tour_packages`
--

CREATE TABLE `tour_packages` (
  `id` int(11) NOT NULL,
  `tour_cover_image` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `package_name` varchar(255) NOT NULL,
  `package_description` text DEFAULT NULL,
  `inclusions_json` text DEFAULT NULL,
  `tour_inclusions` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `day_duration` int(11) DEFAULT 0,
  `night_duration` int(11) DEFAULT 0,
  `origin` varchar(100) DEFAULT NULL,
  `destination` varchar(100) DEFAULT NULL,
  `is_favorite` tinyint(1) DEFAULT 0,
  `checklist_template_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tour_package_itinerary`
--

CREATE TABLE `tour_package_itinerary` (
  `id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `itinerary_json` text NOT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `uploaded_files`
--

CREATE TABLE `uploaded_files` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` text NOT NULL,
  `document_type` varchar(100) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `document_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `admin_comments` text DEFAULT NULL,
  `status_updated_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_survey_status`
--

CREATE TABLE `user_survey_status` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_role` enum('client','admin') NOT NULL,
  `survey_type` enum('first_login','trip_complete','admin_weekly_survey') NOT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `response_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '{"survey_type": null, "responses": {}, "submitted_at": null}' CHECK (json_valid(`response_payload`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_accounts`
--
ALTER TABLE `admin_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `checklist_templates`
--
ALTER TABLE `checklist_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `access_code` (`access_code`),
  ADD KEY `fk_assigned_admin` (`assigned_admin_id`);

--
-- Indexes for table `client_checklist_progress`
--
ALTER TABLE `client_checklist_progress`
  ADD PRIMARY KEY (`client_id`,`template_id`),
  ADD UNIQUE KEY `client_id` (`client_id`,`template_id`);

--
-- Indexes for table `client_itinerary`
--
ALTER TABLE `client_itinerary`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `client_trip_photos`
--
ALTER TABLE `client_trip_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client_package` (`client_id`,`assigned_package_id`),
  ADD KEY `idx_status` (`document_status`),
  ADD KEY `assigned_package_id` (`assigned_package_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_thread_id` (`thread_id`),
  ADD KEY `idx_sender` (`sender_id`,`sender_type`),
  ADD KEY `idx_recipient` (`recipient_id`,`recipient_type`),
  ADD KEY `idx_read` (`read_at`),
  ADD KEY `idx_deleted` (`deleted_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `photo_tags`
--
ALTER TABLE `photo_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tag_name` (`tag_name`);

--
-- Indexes for table `photo_tags_map`
--
ALTER TABLE `photo_tags_map`
  ADD PRIMARY KEY (`photo_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `action_type` (`action_type`);

--
-- Indexes for table `threads`
--
ALTER TABLE `threads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_conversation` (`user_id`,`user_type`,`recipient_id`,`recipient_type`);

--
-- Indexes for table `tour_packages`
--
ALTER TABLE `tour_packages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tour_package_itinerary`
--
ALTER TABLE `tour_package_itinerary`
  ADD PRIMARY KEY (`id`),
  ADD KEY `package_id` (`package_id`);

--
-- Indexes for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `user_survey_status`
--
ALTER TABLE `user_survey_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_survey` (`user_id`,`user_role`,`survey_type`,`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_accounts`
--
ALTER TABLE `admin_accounts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `checklist_templates`
--
ALTER TABLE `checklist_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_itinerary`
--
ALTER TABLE `client_itinerary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_trip_photos`
--
ALTER TABLE `client_trip_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `photo_tags`
--
ALTER TABLE `photo_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `threads`
--
ALTER TABLE `threads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tour_packages`
--
ALTER TABLE `tour_packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tour_package_itinerary`
--
ALTER TABLE `tour_package_itinerary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `user_survey_status`
--
ALTER TABLE `user_survey_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `client_itinerary`
--
ALTER TABLE `client_itinerary`
  ADD CONSTRAINT `client_itinerary_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_trip_photos`
--
ALTER TABLE `client_trip_photos`
  ADD CONSTRAINT `client_trip_photos_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_trip_photos_ibfk_2` FOREIGN KEY (`assigned_package_id`) REFERENCES `tour_packages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_thread_id` FOREIGN KEY (`thread_id`) REFERENCES `threads` (`id`),
  ADD CONSTRAINT `fk_recipient_client` FOREIGN KEY (`recipient_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sender_admin` FOREIGN KEY (`sender_id`) REFERENCES `admin_accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `photo_tags_map`
--
ALTER TABLE `photo_tags_map`
  ADD CONSTRAINT `fk_photo_tags_map_photo` FOREIGN KEY (`photo_id`) REFERENCES `client_trip_photos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `photo_tags_map_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `photo_tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tour_package_itinerary`
--
ALTER TABLE `tour_package_itinerary`
  ADD CONSTRAINT `tour_package_itinerary_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `tour_packages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  ADD CONSTRAINT `uploaded_files_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
