-- Create database
CREATE DATABASE IF NOT EXISTS mywebsite;
USE mywebsite;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    position ENUM('admin', 'client', 'accountant') NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE personal_details (
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL PRIMARY KEY,
    gender VARCHAR(10) NOT NULL,
    age INT NOT NULL,
    marital_status VARCHAR(20) NOT NULL,
    date_of_birth DATE NOT NULL,
    address VARCHAR(255),
    religion VARCHAR(50) NOT NULL,
    nic VARCHAR(50) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    spouse_name VARCHAR(100)
);