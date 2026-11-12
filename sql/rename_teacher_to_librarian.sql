-- Doms Library: rename the Teacher role to Librarian. Run once on library_db, BEFORE (or together with) deploying the new PHP files.
-- Existing Teacher accounts keep their access: they become Librarian.

USE library_db;

-- 1) Allow both names while rows are converted
ALTER TABLE users MODIFY role ENUM('Admin','Teacher','Librarian','User') NOT NULL DEFAULT 'User';

-- 2) Convert the rows
UPDATE users SET role = 'Librarian' WHERE role = 'Teacher';

-- 3) Remove the old name
ALTER TABLE users MODIFY role ENUM('Admin','Librarian','User') NOT NULL DEFAULT 'User';
