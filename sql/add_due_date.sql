USE library_db;

ALTER TABLE borrow_return ADD COLUMN due_date DATETIME NULL;
