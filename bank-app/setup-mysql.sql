-- ============================================================
-- setup-mysql.sql — provision the MySQL database + app user
-- for the Bank of VULN lab.
--
--   Run ONCE as an admin (root) user:
--     XAMPP:  /Applications/XAMPP/xamppfiles/bin/mysql -u root < setup-mysql.sql
--     remote: mysql -h <host> -u <admin-user> -p < setup-mysql.sql
--
--   The user/password here MUST match bank-app/.env (DB_USER / DB_PASS).
--   To use different credentials, edit BOTH this file and `.env`.
-- ============================================================

-- 1. The database itself
CREATE DATABASE IF NOT EXISTS vulnerable_bank
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. A dedicated application user (never `root`).
--    Both hostnames are created because PHP may reach the server via
--    127.0.0.1 while local admin tools use localhost.
CREATE USER IF NOT EXISTS 'bank_app'@'localhost'  IDENTIFIED BY 'BankVuln@2026!';
CREATE USER IF NOT EXISTS 'bank_app'@'127.0.0.1'  IDENTIFIED BY 'BankVuln@2026!';

-- 3. Day-to-day access: full rights inside the bank database only.
GRANT ALL PRIVILEGES ON vulnerable_bank.* TO 'bank_app'@'localhost';
GRANT ALL PRIVILEGES ON vulnerable_bank.* TO 'bank_app'@'127.0.0.1';

-- 4. Lab convenience: lets the app self-bootstrap the database on first
--    request (CREATE DATABASE IF NOT EXISTS). In a strict production setup,
--    remove this line and pre-create the DB as admin instead.
GRANT CREATE ON *.* TO 'bank_app'@'localhost';
GRANT CREATE ON *.* TO 'bank_app'@'127.0.0.1';

FLUSH PRIVILEGES;
