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

CREATE TABLE IF NOT EXISTS membership_numbers (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    membership_number VARCHAR(6) NOT NULL UNIQUE,
    nic_number VARCHAR(12) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS packaged_foods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    total_quantity INT NOT NULL,
    selling_quantity INT NOT NULL,
    remaining_quantity INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Suppliers Table (if not already exists)
CREATE TABLE suppliers (
    supplier_id VARCHAR(10) PRIMARY KEY,
    supplier_name VARCHAR(100) NOT NULL,
    nic VARCHAR(20) NOT NULL UNIQUE,
    address TEXT NOT NULL,
    registration_date DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



CREATE TABLE IF NOT EXISTS categories ( category_id INT AUTO_INCREMENT PRIMARY KEY, -- INT category_name VARCHAR(100) NOT NULL UNIQUE, created_at DATETIME DEFAULT CURRENT_TIMESTAMP ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE items (
    item_id CHAR(6) PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    category_id CHAR(6) NOT NULL,
    supplier_id VARCHAR(10) NOT NULL,
    quantity INT NOT NULL,
    price_per_unit DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) 
        REFERENCES categories(category_id)
        ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) 
        REFERENCES supplier(supplier_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity VARCHAR(255) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);



