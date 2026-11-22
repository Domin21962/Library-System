USE library_db;

CREATE TABLE IF NOT EXISTS admin_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(20) NOT NULL DEFAULT 'borrow',
    username VARCHAR(100) NOT NULL,
    book_id INT NOT NULL DEFAULT 0,
    book_title VARCHAR(255) NOT NULL DEFAULT '',
    message VARCHAR(500) NOT NULL DEFAULT '',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_notifications_id (id),
    INDEX idx_admin_notifications_created (created_at),
    INDEX idx_admin_notifications_type (event_type)
);
