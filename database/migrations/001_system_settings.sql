USE interntrack;

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    identity_hash CHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_identity_time (identity_hash, attempted_at)
);

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('system_name', 'InternTrack'),
('system_description', 'Internship/OJT Monitoring and Management System'),
('system_logo', ''),
('contact_email', ''),
('system_timezone', 'Asia/Manila'),
('date_format', 'M d, Y'),
('time_format', 'h:i A'),
('minimum_password_length', '8'),
('session_timeout_minutes', '30'),
('login_attempt_limit', '5'),
('login_lockout_minutes', '15'),
('automatic_logout', '1'),
('maintenance_mode', '0'),
('application_version', '1.0.0'),
('last_backup_at', '');
