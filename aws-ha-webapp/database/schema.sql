-- Database Schema for Highly Available Web Application
-- MySQL 8.0+

-- Create database
CREATE DATABASE IF NOT EXISTS webapp_db
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE webapp_db;

-- Drop table if exists (for clean reinstall)
DROP TABLE IF EXISTS submissions;

-- Create submissions table
CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'User full name',
    email VARCHAR(100) NOT NULL COMMENT 'User email address',
    message TEXT COMMENT 'Optional message from user',
    server_hostname VARCHAR(255) NOT NULL COMMENT 'EC2 instance that processed this request',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
    
    -- Indexes for performance
    INDEX idx_created_at (created_at DESC) COMMENT 'Index for sorting by date',
    INDEX idx_email (email) COMMENT 'Index for email lookups',
    INDEX idx_server_hostname (server_hostname) COMMENT 'Index for analyzing load distribution'
    
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Stores user submissions to demonstrate database connectivity and load balancing';

-- Insert seed data for testing
INSERT INTO submissions (name, email, message, server_hostname) VALUES
('Test User', 'test@example.com', 'Database connection successful!', 'initial-setup'),
('Admin', 'admin@webapp.com', 'RDS Multi-AZ is now active', 'initial-setup'),
('John Doe', 'john@example.com', 'Testing Auto Scaling Group', 'initial-setup');

-- Verify data
SELECT * FROM submissions ORDER BY created_at DESC;

-- Display table structure
DESCRIBE submissions;

-- Show table statistics
SHOW TABLE STATUS LIKE 'submissions';
