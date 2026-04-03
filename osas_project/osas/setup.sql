-- ============================================
--  ONLINE STUDENT ADMISSION SYSTEM (OSAS)
--  Database Setup Script
--  Kabarak University | DIT Project 2026
-- ============================================

CREATE DATABASE IF NOT EXISTS osas_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE osas_db;

-- ── USERS TABLE ──────────────────────────────────────────────
DROP TABLE IF EXISTS documents;
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(150) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    phone       VARCHAR(20)  NOT NULL,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('student','admin') DEFAULT 'student',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── APPLICATIONS TABLE ────────────────────────────────────────
CREATE TABLE applications (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    -- Personal Info
    first_name      VARCHAR(80) NOT NULL,
    last_name       VARCHAR(80) NOT NULL,
    dob             DATE NOT NULL,
    gender          ENUM('Male','Female','Other') NOT NULL,
    nationality     VARCHAR(80) DEFAULT 'Kenyan',
    id_number       VARCHAR(20),
    address         TEXT,
    -- Academic Info
    school_name     VARCHAR(200) NOT NULL,
    kcse_year       YEAR NOT NULL,
    kcse_grade      VARCHAR(5) NOT NULL,
    mean_grade_pts  TINYINT,
    -- Program
    program         VARCHAR(200) NOT NULL,
    intake          VARCHAR(50) DEFAULT 'September 2026',
    -- Parent/Guardian
    guardian_name   VARCHAR(150),
    guardian_phone  VARCHAR(20),
    guardian_rel    VARCHAR(80),
    -- Status
    status          ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    admin_comment   TEXT,
    submitted_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── DOCUMENTS TABLE ───────────────────────────────────────────
CREATE TABLE documents (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    application_id  INT NOT NULL,
    doc_type        VARCHAR(100) NOT NULL,
    file_name       VARCHAR(255) NOT NULL,
    file_path       VARCHAR(500) NOT NULL,
    uploaded_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

-- ── SEED: DEFAULT ADMIN ACCOUNT ──────────────────────────────
-- Email: admin@osas.ac.ke | Password: Admin@123
INSERT INTO users (full_name, email, phone, password, role) VALUES
('System Administrator', 'admin@osas.ac.ke', '0700000000',
 '$2y$12$YkUjvAnpKxvKzJjJXYkJAOEYf0z7TK.FNKyGCvC44TikVdP0JWvMq', 'admin');

-- ── SEED: SAMPLE STUDENT ──────────────────────────────────────
-- Email: student@test.com | Password: Test@123
INSERT INTO users (full_name, email, phone, password, role) VALUES
('James Mwangi', 'student@test.com', '0712345678',
 '$2y$12$6gN.Y9r8X2IkRaJxW6QYvO4oRu6.gDpjZLpUJPR1JkPfFn9xKG5Me', 'student');

-- ── SEED: SAMPLE APPLICATION ─────────────────────────────────
INSERT INTO applications 
    (user_id, first_name, last_name, dob, gender, nationality, id_number, address,
     school_name, kcse_year, kcse_grade, mean_grade_pts, program, intake,
     guardian_name, guardian_phone, guardian_rel, status)
VALUES
    (2, 'James', 'Mwangi', '2004-06-15', 'Male', 'Kenyan', '12345678',
     'P.O. Box 100, Nakuru',
     'Nakuru High School', 2023, 'B+', 62,
     'Diploma in Information Technology', 'September 2026',
     'Mary Mwangi', '0722000111', 'Mother', 'Pending');

SELECT 'Database setup complete! Admin: admin@osas.ac.ke / Admin@123' AS message;
