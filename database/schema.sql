CREATE DATABASE IF NOT EXISTS campustrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campustrack;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'faculty', 'student') NOT NULL,
    department VARCHAR(120) NOT NULL DEFAULT '',
    phone VARCHAR(30) NOT NULL DEFAULT '',
    identifier VARCHAR(40) NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_role_department (role, department)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS courses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(140) NOT NULL,
    credits TINYINT UNSIGNED NOT NULL DEFAULT 3,
    semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
    faculty_id INT UNSIGNED NULL,
    CONSTRAINT fk_courses_faculty FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_courses_faculty (faculty_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS enrollments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_enrollment_student_course (student_id, course_id),
    CONSTRAINT fk_enrollments_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present', 'Absent') NOT NULL,
    UNIQUE KEY uq_attendance_student_course_date (student_id, course_id, attendance_date),
    CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_attendance_course_date (course_id, attendance_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grades (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    internal DECIMAL(5,2) NOT NULL DEFAULT 0,
    midterm DECIMAL(5,2) NOT NULL DEFAULT 0,
    final DECIMAL(5,2) NOT NULL DEFAULT 0,
    total DECIMAL(6,2) NOT NULL DEFAULT 0,
    percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
    grade_letter CHAR(1) NOT NULL DEFAULT 'F',
    UNIQUE KEY uq_grade_student_course (student_id, course_id),
    CONSTRAINT fk_grades_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_grades_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS schedules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    day ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(60) NOT NULL DEFAULT '',
    CONSTRAINT fk_schedules_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_schedules_day_time (day, start_time)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS materials (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_materials_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Demo passwords are bcrypt hashes: admin123, faculty123, and student123.
INSERT IGNORE INTO users (id, name, email, password_hash, role, department, phone, identifier) VALUES
(1, 'Avery Morgan', 'admin@campustrack.com', '$2y$10$tz1NxFs9uGVfWjb0Svcs.uG.9iaqChVjIXsdFsbH1LUU/h1c2cKFS', 'admin', 'Administration', '555-0100', 'ADM-001'),
(2, 'Jordan Lee', 'faculty1@campustrack.com', '$2y$10$wwgkCWWF/L0IwJQRemEFuu60KxTcXvjY4/gqcsuBvpOoPXSadcgL2', 'faculty', 'Computer Science', '555-0101', 'FAC-001'),
(3, 'Riley Patel', 'faculty2@campustrack.com', '$2y$10$wwgkCWWF/L0IwJQRemEFuu60KxTcXvjY4/gqcsuBvpOoPXSadcgL2', 'faculty', 'Mathematics', '555-0102', 'FAC-002'),
(4, 'Casey Chen', 'faculty3@campustrack.com', '$2y$10$wwgkCWWF/L0IwJQRemEFuu60KxTcXvjY4/gqcsuBvpOoPXSadcgL2', 'faculty', 'Science', '555-0103', 'FAC-003'),
(5, 'Sam Rivera', 'student1@campustrack.com', '$2y$10$qMetNATOrA4zUsMeroOXhu6WVi0CWghtn4N/.MTcO5iUhZ7PkrrA6', 'student', 'Computer Science', '555-0201', 'STU-001'),
(6, 'Taylor Brooks', 'student2@campustrack.com', '$2y$10$qMetNATOrA4zUsMeroOXhu6WVi0CWghtn4N/.MTcO5iUhZ7PkrrA6', 'student', 'Computer Science', '555-0202', 'STU-002'),
(7, 'Morgan Ellis', 'student3@campustrack.com', '$2y$10$qMetNATOrA4zUsMeroOXhu6WVi0CWghtn4N/.MTcO5iUhZ7PkrrA6', 'student', 'Mathematics', '555-0203', 'STU-003'),
(8, 'Jamie Kim', 'student4@campustrack.com', '$2y$10$qMetNATOrA4zUsMeroOXhu6WVi0CWghtn4N/.MTcO5iUhZ7PkrrA6', 'student', 'Science', '555-0204', 'STU-004'),
(9, 'Alex Murphy', 'student5@campustrack.com', '$2y$10$qMetNATOrA4zUsMeroOXhu6WVi0CWghtn4N/.MTcO5iUhZ7PkrrA6', 'student', 'Computer Science', '555-0205', 'STU-005'),
(10, 'Drew Bennett', 'student6@campustrack.com', '$2y$10$qMetNATOrA4zUsMeroOXhu6WVi0CWghtn4N/.MTcO5iUhZ7PkrrA6', 'student', 'Mathematics', '555-0206', 'STU-006'),
(11, 'Cameron Diaz', 'student7@campustrack.com', '$2y$10$qMetNATOrA4zUsMeroOXhu6WVi0CWghtn4N/.MTcO5iUhZ7PkrrA6', 'student', 'Science', '555-0207', 'STU-007'),
(12, 'Quinn Foster', 'student8@campustrack.com', '$2y$10$qMetNATOrA4zUsMeroOXhu6WVi0CWghtn4N/.MTcO5iUhZ7PkrrA6', 'student', 'Computer Science', '555-0208', 'STU-008'),
(13, 'Harper Singh', 'student9@campustrack.com', '$2y$10$qMetNATOrA4zUsMeroOXhu6WVi0CWghtn4N/.MTcO5iUhZ7PkrrA6', 'student', 'Mathematics', '555-0209', 'STU-009'),
(14, 'Parker Jones', 'student10@campustrack.com', '$2y$10$qMetNATOrA4zUsMeroOXhu6WVi0CWghtn4N/.MTcO5iUhZ7PkrrA6', 'student', 'Science', '555-0210', 'STU-010');

INSERT IGNORE INTO courses (id, code, name, credits, semester, faculty_id) VALUES
(1, 'CS101', 'Introduction to Programming', 4, 1, 2),
(2, 'CS204', 'Data Structures', 4, 2, 2),
(3, 'MA110', 'Applied Mathematics', 3, 1, 3),
(4, 'SC120', 'Foundations of Science', 3, 1, 4),
(5, 'CS305', 'Database Systems', 4, 3, 3);

INSERT IGNORE INTO enrollments (student_id, course_id)
SELECT u.id, c.id FROM users u CROSS JOIN courses c WHERE u.role = 'student';

INSERT IGNORE INTO schedules (id, course_id, day, start_time, end_time, room) VALUES
(1, 1, 'Monday', '09:00:00', '10:30:00', 'Hall 204'),
(2, 1, 'Wednesday', '09:00:00', '10:30:00', 'Lab 3'),
(3, 2, 'Tuesday', '11:00:00', '12:30:00', 'Lab 3'),
(4, 2, 'Thursday', '11:00:00', '12:30:00', 'Lab 3'),
(5, 3, 'Monday', '13:00:00', '14:30:00', 'Hall 108'),
(6, 3, 'Friday', '13:00:00', '14:30:00', 'Hall 108'),
(7, 4, 'Tuesday', '14:00:00', '15:30:00', 'Science 12'),
(8, 4, 'Thursday', '14:00:00', '15:30:00', 'Science 12'),
(9, 5, 'Wednesday', '11:00:00', '12:30:00', 'Lab 2'),
(10, 5, 'Friday', '09:00:00', '10:30:00', 'Lab 2');

-- Twenty recent class dates are seeded for every enrolled student.
INSERT IGNORE INTO attendance (student_id, course_id, attendance_date, status)
SELECT e.student_id, e.course_id, d.class_date,
       CASE
           WHEN e.student_id = 5 AND MOD(d.day_number, 3) = 0 THEN 'Absent'
           WHEN e.student_id = 6 AND MOD(d.day_number, 5) = 0 THEN 'Absent'
           WHEN MOD(d.day_number + e.student_id + e.course_id, 10) = 0 THEN 'Absent'
           ELSE 'Present'
       END
FROM enrollments e
CROSS JOIN (
    SELECT CURDATE() - INTERVAL 19 DAY AS class_date, 0 AS day_number UNION ALL
    SELECT CURDATE() - INTERVAL 18 DAY, 1 UNION ALL SELECT CURDATE() - INTERVAL 17 DAY, 2 UNION ALL
    SELECT CURDATE() - INTERVAL 16 DAY, 3 UNION ALL SELECT CURDATE() - INTERVAL 15 DAY, 4 UNION ALL
    SELECT CURDATE() - INTERVAL 14 DAY, 5 UNION ALL SELECT CURDATE() - INTERVAL 13 DAY, 6 UNION ALL
    SELECT CURDATE() - INTERVAL 12 DAY, 7 UNION ALL SELECT CURDATE() - INTERVAL 11 DAY, 8 UNION ALL
    SELECT CURDATE() - INTERVAL 10 DAY, 9 UNION ALL SELECT CURDATE() - INTERVAL 9 DAY, 10 UNION ALL
    SELECT CURDATE() - INTERVAL 8 DAY, 11 UNION ALL SELECT CURDATE() - INTERVAL 7 DAY, 12 UNION ALL
    SELECT CURDATE() - INTERVAL 6 DAY, 13 UNION ALL SELECT CURDATE() - INTERVAL 5 DAY, 14 UNION ALL
    SELECT CURDATE() - INTERVAL 4 DAY, 15 UNION ALL SELECT CURDATE() - INTERVAL 3 DAY, 16 UNION ALL
    SELECT CURDATE() - INTERVAL 2 DAY, 17 UNION ALL SELECT CURDATE() - INTERVAL 1 DAY, 18 UNION ALL
    SELECT CURDATE(), 19
) d;

INSERT IGNORE INTO grades (student_id, course_id, internal, midterm, final, total, percentage, grade_letter)
SELECT student_id, course_id, internal_mark, midterm_mark, final_mark, total_mark,
       ROUND(total_mark / 3, 2),
       CASE WHEN total_mark / 3 >= 90 THEN 'A'
            WHEN total_mark / 3 >= 80 THEN 'B'
            WHEN total_mark / 3 >= 70 THEN 'C'
            WHEN total_mark / 3 >= 60 THEN 'D' ELSE 'F' END
FROM (
    SELECT student_id, course_id,
           60 + MOD(student_id + course_id, 41) AS internal_mark,
           55 + MOD(student_id + course_id * 2, 36) AS midterm_mark,
           50 + MOD(student_id * 2 + course_id, 46) AS final_mark,
           (60 + MOD(student_id + course_id, 41)) +
           (55 + MOD(student_id + course_id * 2, 36)) +
           (50 + MOD(student_id * 2 + course_id, 46)) AS total_mark
    FROM enrollments
) marks;