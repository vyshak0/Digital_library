-- Digital Library System: schema + demo data
-- Import in phpMyAdmin (Import tab) or run:  mysql -u root < database/schema.sql
-- WARNING: re-importing drops and recreates the four tables below.

CREATE DATABASE IF NOT EXISTS digital_library CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE digital_library;

DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS borrowings;
DROP TABLE IF EXISTS books;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(80)  NOT NULL,
    email         VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE books (
    book_id          INT AUTO_INCREMENT PRIMARY KEY,
    title            VARCHAR(150) NOT NULL,
    author           VARCHAR(100) NOT NULL,
    category         VARCHAR(60),
    description      TEXT,
    file_path        VARCHAR(255),                 -- file name inside /uploads (PDF or EPUB)
    total_copies     INT NOT NULL DEFAULT 1,       -- licences the library owns
    available_copies INT NOT NULL DEFAULT 1,       -- licences not currently on loan
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_copies CHECK (total_copies >= 0 AND available_copies >= 0 AND available_copies <= total_copies),
    INDEX idx_title (title),
    INDEX idx_author (author),
    INDEX idx_category (category)
) ENGINE=InnoDB;

CREATE TABLE borrowings (
    loan_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    book_id     INT NOT NULL,
    borrow_date DATETIME NOT NULL,
    due_date    DATETIME NOT NULL,
    return_date DATETIME NULL,
    status      ENUM('borrowed', 'returned') NOT NULL DEFAULT 'borrowed',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status),
    INDEX idx_book_status (book_id, status)
) ENGINE=InnoDB;

CREATE TABLE reviews (
    review_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    book_id    INT NOT NULL,
    rating     TINYINT NOT NULL,
    comment    TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5),
    UNIQUE KEY one_review_per_user_book (user_id, book_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Demo data ----------
-- Logins:  admin@library.test / Admin@123   ·   reader@library.test and anu@library.test / Reader@123
INSERT INTO users (name, email, password_hash, role) VALUES
('Library Admin', 'admin@library.test',  '$2y$10$U0IPkGi/UR1yLohbryQmmeyV.AgSSn3A9JTVhd1BL9hdinY4rZdOm',  'admin'),
('Maya',          'reader@library.test', '$2y$10$L1ypQSLyGxwIxMkH.n2ZbO6LdxszHqUXt4PHBvtd7GbRlmF0mw6NG', 'user'),
('Anu',           'anu@library.test',    '$2y$10$L1ypQSLyGxwIxMkH.n2ZbO6LdxszHqUXt4PHBvtd7GbRlmF0mw6NG', 'user');

-- file_path 'sample.pdf' is a placeholder document so the reader can be tried out.
INSERT INTO books (title, author, category, description, file_path, total_copies, available_copies) VALUES
('Clean Code', 'Robert C. Martin', 'Programming', 'A handbook of agile software craftsmanship: naming, functions, comments, formatting and refactoring.', 'sample.pdf', 3, 2),
('Atomic Habits', 'James Clear', 'Self-help', 'A practical framework for building good habits and breaking bad ones through small daily changes.', 'sample.pdf', 2, 1),
('Dune', 'Frank Herbert', 'Science fiction', 'On the desert planet Arrakis, young Paul Atreides is drawn into a struggle over the most valuable resource in the universe.', 'sample.pdf', 2, 2),
('The Pragmatic Programmer', 'Andrew Hunt, David Thomas', 'Programming', 'Tips and habits for becoming a more effective, adaptable programmer.', 'sample.pdf', 2, 2),
('Sapiens', 'Yuval Noah Harari', 'History', 'A sweeping history of humankind, from the Stone Age to the present.', 'sample.pdf', 1, 1),
('The Hobbit', 'J. R. R. Tolkien', 'Fantasy', 'Bilbo Baggins joins a company of dwarves on a journey to reclaim their mountain home from a dragon.', 'sample.pdf', 2, 2),
('Pride and Prejudice', 'Jane Austen', 'Classic', 'Elizabeth Bennet and Mr Darcy navigate manners, money and first impressions in Regency England.', 'sample.pdf', 2, 2),
('Introduction to Algorithms', 'Thomas H. Cormen et al.', 'Computer science', 'A comprehensive textbook covering the design and analysis of algorithms.', 'sample.pdf', 2, 2),
('Thinking, Fast and Slow', 'Daniel Kahneman', 'Psychology', 'How two systems of thought shape our judgements, and where intuition goes wrong.', 'sample.pdf', 2, 2),
('A Brief History of Time', 'Stephen Hawking', 'Science', 'An accessible tour of the big bang, black holes and the nature of time.', 'sample.pdf', 2, 2);

INSERT INTO borrowings (user_id, book_id, borrow_date, due_date, return_date, status) VALUES
(2, 3, DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 16 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY), 'returned'),
(2, 2, DATE_SUB(NOW(), INTERVAL 3 DAY),  DATE_ADD(NOW(), INTERVAL 11 DAY), NULL, 'borrowed'),
(3, 3, DATE_SUB(NOW(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY), 'returned'),
(3, 2, DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 26 DAY), DATE_SUB(NOW(), INTERVAL 30 DAY), 'returned'),
(3, 1, DATE_SUB(NOW(), INTERVAL 5 DAY),  DATE_ADD(NOW(), INTERVAL 9 DAY),  NULL, 'borrowed');

INSERT INTO reviews (user_id, book_id, rating, comment) VALUES
(2, 3, 5, 'Rich world-building and a story that keeps building. Worth the length.'),
(3, 3, 4, 'Slow start, but the second half is gripping.'),
(3, 2, 5, 'Short chapters and ideas I started using the same week.');
