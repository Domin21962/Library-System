-- Doms Library: data integrity + 3NF migration. Run once in phpMyAdmin / HeidiSQL on library_db,
-- AFTER deploying the matching PHP files (they no longer write borrow_return.email).

USE library_db;

-- 1) 3NF: the email depends on the user, not on the borrowing. It is read from users via JOIN.
ALTER TABLE borrow_return DROP COLUMN email;

-- 2) Remove rows that point at users that no longer exist (they would block the keys below).
DELETE FROM borrow_return WHERE username NOT IN (SELECT username FROM users);
DELETE FROM password_reset_tokens WHERE user_id NOT IN (SELECT id FROM users);

-- 3) Enforce the links in the database. Deleting a user also deletes their history and reset tokens.
ALTER TABLE borrow_return
  ADD CONSTRAINT fk_borrow_user FOREIGN KEY (username) REFERENCES users(username)
  ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE password_reset_tokens
  ADD CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id)
  ON DELETE CASCADE;
