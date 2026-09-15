-- Adds full-text support to the catalog.
--
-- Run this AFTER library_db.sql, then run:  php tools/import_texts.php
--
-- Every book here is public domain (first published before ~1930), so the complete
-- texts are free to download and store. The IDs below are Project Gutenberg ebook IDs.

USE library_db;

ALTER TABLE book ADD COLUMN IF NOT EXISTS gutenberg_id INT NULL;
ALTER TABLE book ADD COLUMN IF NOT EXISTS full_text LONGTEXT NULL;

UPDATE book SET gutenberg_id = 1342  WHERE title = 'Pride and Prejudice';
UPDATE book SET gutenberg_id = 161   WHERE title = 'Sense and Sensibility';
UPDATE book SET gutenberg_id = 158   WHERE title = 'Emma';
UPDATE book SET gutenberg_id = 84    WHERE title = 'Frankenstein';
UPDATE book SET gutenberg_id = 345   WHERE title = 'Dracula';
UPDATE book SET gutenberg_id = 2701  WHERE title = 'Moby-Dick';
UPDATE book SET gutenberg_id = 2600  WHERE title = 'War and Peace';
UPDATE book SET gutenberg_id = 1399  WHERE title = 'Anna Karenina';
UPDATE book SET gutenberg_id = 2554  WHERE title = 'Crime and Punishment';
UPDATE book SET gutenberg_id = 28054 WHERE title = 'The Brothers Karamazov';
UPDATE book SET gutenberg_id = 64317 WHERE title = 'The Great Gatsby';
UPDATE book SET gutenberg_id = 76    WHERE title = 'The Adventures of Huckleberry Finn';
UPDATE book SET gutenberg_id = 74    WHERE title = 'The Adventures of Tom Sawyer';
UPDATE book SET gutenberg_id = 11    WHERE title = 'Alice''s Adventures in Wonderland';
UPDATE book SET gutenberg_id = 12    WHERE title = 'Through the Looking-Glass';
UPDATE book SET gutenberg_id = 174   WHERE title = 'The Picture of Dorian Gray';
UPDATE book SET gutenberg_id = 135   WHERE title = 'Les Miserables';
UPDATE book SET gutenberg_id = 1184  WHERE title = 'The Count of Monte Cristo';
UPDATE book SET gutenberg_id = 1257  WHERE title = 'The Three Musketeers';
UPDATE book SET gutenberg_id = 120   WHERE title = 'Treasure Island';
UPDATE book SET gutenberg_id = 43    WHERE title = 'Strange Case of Dr Jekyll and Mr Hyde';
UPDATE book SET gutenberg_id = 521   WHERE title = 'Robinson Crusoe';
UPDATE book SET gutenberg_id = 103   WHERE title = 'Around the World in Eighty Days';
UPDATE book SET gutenberg_id = 164   WHERE title = 'Twenty Thousand Leagues Under the Sea';
UPDATE book SET gutenberg_id = 18857 WHERE title = 'Journey to the Center of the Earth';
UPDATE book SET gutenberg_id = 36    WHERE title = 'The War of the Worlds';
UPDATE book SET gutenberg_id = 35    WHERE title = 'The Time Machine';
UPDATE book SET gutenberg_id = 5230  WHERE title = 'The Invisible Man';
UPDATE book SET gutenberg_id = 996   WHERE title = 'Don Quixote';
UPDATE book SET gutenberg_id = 514   WHERE title = 'Little Women';
UPDATE book SET gutenberg_id = 45    WHERE title = 'Anne of Green Gables';
UPDATE book SET gutenberg_id = 113   WHERE title = 'The Secret Garden';
UPDATE book SET gutenberg_id = 244   WHERE title = 'A Study in Scarlet';
UPDATE book SET gutenberg_id = 1661  WHERE title = 'The Adventures of Sherlock Holmes';
UPDATE book SET gutenberg_id = 2852  WHERE title = 'The Hound of the Baskervilles';
UPDATE book SET gutenberg_id = 98    WHERE title = 'A Tale of Two Cities';
UPDATE book SET gutenberg_id = 1400  WHERE title = 'Great Expectations';
UPDATE book SET gutenberg_id = 730   WHERE title = 'Oliver Twist';
UPDATE book SET gutenberg_id = 46    WHERE title = 'A Christmas Carol';
UPDATE book SET gutenberg_id = 55    WHERE title = 'The Wonderful Wizard of Oz';
UPDATE book SET gutenberg_id = 16    WHERE title = 'Peter Pan';
UPDATE book SET gutenberg_id = 215   WHERE title = 'The Call of the Wild';
UPDATE book SET gutenberg_id = 910   WHERE title = 'White Fang';
UPDATE book SET gutenberg_id = 768   WHERE title = 'Wuthering Heights';
UPDATE book SET gutenberg_id = 1260  WHERE title = 'Jane Eyre';
UPDATE book SET gutenberg_id = 25344 WHERE title = 'The Scarlet Letter';
UPDATE book SET gutenberg_id = 5200  WHERE title = 'The Metamorphosis';
UPDATE book SET gutenberg_id = 1727  WHERE title = 'The Odyssey';
UPDATE book SET gutenberg_id = 1513  WHERE title = 'Romeo and Juliet';
UPDATE book SET gutenberg_id = 1524  WHERE title = 'Hamlet';
UPDATE book SET gutenberg_id = 2591  WHERE title = 'Grimms'' Fairy Tales';

SELECT COUNT(*) AS books_ready_to_import FROM book WHERE gutenberg_id IS NOT NULL;
