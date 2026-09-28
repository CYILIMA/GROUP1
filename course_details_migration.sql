ALTER TABLE courses
    ADD COLUMN fee DECIMAL(12,2) DEFAULT NULL,
    ADD COLUMN fee_notes TEXT DEFAULT NULL,
    ADD COLUMN duration VARCHAR(120) NOT NULL DEFAULT '',
    ADD COLUMN level VARCHAR(60) NOT NULL DEFAULT 'All levels',
    ADD COLUMN certificate_details TEXT DEFAULT NULL,
    ADD COLUMN requirements TEXT DEFAULT NULL,
    ADD COLUMN course_outline TEXT DEFAULT NULL;

ALTER TABLE courses
    MODIFY COLUMN fee DECIMAL(12,2) DEFAULT NULL;

UPDATE courses
SET fee = NULL
WHERE fee = 0
    AND duration = ''
    AND certificate_details IS NULL
    AND requirements IS NULL
    AND course_outline IS NULL;