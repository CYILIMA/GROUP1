CREATE DATABASE IF NOT EXISTS utab_online;

USE utab_online;


CREATE TABLE IF NOT EXISTS users (

    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    email VARCHAR(150) NOT NULL UNIQUE,

    password VARCHAR(255) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);


CREATE TABLE IF NOT EXISTS messages (

    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    email VARCHAR(150) NOT NULL,

    subject VARCHAR(200) NOT NULL,

    message TEXT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admins (

    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    email VARCHAR(150) NOT NULL UNIQUE,

    password VARCHAR(255) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS courses (

    id INT AUTO_INCREMENT PRIMARY KEY,

    title VARCHAR(180) NOT NULL,

    category VARCHAR(100) NOT NULL,

    description TEXT NOT NULL,

    image_path VARCHAR(255) DEFAULT NULL,

    fee DECIMAL(12,2) DEFAULT NULL,

    fee_notes TEXT DEFAULT NULL,

    duration VARCHAR(120) NOT NULL DEFAULT '',

    level VARCHAR(60) NOT NULL DEFAULT 'All levels',

    certificate_details TEXT DEFAULT NULL,

    requirements TEXT DEFAULT NULL,

    course_outline TEXT DEFAULT NULL,

    course_notes LONGTEXT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS course_resources (

    id INT AUTO_INCREMENT PRIMARY KEY,

    course_id INT NOT NULL,

    title VARCHAR(180) NOT NULL,

    resource_type ENUM('image', 'pdf') NOT NULL,

    file_path VARCHAR(255) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_course_resources_course_id (course_id),

    CONSTRAINT fk_course_resources_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS homepage_feature_images (

    feature_key VARCHAR(40) PRIMARY KEY,

    image_path VARCHAR(255) NOT NULL,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hero_slides (

    id INT AUTO_INCREMENT PRIMARY KEY,

    image_path VARCHAR(255) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
