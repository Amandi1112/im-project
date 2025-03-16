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

-- Create personal_details table
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
    spouse_name VARCHAR(100),
);

CREATE TABLE education_details (
    email VARCHAR(100) PRIMARY KEY,
    qualification VARCHAR(255) NOT NULL,
    institute VARCHAR(255) NOT NULL,
    study_duration VARCHAR(100) NOT NULL,
    FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS members (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    membership_number VARCHAR(6) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    membership_age INT(3) NOT NULL,
    nic_number VARCHAR(12) NOT NULL UNIQUE,
    telephone_number VARCHAR(15) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

