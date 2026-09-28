ALTER TABLE courses
    ADD COLUMN course_notes LONGTEXT DEFAULT NULL;

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