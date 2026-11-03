<?php
/**
 * ساختار جداول پایگاه داده
 */
return [
"CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `display_name` VARCHAR(120) NOT NULL DEFAULT '',
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'author',
  `avatar` VARCHAR(255) NULL,
  `bio` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `description` TEXT NULL,
  `color` VARCHAR(20) NOT NULL DEFAULT '#ff1f3d',
  `icon` VARCHAR(32) NOT NULL DEFAULT '📰',
  `sort_order` INT NOT NULL DEFAULT 0,
  `show_on_home` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_cat_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `excerpt` TEXT NULL,
  `content` MEDIUMTEXT NULL,
  `cover` VARCHAR(255) NULL,
  `category_id` INT UNSIGNED NULL,
  `author_id` INT UNSIGNED NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_breaking` TINYINT(1) NOT NULL DEFAULT 0,
  `allow_comments` TINYINT(1) NOT NULL DEFAULT 1,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `source_name` VARCHAR(120) NULL,
  `source_url` VARCHAR(255) NULL,
  `meta_title` VARCHAR(255) NULL,
  `meta_desc` VARCHAR(320) NULL,
  `telegram_sent` TINYINT(1) NOT NULL DEFAULT 0,
  `published_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_post_slug` (`slug`),
  KEY `k_status_pub` (`status`, `published_at`),
  KEY `k_cat` (`category_id`),
  KEY `k_views` (`views`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `tags` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_tag_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `post_tags` (
  `post_id` INT UNSIGNED NOT NULL,
  `tag_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`post_id`, `tag_id`),
  KEY `k_tag` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `comments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `parent_id` INT UNSIGNED NULL,
  `user_id` INT UNSIGNED NULL,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NULL,
  `body` TEXT NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `ip` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `k_post` (`post_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `reactions` (
  `post_id` INT UNSIGNED NOT NULL,
  `emoji` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `cnt` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`post_id`, `emoji`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `reaction_log` (
  `post_id` INT UNSIGNED NOT NULL,
  `ip_hash` CHAR(64) NOT NULL,
  `emoji` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`post_id`, `ip_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `ads` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(190) NOT NULL,
  `position` VARCHAR(40) NOT NULL,
  `format` VARCHAR(20) NOT NULL DEFAULT 'card',
  `theme` VARCHAR(30) NOT NULL DEFAULT 'neon',
  `accent` VARCHAR(20) NOT NULL DEFAULT '#ff1f3d',
  `image` VARCHAR(255) NULL,
  `link` VARCHAR(500) NULL,
  `html` MEDIUMTEXT NULL,
  `heading` VARCHAR(190) NULL,
  `body` TEXT NULL,
  `button_text` VARCHAR(80) NULL,
  `emoji` VARCHAR(16) NULL,
  `badge` VARCHAR(40) NULL,
  `device` VARCHAR(10) NOT NULL DEFAULT 'all',
  `countdown` TINYINT(1) NOT NULL DEFAULT 0,
  `new_tab` TINYINT(1) NOT NULL DEFAULT 1,
  `popup_delay` INT NOT NULL DEFAULT 4,
  `popup_freq` INT NOT NULL DEFAULT 12,
  `priority` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `start_at` DATETIME NULL,
  `end_at` DATETIME NULL,
  `impressions` INT UNSIGNED NOT NULL DEFAULT 0,
  `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `k_pos` (`position`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `pages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(190) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `content` MEDIUMTEXT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'published',
  `show_in_menu` TINYINT(1) NOT NULL DEFAULT 0,
  `show_in_footer` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_page_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NULL,
  `subject` VARCHAR(190) NULL,
  `body` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `ip` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `media` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `path` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NULL,
  `mime` VARCHAR(60) NULL,
  `size` INT UNSIGNED NOT NULL DEFAULT 0,
  `width` INT NULL,
  `height` INT NULL,
  `uploaded_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `settings` (
  `skey` VARCHAR(100) NOT NULL,
  `svalue` MEDIUMTEXT NULL,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `stats_daily` (
  `day` DATE NOT NULL,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `visitors` INT UNSIGNED NOT NULL DEFAULT 0,
  `post_views` INT UNSIGNED NOT NULL DEFAULT 0,
  `ad_clicks` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_code` VARCHAR(20) NOT NULL,
  `first_name` VARCHAR(60) NOT NULL,
  `last_name` VARCHAR(60) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `username` VARCHAR(40) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `note` TEXT NULL,
  `login_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_login` DATETIME NULL,
  `last_ip` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_m_code` (`member_code`),
  UNIQUE KEY `u_m_phone` (`phone`),
  UNIQUE KEY `u_m_email` (`email`),
  UNIQUE KEY `u_m_user` (`username`),
  KEY `k_m_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS `login_attempts` (
  `ip` VARCHAR(45) NOT NULL,
  `attempts` INT NOT NULL DEFAULT 0,
  `last_attempt` DATETIME NOT NULL,
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
