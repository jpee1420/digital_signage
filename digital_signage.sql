CREATE DATABASE IF NOT EXISTS `digital_signage`;
USE `digital_signage`;

CREATE TABLE IF NOT EXISTS `media`( 
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `type` ENUM('image', 'video', 'webpage') NOT NULL,
    `path` VARCHAR(255) NOT NULL,
    `url` VARCHAR(255) NOT NULL,
    `duration` INT DEFAULT 10,
    `display_order` INT DEFAULT 0,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
);