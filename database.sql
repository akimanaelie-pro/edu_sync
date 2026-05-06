-- EduSync Nexus - Database Schema
-- School Management System with Offline-Online Sync Support

-- Drop tables if exist
DROP TABLE IF EXISTS sync_logs;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS fee_payments;
DROP TABLE IF EXISTS fee_structure;
DROP TABLE IF EXISTS assignments;
DROP TABLE IF EXISTS exam_results;
DROP TABLE IF EXISTS grades;
DROP TABLE IF EXISTS timetable;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS guardians;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS sync_queue;
DROP TABLE IF EXISTS audit_logs;

-- Audit Logs table
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Settings table
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Users table (Base authentication)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student', 'parent', 'accountant') NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    profile_image VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    INDEX idx_uuid (uuid),
    INDEX idx_email (email),
    INDEX idx_role (role)
);

-- Teachers table (extends users)
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    employee_id VARCHAR(50) UNIQUE,
    department VARCHAR(100),
    qualification VARCHAR(255),
    hire_date DATE,
    salary DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_uuid (uuid),
    INDEX idx_user_id (user_id)
);

-- Guardians table
CREATE TABLE guardians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255),
    occupation VARCHAR(100),
    relationship ENUM('father', 'mother', 'guardian', 'other') NOT NULL,
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_uuid (uuid),
    INDEX idx_user_id (user_id)
);

-- Students table (extends users, core SIS)
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    student_id VARCHAR(50) UNIQUE,
    admission_date DATE NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    blood_group VARCHAR(5),
    nationality VARCHAR(100),
    address TEXT,
    tags JSON,
    risk_level ENUM('low', 'medium', 'high') DEFAULT 'low',
    xp_points INT DEFAULT 0,
    badges JSON,
    guardian_id INT,
    class_id INT,
    section_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (guardian_id) REFERENCES guardians(id) ON DELETE SET NULL,
    INDEX idx_uuid (uuid),
    INDEX idx_student_id (student_id),
    INDEX idx_class_id (class_id),
    INDEX idx_risk_level (risk_level)
);

-- Classes table
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    grade_level VARCHAR(50),
    capacity INT DEFAULT 40,
    room_number VARCHAR(20),
    academic_year VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    INDEX idx_uuid (uuid),
    INDEX idx_name (name)
);

-- Sections table
CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    class_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    section_id VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    INDEX idx_uuid (uuid),
    INDEX idx_class_id (class_id)
);

-- Attendance table
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    date DATE NOT NULL,
    status VARCHAR(50) NOT NULL,
    mark_by INT NOT NULL,
    mark_method ENUM('manual', 'biometric', 'ai_face') DEFAULT 'manual',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (mark_by) REFERENCES users(id),
    INDEX idx_uuid (uuid),
    INDEX idx_student_date (student_id, date),
    INDEX idx_date (date),
    INDEX idx_status (status),
    UNIQUE KEY unique_attendance (student_id, class_id, date)
);

-- Subjects table
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE,
    teacher_id INT,
    class_id INT,
    credit_hours INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    INDEX idx_uuid (uuid),
    INDEX idx_teacher (teacher_id),
    INDEX idx_class (class_id)
);

-- Grade Scales table
CREATE TABLE grade_scales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_default TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Grade Scale Items table
CREATE TABLE grade_scale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scale_id INT NOT NULL,
    min_percentage DECIMAL(5,2) NOT NULL,
    max_percentage DECIMAL(5,2) NOT NULL,
    grade_letter VARCHAR(10) NOT NULL,
    grade_value DECIMAL(5,2),
    description VARCHAR(100),
    color_code VARCHAR(7) DEFAULT '#000000',
    FOREIGN KEY (scale_id) REFERENCES grade_scales(id) ON DELETE CASCADE,
    INDEX idx_scale (scale_id),
    INDEX idx_range (min_percentage, max_percentage)
);

-- Grades table
CREATE TABLE grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    term VARCHAR(20) NOT NULL,
    assessment_type ENUM('classwork', 'homework', 'quiz', 'midterm', 'final', 'project') NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    max_score DECIMAL(5,2) NOT NULL,
    grade_letter VARCHAR(10),
    grade_scale_id INT,
    comments TEXT,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    FOREIGN KEY (grade_scale_id) REFERENCES grade_scales(id) ON DELETE SET NULL,
    INDEX idx_uuid (uuid),
    INDEX idx_student_subject (student_id, subject_id, academic_year, term),
    INDEX idx_academic (academic_year, term)
);

-- Insert default grade scale (Traditional A-F)
INSERT INTO grade_scales (name, description, is_default, is_active) VALUES 
('Traditional A-F', 'Standard letter grade system with plus/minus', 1, 1);

SET @scale_id = LAST_INSERT_ID();

INSERT INTO grade_scale_items (scale_id, min_percentage, max_percentage, grade_letter, grade_value, description, color_code) VALUES
(@scale_id, 97, 100, 'A+', 4.33, 'Exceptional', '#10b981'),
(@scale_id, 93, 96.99, 'A', 4.0, 'Excellent', '#10b981'),
(@scale_id, 90, 92.99, 'A-', 3.67, 'Excellent', '#10b981'),
(@scale_id, 87, 89.99, 'B+', 3.33, 'Good', '#6366f1'),
(@scale_id, 83, 86.99, 'B', 3.0, 'Good', '#6366f1'),
(@scale_id, 80, 82.99, 'B-', 2.67, 'Good', '#6366f1'),
(@scale_id, 77, 79.99, 'C+', 2.33, 'Satisfactory', '#f59e0b'),
(@scale_id, 73, 76.99, 'C', 2.0, 'Satisfactory', '#f59e0b'),
(@scale_id, 70, 72.99, 'C-', 1.67, 'Satisfactory', '#f59e0b'),
(@scale_id, 67, 69.99, 'D+', 1.33, 'Needs Improvement', '#ef4444'),
(@scale_id, 63, 66.99, 'D', 1.0, 'Needs Improvement', '#ef4444'),
(@scale_id, 60, 62.99, 'D-', 0.67, 'Needs Improvement', '#ef4444'),
(@scale_id, 0, 59.99, 'F', 0.0, 'Failing', '#dc2626');

-- Insert Percentage-based scale
INSERT INTO grade_scales (name, description, is_default, is_active) VALUES 
('Percentage Based', 'Simple percentage scoring', 0, 1);

SET @scale_id = LAST_INSERT_ID();

INSERT INTO grade_scale_items (scale_id, min_percentage, max_percentage, grade_letter, grade_value, description, color_code) VALUES
(@scale_id, 90, 100, '90-100%', 100, 'Distinction', '#10b981'),
(@scale_id, 75, 89.99, '75-89%', 89, 'Merit', '#6366f1'),
(@scale_id, 60, 74.99, '60-74%', 74, 'Pass', '#f59e0b'),
(@scale_id, 0, 59.99, 'Below 60%', 59, 'Fail', '#ef4444');

-- Insert Standards-based scale
INSERT INTO grade_scales (name, description, is_default, is_active) VALUES 
('Standards-Based', 'Mastery levels for standards-based grading', 0, 1);

SET @scale_id = LAST_INSERT_ID();

INSERT INTO grade_scale_items (scale_id, min_percentage, max_percentage, grade_letter, grade_value, description, color_code) VALUES
(@scale_id, 90, 100, 'Exceeds', 4, 'Exceeds Expectations', '#10b981'),
(@scale_id, 75, 89.99, 'Meets', 3, 'Meets Expectations', '#6366f1'),
(@scale_id, 60, 74.99, 'Approaching', 2, 'Approaching Expectations', '#f59e0b'),
(@scale_id, 0, 59.99, 'Below', 1, 'Below Expectations', '#ef4444');

-- Assignments table
CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    subject_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    assignment_type ENUM('homework', 'quiz', 'project', 'essay', 'lab', 'presentation') DEFAULT 'homework',
    due_date DATETIME,
    total_marks DECIMAL(5,2),
    rubric_id INT,
    allow_late_submission TINYINT(1) DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (rubric_id) REFERENCES rubrics(id) ON DELETE SET NULL,
    INDEX idx_uuid (uuid),
    INDEX idx_subject (subject_id),
    INDEX idx_due_date (due_date),
    INDEX idx_type (assignment_type)
);

-- Assignment Classes (for multi-class posting)
CREATE TABLE assignment_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    class_id INT NOT NULL,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment_class (assignment_id, class_id)
);

-- Rubrics table
CREATE TABLE rubrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    total_points DECIMAL(5,2),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_name (name)
);

-- Rubric Criteria table
CREATE TABLE rubric_criteria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rubric_id INT NOT NULL,
    criterion TEXT NOT NULL,
    points DECIMAL(5,2) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (rubric_id) REFERENCES rubrics(id) ON DELETE CASCADE,
    INDEX idx_rubric (rubric_id, sort_order)
);

-- Assignment Submissions table
CREATE TABLE assignment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    submission_text TEXT,
    file_url VARCHAR(500),
    file_type ENUM('pdf', 'doc', 'image', 'video', 'link', 'other') DEFAULT 'other',
    google_drive_link VARCHAR(500),
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('submitted', 'late', 'resubmit') DEFAULT 'submitted',
    marks_obtained DECIMAL(5,2),
    feedback TEXT,
    graded_by INT,
    graded_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id),
    INDEX idx_uuid (uuid),
    INDEX idx_assignment (assignment_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    UNIQUE KEY unique_submission (assignment_id, student_id)
);

-- Exam Results table
CREATE TABLE exam_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    exam_name VARCHAR(100) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    term VARCHAR(20) NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    max_score DECIMAL(5,2) NOT NULL,
    grade_letter VARCHAR(2),
    rank INT,
    remarks TEXT,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_uuid (uuid),
    INDEX idx_student_exam (student_id, exam_name, academic_year, term)
);

-- Timetable table
CREATE TABLE timetable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(20),
    academic_year VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    INDEX idx_uuid (uuid),
    INDEX idx_class_day (class_id, day_of_week),
    INDEX idx_teacher (teacher_id, day_of_week)
);

-- Fee Structure table
CREATE TABLE fee_structure (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    class_id INT,
    academic_year VARCHAR(20) NOT NULL,
    term VARCHAR(20),
    due_date DATE,
    description TEXT,
    is_recurring TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    INDEX idx_uuid (uuid),
    INDEX idx_class_academic (class_id, academic_year)
);

-- Fee Payments table
CREATE TABLE fee_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    student_id INT NOT NULL,
    fee_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'mtn_momo', 'airtel_money', 'bank_transfer', 'other') NOT NULL,
    transaction_id VARCHAR(100),
    payment_date DATETIME NOT NULL,
    recorded_by INT NOT NULL,
    receipt_number VARCHAR(50),
    notes TEXT,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_id) REFERENCES fee_structure(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_uuid (uuid),
    INDEX idx_student (student_id),
    INDEX idx_payment_date (payment_date),
    INDEX idx_status (status)
);

-- Announcements table
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    posted_by INT NOT NULL,
    target_roles JSON,
    target_classes JSON,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    publish_from DATETIME,
    publish_until DATETIME,
    is_published TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    FOREIGN KEY (posted_by) REFERENCES users(id),
    INDEX idx_uuid (uuid),
    INDEX idx_published (is_published)
);

-- Messages table
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    parent_id INT,
    is_read TINYINT(1) DEFAULT 0,
    message_type ENUM('direct', 'broadcast', 'notification') DEFAULT 'direct',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sync_status ENUM('pending', 'synced') DEFAULT 'synced',
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id),
    FOREIGN KEY (parent_id) REFERENCES messages(id),
    INDEX idx_uuid (uuid),
    INDEX idx_sender (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_read (is_read)
);

-- Sync Queue table (for offline-online sync)
CREATE TABLE sync_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    operation ENUM('insert', 'update', 'delete') NOT NULL,
    data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    synced_at DATETIME,
    sync_status ENUM('pending', 'synced', 'failed') DEFAULT 'pending',
    error_message TEXT,
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_status (sync_status),
    INDEX idx_created (created_at)
);

-- Sync Logs table
CREATE TABLE sync_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sync_direction ENUM('push', 'pull') NOT NULL,
    records_synced INT,
    records_failed INT,
    started_at DATETIME NOT NULL,
    completed_at DATETIME,
    details TEXT,
    INDEX idx_direction (sync_direction),
    INDEX idx_date (started_at)
);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value) VALUES 
('school_name', 'EduSync Nexus School'),
('school_tagline', 'Empowering Education'),
('academic_year', '2025-2026'),
('current_term', 'Term 1'),
('currency', 'RWF'),
('sms_provider', 'africastalking'),
('mtn_momo_enabled', '0'),
('airtel_money_enabled', '0'),
('ai_risk_prediction', '1'),
('gamification_enabled', '1'),
('offline_mode', '0');

-- Insert default admin user (password: admin123)
INSERT INTO users (uuid, email, password_hash, role, first_name, last_name, phone) VALUES 
(UUID(), 'admin@edusync.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System', 'Administrator', '+250700000000');