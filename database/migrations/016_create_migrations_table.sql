CREATE TABLE IF NOT EXISTS Migrations (
    ID INT PRIMARY KEY AUTO_INCREMENT,
    Migration VARCHAR(255) UNIQUE,
    AppliedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    RolledBackAt TIMESTAMP NULL,
    Checksum VARCHAR(64),
    ExecutionTime INT, -- milliseconds
    Status ENUM('applied', 'rolled_back', 'failed') DEFAULT 'applied',
    ErrorMessage TEXT
);
