USE library_db;

-- Add persistent roles so Teacher/Admin usernames do not need a prefix.
ALTER TABLE users ADD COLUMN role ENUM('Admin','Teacher','Student') NOT NULL DEFAULT 'Student' AFTER email;

-- Preserve the legacy role mapping for existing accounts.
UPDATE users SET role = 'Admin' WHERE username LIKE 'AM.%';
UPDATE users SET role = 'Teacher' WHERE username LIKE 'TC.%';
UPDATE users SET role = 'Student' WHERE username LIKE 'SD.%' OR role IS NULL;

CREATE TABLE IF NOT EXISTS login_confirmations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME NULL,
    INDEX idx_login_confirmations_username (username),
    INDEX idx_login_confirmations_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
