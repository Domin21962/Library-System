USE library_db;

-- Holds a pending password change until the user confirms it with the code
-- emailed to them. The new password sits here (unused, in the same plain-text
-- form as the rest of this app) until the code is confirmed, at which point
-- it's copied into users.password and this row is marked used.
CREATE TABLE password_reset_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    code VARCHAR(6) NOT NULL,
    new_password VARCHAR(100) NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    used TINYINT(1) NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
