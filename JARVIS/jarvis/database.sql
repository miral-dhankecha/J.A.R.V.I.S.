-- ============================================
-- J.A.R.V.I.S Database Schema
-- Import this in Laragon -> phpMyAdmin
-- ============================================

-- NOTE: name matches config/db.php's $DB_NAME ("jarvis_new"). If your
-- database is already created with a different name, either rename it
-- to jarvis_new or edit config/db.php to match.
CREATE DATABASE IF NOT EXISTS jarvis_new;
USE jarvis_new;

-- Users table (register/login)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Command history table
CREATE TABLE IF NOT EXISTS history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    command_text VARCHAR(500) NOT NULL,
    response_text VARCHAR(500) NOT NULL,
    status ENUM('success','fail') DEFAULT 'success',
    cmd_date DATE NOT NULL,
    cmd_day VARCHAR(20) NOT NULL,
    cmd_time VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Memory table (jarvis remember my name is X / favorite color etc.)
CREATE TABLE IF NOT EXISTS memory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    mem_key VARCHAR(100) NOT NULL,
    mem_value VARCHAR(500) NOT NULL,
    cmd_date DATE NOT NULL,
    cmd_day VARCHAR(20) NOT NULL,
    cmd_time VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
