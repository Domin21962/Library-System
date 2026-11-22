-- Doms Library: replace borrowing with My Shelf + Reading Progress.
-- Run integrity_migration.sql first if you have not already (it still needs borrow_return).
-- Run ONCE on an existing library_db (phpMyAdmin / HeidiSQL), together with the matching PHP files.
-- Books that were currently borrowed are copied to the borrower's shelf first, then borrow_return is dropped.
-- (A fresh install does not need this file: sql/library_db.sql already builds the new tables.)

USE library_db;

CREATE TABLE IF NOT EXISTS shelf (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_shelf_user_book (user_id, book_id),
    CONSTRAINT fk_shelf_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_shelf_book FOREIGN KEY (book_id) REFERENCES book(book_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reading_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_progress_user_book (user_id, book_id),
    CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_progress_book FOREIGN KEY (book_id) REFERENCES book(book_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Keep what users had out: put every book still on loan on its borrower's shelf
INSERT IGNORE INTO shelf (user_id, book_id)
SELECT u.id, br.book_id FROM borrow_return br JOIN users u ON u.username = br.username WHERE br.return_date IS NULL;

DROP TABLE borrow_return;

-- Old borrow/return notifications no longer apply
DELETE FROM admin_notifications WHERE event_type IN ('borrow','return');
