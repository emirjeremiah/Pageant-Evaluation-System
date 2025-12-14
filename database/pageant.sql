-- pageant.sql (place inside /database)

CREATE DATABASE IF NOT EXISTS pageant_db;
USE pageant_db;

-- Drop tables in reverse order of creation to avoid foreign key issues
DROP TABLE IF EXISTS competition_judges;
DROP TABLE IF EXISTS scores;
DROP TABLE IF EXISTS criteria;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS contestants;
DROP TABLE IF EXISTS judges;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS competitions;
DROP TABLE IF EXISTS notifications;

-- =========================
-- TABLE: admins
-- =========================
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

INSERT INTO admins (username, password)
VALUES ('admin', 'password');

-- =========================
-- TABLE: judges
-- =========================
CREATE TABLE judges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

INSERT INTO judges (name, username, password) VALUES
('Judge 1', 'judge1', 'password'),
('Judge 2', 'judge2', 'password'),
('Judge 3', 'judge3', 'password');

-- =========================
-- TABLE: contestants
-- =========================
CREATE TABLE `contestants` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    age INT NOT NULL,
    number INT NOT NULL,
    photo VARCHAR(255),
    competition_id INT NOT NULL
);

-- =========================
-- TABLE: competitions
-- =========================
CREATE TABLE `competitions` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO competitions (name) VALUES ('Default Competition');

-- Add foreign key constraints after tables are created
ALTER TABLE `contestants` ADD FOREIGN KEY (`competition_id`) REFERENCES `competitions`(`id`) ON DELETE CASCADE;

INSERT INTO contestants (name, age, number, photo, competition_id) VALUES
('Catriona Gray', 25, 1, 'catriona.jpg', 1),
('Pia Wurtzbach', 27, 2, 'pia.jpg', 1),
('Beatrice Gomez', 24, 3, 'gomez.jpg', 1);

-- =========================
-- TABLE: scores
-- =========================
CREATE TABLE scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL,
    judge_id INT NOT NULL,
    contestant_id INT NOT NULL,
    criteria_id INT NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    FOREIGN KEY (judge_id) REFERENCES judges(id) ON DELETE CASCADE,
    FOREIGN KEY (contestant_id) REFERENCES contestants(id) ON DELETE CASCADE
    -- The FOREIGN KEY for criteria_id will be added after the final criteria table is created.
);

-- =========================
-- TABLE: categories
-- =========================
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

INSERT INTO categories (name) VALUES
('Preliminary Interview'),
('Evening Gown'),
('Swimwear');

-- =========================
-- TABLE: criteria
-- =========================
CREATE TABLE criteria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    competition_id INT NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE
);

-- Now, add the foreign key constraint to the scores table
ALTER TABLE scores
ADD CONSTRAINT fk_criteria
FOREIGN KEY (criteria_id) REFERENCES criteria(id) ON DELETE CASCADE;
ALTER TABLE scores
ADD CONSTRAINT fk_competition
FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE;

INSERT INTO `criteria` (`category_id`, `name`, `competition_id`, `percentage`) VALUES
(1, 'Communication Skills', 1, 40.00),
(1, 'Confidence', 1, 30.00),
(1, 'Personality', 1, 30.00),
(2, 'Elegance', 1, 50.00),
(2, 'Stage Presence', 1, 50.00),
(3, 'Physique', 1, 60.00),
(3, 'Poise', 1, 40.00);

-- =========================
-- TABLE: notifications
-- =========================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- TABLE: competition_judges (Pivot Table)
-- =========================
CREATE TABLE `competition_judges` (
    `competition_id` INT NOT NULL,
    `judge_id` INT NOT NULL,
    PRIMARY KEY (`competition_id`, `judge_id`),
    FOREIGN KEY (`competition_id`) REFERENCES `competitions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`judge_id`) REFERENCES `judges`(`id`) ON DELETE CASCADE
);

INSERT INTO competition_judges (competition_id, judge_id) VALUES (1, 1), (1, 2), (1, 3);