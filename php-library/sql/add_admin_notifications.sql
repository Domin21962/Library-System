USE library_db;

CREATE TABLE IF NOT EXISTS admin_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type ENUM('borrow','return') NOT NULL,
    username VARCHAR(100) NOT NULL,
    book_id INT NOT NULL,
    book_title VARCHAR(255) NOT NULL,
    message VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_notifications_id (id),
    INDEX idx_admin_notifications_created (created_at),
    INDEX idx_admin_notifications_type (event_type)
);
