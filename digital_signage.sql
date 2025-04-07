CREATE DATABASE IF NOT EXISTS `digital_signage`;
USE `digital_signage`;

CREATE TABLE IF NOT EXISTS `media` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` enum('image','video','webpage') NOT NULL,
  `path` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `duration` int(11) DEFAULT 10,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_local` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `ticker_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message` text NOT NULL,
  `type` enum('default','schedule','custom') NOT NULL DEFAULT 'default',
  `is_active` tinyint(1) DEFAULT 1,
  `target_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `display_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
);

INSERT INTO `media` (`name`, `type`, `path`, `url`, `duration`, `display_order`, `is_active`, `is_local`) 
VALUES ('Schedule System', 'webpage', NULL,'http://localhost/smart_schedule/view_schedules.php', 90, 0, 1, 1);

-- Insert default ticker message for multimedia content (no target_url)
INSERT INTO `ticker_messages` (`message`, `type`, `is_active`, `target_url`, `display_order`) 
VALUES ('Welcome to our Digital Signage System. For any inquiries, please contact the IT Department.', 'default', 1, NULL, 1);

-- Insert schedule system specific ticker message
INSERT INTO `ticker_messages` (`message`, `type`, `is_active`, `target_url`, `display_order`) 
VALUES ('If you notice any inaccuracies in the current schedule, please report them to the lab administrator office.', 'schedule', 1, 'http://localhost/smart_schedule/view_schedules.php', 3);