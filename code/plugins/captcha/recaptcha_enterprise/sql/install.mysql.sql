CREATE TABLE IF NOT EXISTS `#__recaptcha_enterprise_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `log_date` datetime NOT NULL,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `action` varchar(255) NOT NULL DEFAULT '',
  `score` decimal(3,2) DEFAULT NULL,
  `threshold` decimal(3,2) DEFAULT NULL,
  `result` varchar(20) NOT NULL DEFAULT '',
  `invalid_reason` varchar(50) NOT NULL DEFAULT '',
  `error_message` text,
  `page_url` varchar(2048) NOT NULL DEFAULT '',
  `user_id` int unsigned NOT NULL DEFAULT 0,
  `form_name` varchar(400) NOT NULL DEFAULT '',
  `form_email` varchar(320) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_log_date` (`log_date`),
  KEY `idx_result` (`result`),
  KEY `idx_ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
