-- ============================================================
-- schema.sql — reference schema for the Bank of VULN lab
-- (documentation only; database.php auto-creates everything)
-- ============================================================
CREATE DATABASE IF NOT EXISTS vulnerable_bank;
USE vulnerable_bank;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(120) NOT NULL,
    password VARCHAR(255) NOT NULL,          -- stored PLAINTEXT (bug)
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    account_number VARCHAR(20) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_account VARCHAR(20) NOT NULL,
    to_account VARCHAR(20) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    note VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
