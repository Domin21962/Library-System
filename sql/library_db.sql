-- Library System database schema
-- Import this in Laragon's HeidiSQL / phpMyAdmin (SQL tab), or run: mysql -u root < library_db.sql

CREATE DATABASE IF NOT EXISTS library_db;
USE library_db;

DROP TABLE IF EXISTS borrow_return;
DROP TABLE IF EXISTS book;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100),
    password VARCHAR(100) NOT NULL
);

CREATE TABLE book (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255),
    year INT,
    genre VARCHAR(100),
    publisher VARCHAR(255),
    book_content TEXT
);

CREATE TABLE borrow_return (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    book_id INT NOT NULL,
    borrow_date DATETIME NOT NULL,
    return_date DATETIME NULL,
    FOREIGN KEY (book_id) REFERENCES book(book_id)
);

-- Sample accounts (username prefix decides role: AM. = Admin, TC. = Teacher, SD. = Student)
INSERT INTO users (username, email, password) VALUES
('AM.Admin', 'admin@library.edu', 'admin123'),
('AM.Cruz', 'cruz.admin@library.edu', 'admin123'),
('TC.Reyes', 'reyes.teacher@library.edu', 'teacher123'),
('TC.Santos', 'santos.teacher@library.edu', 'teacher123'),
('SD.Juan', 'juan.student@library.edu', 'student123'),
('SD.Maria', 'maria.student@library.edu', 'student123'),
('SD.Pedro', 'pedro.student@library.edu', 'student123'),
('SD.Ana', 'ana.student@library.edu', 'student123');

-- Sample book catalog across a range of genres
INSERT INTO book (title, author, year, genre, publisher, book_content) VALUES
('The Hobbit', 'J.R.R. Tolkien', 1937, 'Fantasy', 'George Allen & Unwin',
 'Bilbo Baggins, a hobbit who enjoys a comfortable life, is thrust into an epic quest by the wizard Gandalf and a company of dwarves seeking to reclaim their mountain home from the dragon Smaug.'),

('Dune', 'Frank Herbert', 1965, 'Science Fiction', 'Chilton Books',
 'On the desert planet Arrakis, young Paul Atreides navigates political intrigue, prophecy, and survival as forces conspire to control the galaxys most valuable resource: the spice melange.'),

('Pride and Prejudice', 'Jane Austen', 1813, 'Romance', 'T. Egerton',
 'Elizabeth Bennet must navigate issues of manners, upbringing, morality, and marriage in the landed gentry society of early 19th-century England, all while sparring with the proud Mr. Darcy.'),

('1984', 'George Orwell', 1949, 'Dystopian', 'Secker & Warburg',
 'In a totalitarian superstate under constant surveillance, Winston Smith begins to question the Partys control over truth, history, and even thought itself.'),

('To Kill a Mockingbird', 'Harper Lee', 1960, 'Classic', 'J.B. Lippincott & Co.',
 'Scout Finch recounts her childhood in a small Alabama town, where her father, a principled lawyer, defends a Black man falsely accused of a crime amid deep racial tension.'),

('The Da Vinci Code', 'Dan Brown', 2003, 'Mystery', 'Doubleday',
 'Symbologist Robert Langdon uncovers a trail of clues hidden in the works of Leonardo da Vinci while investigating a murder inside the Louvre Museum in Paris.'),

('Introduction to Algorithms', 'Thomas H. Cormen', 2009, 'Computer Science', 'MIT Press',
 'A comprehensive textbook covering a broad range of algorithms in depth, with rigorous analysis of correctness and running time, widely used in university computer science courses.'),

('A Brief History of Time', 'Stephen Hawking', 1988, 'Science', 'Bantam Books',
 'A landmark work of popular science exploring cosmology, black holes, and the nature of time itself, written to be accessible to readers without a scientific background.'),

('The Diary of a Young Girl', 'Anne Frank', 1947, 'Biography', 'Contact Publishing',
 'The real-life diary of a Jewish teenager hiding from Nazi persecution in Amsterdam, offering a deeply personal account of hope and fear during World War II.'),

('Sapiens: A Brief History of Humankind', 'Yuval Noah Harari', 2011, 'History', 'Harvill Secker',
 'An exploration of how Homo sapiens came to dominate the planet, tracing the cognitive, agricultural, and scientific revolutions that shaped human society.'),

('Harry Potter and the Sorcerers Stone', 'J.K. Rowling', 1997, 'Fantasy', 'Bloomsbury',
 'An orphaned boy discovers on his eleventh birthday that he is a wizard, and is whisked away to Hogwarts School of Witchcraft and Wizardry to begin his magical education.'),

('The Alchemist', 'Paulo Coelho', 1988, 'Adventure', 'HarperTorch',
 'A young Andalusian shepherd travels to Egypt after a recurring dream, discovering along the way that the treasure he seeks was within him all along.');
