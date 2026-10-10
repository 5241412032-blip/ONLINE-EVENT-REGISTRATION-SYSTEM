-- ============================================================
-- Online Event Management System - Database Schema
-- MySQL 8.0+
-- ============================================================

CREATE DATABASE IF NOT EXISTS event_management_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE event_management_db;

-- ============================================================
-- Table: users
-- Stores both admin and student accounts
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100)                    NOT NULL,
  email       VARCHAR(100)                    NOT NULL UNIQUE,
  password    VARCHAR(255)                    NOT NULL,
  role        ENUM('admin','student')         NOT NULL DEFAULT 'student',
  department  VARCHAR(100)                    DEFAULT NULL,
  year        TINYINT UNSIGNED                DEFAULT NULL,  -- Academic year 1-4
  phone       VARCHAR(20)                     DEFAULT NULL,
  avatar      VARCHAR(255)                    DEFAULT NULL,
  created_at  TIMESTAMP                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP                       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_role  (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: events
-- ============================================================
CREATE TABLE IF NOT EXISTS events (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(200)                                               NOT NULL,
  description  TEXT                                                       DEFAULT NULL,
  event_date   DATE                                                       NOT NULL,
  event_time   TIME                                                       DEFAULT NULL,
  venue        VARCHAR(200)                                               DEFAULT NULL,
  capacity     INT UNSIGNED                                               NOT NULL DEFAULT 100,
  category     VARCHAR(80)                                                DEFAULT 'General',
  status       ENUM('upcoming','ongoing','completed','cancelled')         NOT NULL DEFAULT 'upcoming',
  banner_color VARCHAR(20)                                               DEFAULT '#6c63ff',
  created_by   INT                                                        NOT NULL,
  created_at   TIMESTAMP                                                  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP                                                  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_event_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT,
  INDEX idx_event_date   (event_date),
  INDEX idx_event_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: registrations
-- Tracks student event registrations
-- ============================================================
CREATE TABLE IF NOT EXISTS registrations (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT                              NOT NULL,
  event_id         INT                              NOT NULL,
  status           ENUM('registered','cancelled')   NOT NULL DEFAULT 'registered',
  registered_at    TIMESTAMP                        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelled_at     TIMESTAMP                        NULL     DEFAULT NULL,
  UNIQUE KEY uq_user_event (user_id, event_id),
  CONSTRAINT fk_reg_user  FOREIGN KEY (user_id)  REFERENCES users  (id) ON DELETE CASCADE,
  CONSTRAINT fk_reg_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
  INDEX idx_reg_user  (user_id),
  INDEX idx_reg_event (event_id),
  INDEX idx_reg_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: reports
-- Logs generated reports (metadata only)
-- ============================================================
CREATE TABLE IF NOT EXISTS reports (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  event_id       INT          DEFAULT NULL,  -- NULL = all-events report
  generated_by   INT          NOT NULL,
  report_type    VARCHAR(80)  NOT NULL DEFAULT 'event_summary',
  generated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_report_event FOREIGN KEY (event_id)     REFERENCES events (id) ON DELETE SET NULL,
  CONSTRAINT fk_report_admin FOREIGN KEY (generated_by) REFERENCES users  (id) ON DELETE RESTRICT,
  INDEX idx_report_event (event_id),
  INDEX idx_report_admin (generated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default Admin account
-- Password: Admin@123  (bcrypt hash)
INSERT IGNORE INTO users (name, email, password, role, department) VALUES
(
  'System Administrator',
  'admin@eventms.com',
  '$2y$10$XRtewDGiU8jOB.lOO.ZyweSk7QbhYdQJnbmBUue7i2XgtGsxu51V6',  -- password: Admin@123
  'admin',
  'IT Department'
);

-- Sample Student accounts
-- Password for all: Admin@123
INSERT IGNORE INTO users (name, email, password, role, department, year, phone) VALUES
('Aarav Sharma',   'aarav@student.com',   '$2y$10$XRtewDGiU8jOB.lOO.ZyweSk7QbhYdQJnbmBUue7i2XgtGsxu51V6', 'student', 'Computer Science', 2, '9876543210'),
('Priya Mehta',    'priya@student.com',   '$2y$10$XRtewDGiU8jOB.lOO.ZyweSk7QbhYdQJnbmBUue7i2XgtGsxu51V6', 'student', 'Electronics',      3, '9876543211'),
('Rohit Verma',    'rohit@student.com',   '$2y$10$XRtewDGiU8jOB.lOO.ZyweSk7QbhYdQJnbmBUue7i2XgtGsxu51V6', 'student', 'Mechanical',       1, '9876543212');

-- Sample Events (created_by = admin id = 1)
INSERT IGNORE INTO events (title, description, event_date, event_time, venue, capacity, category, status, banner_color, created_by) VALUES
(
  'Tech Symposium 2026',
  'Annual technology symposium featuring talks by industry experts on AI, Machine Learning, and Cloud Computing. Networking sessions included.',
  '2026-08-15', '09:00:00', 'Main Auditorium, Block A', 200, 'Technology', 'upcoming', '#6c63ff', 1
),
(
  'Cultural Fest – Rangmanch',
  'A vibrant celebration of arts, music, dance, and drama. Open to all students. Prizes worth ₹50,000.',
  '2026-08-22', '10:00:00', 'Open Air Theatre', 500, 'Cultural', 'upcoming', '#ff6b9d', 1
),
(
  'Entrepreneurship Workshop',
  'Two-day intensive workshop on startup ideation, funding, and pitching. Mentored by successful alumni entrepreneurs.',
  '2026-09-05', '09:30:00', 'Seminar Hall – B2', 80,  'Workshop',  'upcoming', '#00d4ff', 1
),
(
  'Sports Meet 2026',
  'Inter-department sports competition. Events include cricket, football, badminton, chess, and athletics.',
  '2026-07-28', '08:00:00', 'University Sports Ground', 300, 'Sports', 'upcoming', '#00e676', 1
),
(
  'Hackathon 36hrs',
  'Non-stop 36-hour coding marathon. Build solutions for real-world problems. Top 3 teams win cash prizes and internship opportunities.',
  '2026-09-12', '18:00:00', 'Innovation Lab, Tech Block', 120, 'Technology', 'upcoming', '#ffab40', 1
);

-- Sample Registrations
INSERT IGNORE INTO registrations (user_id, event_id, status) VALUES
(2, 1, 'registered'),
(2, 3, 'registered'),
(3, 1, 'registered'),
(3, 2, 'registered'),
(4, 4, 'registered'),
(4, 5, 'registered');

-- Sample Report log
INSERT IGNORE INTO reports (event_id, generated_by, report_type) VALUES
(1, 1, 'event_summary'),
(NULL, 1, 'all_events');
