-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 11, 2026 at 10:06 AM
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
-- Database: `heritagelink_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(150) DEFAULT NULL,
  `target_type` varchar(100) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `detail` text DEFAULT NULL,
  `logged_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contributions`
--

CREATE TABLE `contributions` (
  `contribution_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `type` enum('photograph','document','audio','event','oral_history') DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `status` enum('draft','pending','published') NOT NULL DEFAULT 'pending',
  `privacy` enum('public','members','private') NOT NULL DEFAULT 'members',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `conversation_id` int(11) NOT NULL,
  `user_id_1` int(11) NOT NULL,
  `user_id_2` int(11) NOT NULL,
  `last_message_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `family_members`
--

CREATE TABLE `family_members` (
  `member_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `preferred_name` varchar(100) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `dob_approximate` tinyint(1) NOT NULL DEFAULT 0,
  `date_of_death` date DEFAULT NULL,
  `dod_approximate` tinyint(1) NOT NULL DEFAULT 0,
  `birthplace` varchar(200) DEFAULT NULL,
  `occupation` varchar(150) DEFAULT NULL,
  `short_bio` text DEFAULT NULL,
  `quarter_id` int(11) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `source_of_info` varchar(255) DEFAULT NULL,
  `privacy` enum('public','members','private') NOT NULL DEFAULT 'members',
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `added_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `heritage_records`
--

CREATE TABLE `heritage_records` (
  `record_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `type` enum('event','document','photograph','audio','oral_history') DEFAULT NULL,
  `era` enum('pre_colonial','colonial','modern') DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `date_approx` tinyint(1) NOT NULL DEFAULT 0,
  `location` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `contributed_by` int(11) DEFAULT NULL,
  `privacy` enum('public','members','private') NOT NULL DEFAULT 'members',
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `heritage_records`
--

INSERT INTO `heritage_records` (`record_id`, `title`, `type`, `era`, `event_date`, `date_approx`, `location`, `description`, `source`, `contributed_by`, `privacy`, `verified`, `created_at`) VALUES
(1, 'Founding of Ekpor Village', 'event', 'pre_colonial', NULL, 1, 'Ekpor Village, Manyu Division', 'The founding ancestor of Ekpor Village settled\r\n     in the area and had three sons: Mformem, Anamu,\r\n     and Atebe Tambi. Each son established a distinct\r\n     family quarter within the village, giving rise\r\n     to the five quarters that define the community\r\n     structure today.', 'Oral account — Prof. Mbu Robinson, 2026', 1, 'members', 1, '2026-06-11 08:02:51'),
(2, 'NKOCKENOCK 2 Clan Settlement', 'event', 'pre_colonial', NULL, 1, 'Between Manyu River and Kendem Village', 'Members of the NKOCKENOCK 2 clan, including the\r\n     people of Ekpor Village, originally settled\r\n     together in a forested area located between the\r\n     Manyu River and Kendem village, reflecting strong\r\n     kinship ties among members of the same clan.', 'Oral account — Prof. Mbu Robinson, 2026', 1, 'members', 1, '2026-06-11 08:02:51'),
(3, 'Colonial Relocation to Present Location', 'event', 'colonial', NULL, 1, 'Manyu Division, South West Region, Cameroon', 'During the colonial period, the German\r\n     administration influenced the migration of Ekpor\r\n     Village and related communities from their\r\n     original settlement to their present locations.\r\n     This relocation was undertaken to facilitate\r\n     development and the construction of road networks\r\n     linking the grassfields region with Mamfe.', 'Oral account — Prof. Mbu Robinson, 2026', 1, 'members', 1, '2026-06-11 08:02:51'),
(4, 'Population Record — 1953', 'document', 'modern', '1953-01-01', 1, 'Ekpor Village, Manyu Division', 'Historical demographic records indicate that\r\n     Ekpor Village had a population of approximately\r\n     254 inhabitants in 1953, consisting mainly of\r\n     members of the Banyang ethnic group.', 'Dictionnaire des villages de la Manyu,\r\n     Centre ORSTOM de Yaoundé, 1973', 1, 'public', 1, '2026-06-11 08:02:51'),
(5, 'Population Record — 1967', 'document', 'modern', '1967-01-01', 1, 'Ekpor Village, Manyu Division', 'By 1967 the population of Ekpor Village had\r\n     grown to approximately 344 inhabitants,\r\n     representing gradual community growth over\r\n     the preceding decade.', 'Dictionnaire des villages de la Manyu,\r\n     Centre ORSTOM de Yaoundé, 1973', 1, 'public', 1, '2026-06-11 08:02:51');

-- --------------------------------------------------------

--
-- Table structure for table `media_files`
--

CREATE TABLE `media_files` (
  `file_id` int(11) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `file_type` enum('photo','document','audio') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `is_tree_initiated` tinyint(1) NOT NULL DEFAULT 0,
  `tree_member_id` int(11) DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pending_edits`
--

CREATE TABLE `pending_edits` (
  `edit_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `field_changed` varchar(100) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quarters`
--

CREATE TABLE `quarters` (
  `quarter_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `founded_by` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quarters`
--

INSERT INTO `quarters` (`quarter_id`, `name`, `founded_by`, `description`, `created_at`) VALUES
(1, 'Esongmbichang', 'Anamu', 'One of three quarters established by Anamu,\r\n     second son of the founding ancestor of Ekpor Village.', '2026-06-11 08:02:50'),
(2, 'Mformem', 'Mformem', 'Quarter established by Mformem, eldest son\r\n     of the founding ancestor of Ekpor Village.', '2026-06-11 08:02:50'),
(3, 'Tabiju', 'Anamu', 'One of three quarters established by Anamu,\r\n     second son of the founding ancestor of Ekpor Village.', '2026-06-11 08:02:50'),
(4, 'Atebe Tambi', 'Atebe Tambi', 'Quarter established by Atebe Tambi, youngest\r\n     son of the founding ancestor of Ekpor Village.', '2026-06-11 08:02:50'),
(5, 'N\'net Akwa', 'Anamu', 'One of three quarters established by Anamu,\r\n     second son of the founding ancestor of Ekpor Village.', '2026-06-11 08:02:50');

-- --------------------------------------------------------

--
-- Table structure for table `relationships`
--

CREATE TABLE `relationships` (
  `relationship_id` int(11) NOT NULL,
  `member_id_1` int(11) NOT NULL,
  `member_id_2` int(11) NOT NULL,
  `type` enum('parent','child','spouse','sibling') NOT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `preferred_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `role` enum('admin','member') NOT NULL DEFAULT 'member',
  `quarter_id` int(11) DEFAULT NULL,
  `village_of_origin` varchar(150) NOT NULL DEFAULT 'Ekpor Village',
  `is_ekpor_member` tinyint(1) NOT NULL DEFAULT 1,
  `profile_photo` varchar(255) DEFAULT NULL,
  `privacy` enum('public','members','private') NOT NULL DEFAULT 'members',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `preferred_name`, `email`, `password_hash`, `phone`, `role`, `quarter_id`, `village_of_origin`, `is_ekpor_member`, `profile_photo`, `privacy`, `created_at`) VALUES
(1, 'HeritageLink Admin', NULL, 'admin@heritagelink.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'admin', NULL, 'Ekpor Village', 1, NULL, 'members', '2026-06-11 08:02:51');

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `setting_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `direct_messages` tinyint(1) NOT NULL DEFAULT 1,
  `message_previews` tinyint(1) NOT NULL DEFAULT 1,
  `alert_sound` varchar(50) NOT NULL DEFAULT 'pulse',
  `quiet_hours` tinyint(1) NOT NULL DEFAULT 0,
  `quiet_start` time NOT NULL DEFAULT '22:00:00',
  `quiet_end` time NOT NULL DEFAULT '07:00:00',
  `global_directory` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`setting_id`, `user_id`, `direct_messages`, `message_previews`, `alert_sound`, `quiet_hours`, `quiet_start`, `quiet_end`, `global_directory`) VALUES
(1, 1, 1, 1, 'pulse', 0, '22:00:00', '07:00:00', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `contributions`
--
ALTER TABLE `contributions`
  ADD PRIMARY KEY (`contribution_id`),
  ADD KEY `submitted_by` (`submitted_by`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`conversation_id`),
  ADD UNIQUE KEY `unique_pair` (`user_id_1`,`user_id_2`),
  ADD KEY `user_id_2` (`user_id_2`);

--
-- Indexes for table `family_members`
--
ALTER TABLE `family_members`
  ADD PRIMARY KEY (`member_id`),
  ADD KEY `quarter_id` (`quarter_id`),
  ADD KEY `added_by` (`added_by`);

--
-- Indexes for table `heritage_records`
--
ALTER TABLE `heritage_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `contributed_by` (`contributed_by`);

--
-- Indexes for table `media_files`
--
ALTER TABLE `media_files`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `record_id` (`record_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `conversation_id` (`conversation_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `tree_member_id` (`tree_member_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `pending_edits`
--
ALTER TABLE `pending_edits`
  ADD PRIMARY KEY (`edit_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `submitted_by` (`submitted_by`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `quarters`
--
ALTER TABLE `quarters`
  ADD PRIMARY KEY (`quarter_id`);

--
-- Indexes for table `relationships`
--
ALTER TABLE `relationships`
  ADD PRIMARY KEY (`relationship_id`),
  ADD KEY `member_id_1` (`member_id_1`),
  ADD KEY `member_id_2` (`member_id_2`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD KEY `quarter_id` (`quarter_id`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `unique_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contributions`
--
ALTER TABLE `contributions`
  MODIFY `contribution_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `conversation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `family_members`
--
ALTER TABLE `family_members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `heritage_records`
--
ALTER TABLE `heritage_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `media_files`
--
ALTER TABLE `media_files`
  MODIFY `file_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pending_edits`
--
ALTER TABLE `pending_edits`
  MODIFY `edit_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quarters`
--
ALTER TABLE `quarters`
  MODIFY `quarter_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `relationships`
--
ALTER TABLE `relationships`
  MODIFY `relationship_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `contributions`
--
ALTER TABLE `contributions`
  ADD CONSTRAINT `contributions_ibfk_1` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`user_id_1`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`user_id_2`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `family_members`
--
ALTER TABLE `family_members`
  ADD CONSTRAINT `family_members_ibfk_1` FOREIGN KEY (`quarter_id`) REFERENCES `quarters` (`quarter_id`),
  ADD CONSTRAINT `family_members_ibfk_2` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `heritage_records`
--
ALTER TABLE `heritage_records`
  ADD CONSTRAINT `heritage_records_ibfk_1` FOREIGN KEY (`contributed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `media_files`
--
ALTER TABLE `media_files`
  ADD CONSTRAINT `media_files_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `heritage_records` (`record_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `media_files_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `family_members` (`member_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `media_files_ibfk_3` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`conversation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `messages_ibfk_4` FOREIGN KEY (`tree_member_id`) REFERENCES `family_members` (`member_id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `pending_edits`
--
ALTER TABLE `pending_edits`
  ADD CONSTRAINT `pending_edits_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `family_members` (`member_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pending_edits_ibfk_2` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `pending_edits_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `relationships`
--
ALTER TABLE `relationships`
  ADD CONSTRAINT `relationships_ibfk_1` FOREIGN KEY (`member_id_1`) REFERENCES `family_members` (`member_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `relationships_ibfk_2` FOREIGN KEY (`member_id_2`) REFERENCES `family_members` (`member_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`quarter_id`) REFERENCES `quarters` (`quarter_id`) ON DELETE SET NULL;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
