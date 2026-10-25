-- ============================================================
--  TaskFlow — Full-Stack Task Manager
--  Database Schema & Seed Data
--  Engine: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

-- Create the database
CREATE DATABASE IF NOT EXISTS taskflow
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE taskflow;

-- ============================================================
--  USERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)     NOT NULL UNIQUE,
    email       VARCHAR(100)    NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL,           -- bcrypt hash
    avatar_color VARCHAR(7)    DEFAULT '#6366f1',   -- hex color for UI avatar
    created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_email (email)
) ENGINE=InnoDB;

-- ============================================================
--  CATEGORIES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             NOT NULL,
    name        VARCHAR(60)     NOT NULL,
    color       VARCHAR(7)      DEFAULT '#6366f1',
    created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_category (user_id, name)
) ENGINE=InnoDB;

-- ============================================================
--  TASKS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS tasks (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             NOT NULL,
    category_id INT             DEFAULT NULL,
    title       VARCHAR(200)    NOT NULL,
    description TEXT,
    priority    ENUM('low','medium','high') DEFAULT 'medium',
    status      ENUM('todo','in_progress','done') DEFAULT 'todo',
    due_date    DATE            DEFAULT NULL,
    created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,

    INDEX idx_user_status (user_id, status),
    INDEX idx_due_date    (due_date)
) ENGINE=InnoDB;

-- ============================================================
--  SEED DATA  (password for both users: "password123")
-- ============================================================

-- Hashed with password_hash('password123', PASSWORD_BCRYPT)
INSERT INTO users (username, email, password, avatar_color) VALUES
('alice',  'alice@example.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '#6366f1'),
('bob',    'bob@example.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '#f59e0b');

-- Categories for Alice
INSERT INTO categories (user_id, name, color) VALUES
(1, 'Work',     '#6366f1'),
(1, 'Personal', '#10b981'),
(1, 'Learning', '#f59e0b');

-- Categories for Bob
INSERT INTO categories (user_id, name, color) VALUES
(2, 'Projects', '#ef4444'),
(2, 'Errands',  '#3b82f6');

-- Sample tasks for Alice
INSERT INTO tasks (user_id, category_id, title, description, priority, status, due_date) VALUES
(1, 1, 'Finish quarterly report',   'Complete the Q3 revenue analysis and submit to finance.',      'high',   'in_progress', '2026-11-01'),
(1, 1, 'Team standup prep',         'Prepare talking points for Monday standup.',                    'medium', 'todo',        '2026-10-28'),
(1, 2, 'Grocery shopping',          'Milk, eggs, bread, avocados, coffee beans.',                    'low',    'todo',         NULL),
(1, 3, 'Learn Docker basics',       'Go through the Docker Getting Started tutorial.',               'medium', 'in_progress', '2026-11-15'),
(1, 1, 'Review pull requests',      'Review 3 pending PRs on the main repo before EOD.',            'high',   'done',        '2026-10-24'),
(1, 3, 'Read "Designing Data Apps"','Chapters 5-8 on partitioning and replication.',                 'low',    'todo',        '2026-12-01');

-- Sample tasks for Bob
INSERT INTO tasks (user_id, category_id, title, description, priority, status, due_date) VALUES
(2, 4, 'Build portfolio site',      'Design and develop a personal portfolio with project showcase.','high',   'in_progress', '2026-11-10'),
(2, 5, 'Renew car registration',    'Due before end of month — bring insurance docs.',              'high',   'todo',        '2026-10-31'),
(2, 4, 'API integration tests',     'Write integration tests for the payment gateway module.',      'medium', 'todo',        '2026-11-05');
