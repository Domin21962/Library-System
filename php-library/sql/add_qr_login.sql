-- QR login tokens for Doms Library
USE library_db;

CREATE TABLE IF NOT EXISTS qr_login_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    token VARCHAR(128) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_qr_username (username),
    INDEX idx_qr_expiry (expires_at)
);
