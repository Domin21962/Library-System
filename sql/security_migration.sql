-- Doms Library security migration (run in phpMyAdmin against library_db)
-- 1) Create a least-privilege application user (change the password first).
CREATE USER IF NOT EXISTS 'doms_library'@'localhost' IDENTIFIED BY 'CHANGE_THIS_STRONG_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX ON library_db.* TO 'doms_library'@'localhost';
FLUSH PRIVILEGES;

-- 2) Security tables. The application also creates these defensively.
CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_login_attempt (username, ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS security_audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NULL,
  action VARCHAR(100) NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_security_audit_created (created_at),
  INDEX idx_security_audit_username (username),
  INDEX idx_security_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Passwords must be migrated by PHP, not with SQL. Run tools/migrate_passwords.php
-- from the Laragon terminal after configuring config/local.php.
