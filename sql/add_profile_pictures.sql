USE library_db;

ALTER TABLE users
    ADD COLUMN profile_picture VARCHAR(255) NULL AFTER email;
