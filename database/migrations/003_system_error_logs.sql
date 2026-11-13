CREATE TABLE IF NOT EXISTS system_error_logs (
    error_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    error_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    severity ENUM('low','medium','high') NOT NULL DEFAULT 'low',
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_system_error_logs_occurred_at (occurred_at)
);
