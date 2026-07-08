SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- Ruta Clara - esquema inicial nuevo.
-- Importar este archivo recrea la base desde cero. Hacer backup antes de usarlo
-- sobre una base con datos reales.

DROP TABLE IF EXISTS daily_entries;
DROP TABLE IF EXISTS weekly_money_notes;
DROP TABLE IF EXISTS general_expenses;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS remember_tokens;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS settlements;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS cars;
DROP TABLE IF EXISTS drivers;

CREATE TABLE drivers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL DEFAULT 1,
    full_name VARCHAR(140) NOT NULL,
    phone VARCHAR(60) NULL,
    dni VARCHAR(30) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_drivers_owner (owner_user_id),
    INDEX idx_drivers_status (status),
    INDEX idx_drivers_name (full_name),
    UNIQUE KEY unique_drivers_dni (owner_user_id, dni)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cars (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL DEFAULT 1,
    brand VARCHAR(80) NOT NULL,
    model VARCHAR(80) NOT NULL,
    plate VARCHAR(20) NOT NULL,
    year SMALLINT UNSIGNED NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    current_driver_id INT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cars_owner (owner_user_id),
    INDEX idx_cars_status (status),
    INDEX idx_cars_driver (current_driver_id),
    UNIQUE KEY unique_cars_plate (owner_user_id, plate),
    CONSTRAINT fk_cars_current_driver FOREIGN KEY (current_driver_id) REFERENCES drivers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(140) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'driver') NOT NULL DEFAULT 'driver',
    driver_id INT UNSIGNED NULL,
    owner_user_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_owner (owner_user_id),
    INDEX idx_users_role (role),
    INDEX idx_users_driver (driver_id),
    CONSTRAINT fk_users_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE remember_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    selector CHAR(24) NOT NULL UNIQUE,
    token_hash CHAR(64) NOT NULL,
    user_agent VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_remember_user (user_id),
    INDEX idx_remember_expires (expires_at),
    CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    selector CHAR(24) NOT NULL UNIQUE,
    token_hash CHAR(64) NOT NULL,
    user_agent VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_password_resets_user (user_id),
    INDEX idx_password_resets_expires (expires_at),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE daily_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL DEFAULT 1,
    period_type ENUM('day', 'week') NOT NULL DEFAULT 'day',
    entry_date DATE NOT NULL,
    driver_id INT UNSIGNED NOT NULL,
    car_id INT UNSIGNED NOT NULL,
    gross_income DECIMAL(12,2) NOT NULL DEFAULT 0,
    app_expenses DECIMAL(12,2) NOT NULL DEFAULT 0,
    cash_collected DECIMAL(12,2) NOT NULL DEFAULT 0,
    rental_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    rental_billing_status ENUM('pending', 'invoiced') NOT NULL DEFAULT 'pending',
    fuel_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    distance_km DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_expenses DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    week_number TINYINT UNSIGNED NOT NULL,
    month_number TINYINT UNSIGNED NOT NULL,
    year_number SMALLINT UNSIGNED NOT NULL,
    observations TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_daily_entry (period_type, entry_date, driver_id, car_id),
    INDEX idx_entries_owner (owner_user_id),
    INDEX idx_entries_date (entry_date),
    INDEX idx_entries_driver (driver_id),
    INDEX idx_entries_car (car_id),
    INDEX idx_entries_rental_status (rental_billing_status),
    INDEX idx_entries_period (year_number, month_number, week_number),
    CONSTRAINT fk_entries_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_entries_car FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE RESTRICT,
    CONSTRAINT fk_entries_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_entries_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE general_expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL DEFAULT 1,
    expense_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    observations TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_general_expenses_owner (owner_user_id),
    INDEX idx_general_expenses_date (expense_date),
    CONSTRAINT fk_general_expenses_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_general_expenses_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE weekly_money_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL DEFAULT 1,
    week_start DATE NOT NULL,
    week_end DATE NOT NULL,
    notes TEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_weekly_money_note (owner_user_id, week_start),
    INDEX idx_weekly_money_notes_owner (owner_user_id),
    INDEX idx_weekly_money_notes_week (week_start, week_end),
    CONSTRAINT fk_weekly_money_notes_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_weekly_money_notes_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO drivers (owner_user_id, full_name, phone, dni, status, notes)
VALUES
    (1, 'Chofer Demo', '+54 11 0000-0000', '00000000', 'active', 'Registro demo para probar el rol chofer.');

INSERT INTO cars (owner_user_id, brand, model, plate, year, status, current_driver_id, notes)
VALUES
    (1, 'Toyota', 'Etios', 'AA000AA', 2020, 'active', 1, 'Auto demo asignado al chofer demo.');

INSERT INTO users (full_name, username, password_hash, role, driver_id, owner_user_id, is_active)
VALUES
    ('Administrador', 'admin', SHA2('Cambiar123!', 256), 'admin', NULL, 1, 1),
    ('Usuario Personal', 'personal', SHA2('Cambiar123!', 256), 'admin', NULL, 2, 1),
    ('Chofer Demo', 'chofer', SHA2('Cambiar123!', 256), 'driver', 1, 1, 1);

INSERT INTO daily_entries (
    owner_user_id, period_type, entry_date, driver_id, car_id, gross_income, app_expenses,
    cash_collected, rental_amount, rental_billing_status, fuel_cost, distance_km, total_expenses, net_total, week_number,
    month_number, year_number, observations, created_by, updated_by
)
VALUES
    (1, 'day', CURDATE(), 1, 1, 85000, 3000, 18000, 7000, 'pending', 22000, 180.50, 28000, 57000,
        WEEK(CURDATE(), 3), MONTH(CURDATE()), YEAR(CURDATE()), 'Carga diaria demo.', 1, 1);

INSERT INTO general_expenses (owner_user_id, expense_date, amount, observations, created_by, updated_by)
VALUES
    (1, CURDATE(), 12000, 'Gasto de flota demo.', 1, 1);

INSERT INTO weekly_money_notes (owner_user_id, week_start, week_end, notes, created_by, updated_by)
VALUES
    (1, DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY), 'Demo: se registro el destino del dinero semanal para controlar alquileres, gastos de flota y saldo disponible.', 1, 1);
