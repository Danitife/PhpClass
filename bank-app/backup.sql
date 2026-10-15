-- ============================================================
-- backup.sql — leaked database dump (oops!)
-- The ops team scheduled nightly dumps INTO the web root so the
-- dev could "grab a copy quickly". Guess the filename? Own the DB.
-- ============================================================
-- Server: 127.0.0.1   Database: vulnerable_bank   Dump: 2026-08-12 03:15:00

DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL,
  password VARCHAR(255) NOT NULL,          -- PLAINTEXT (yikes)
  role VARCHAR(20) NOT NULL DEFAULT 'user',
  balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  account_number VARCHAR(20) NOT NULL UNIQUE
);

INSERT INTO users (id, username, full_name, email, password, role, balance, account_number) VALUES
(1, 'admin',   'System Administrator', 'admin@bank.local',   'Admin@123',  'admin', 999999.99, '1000000001'),
(2, 'alice',   'Alice Wonder',         'alice@bank.local',   'alice123',   'user',  1000.00,   '1000000002'),
(3, 'bob',     'Bob Builder',          'bob@bank.local',     'bob123',     'user',  500.00,    '1000000003'),
(4, 'mallory', 'Mallory Malice',       'mallory@bank.local', 'mallory123', 'user',  25.00,     '1000000004');

-- ...and 1,234 more rows just like these...
