SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- Ejecutar sobre una base existente para adaptar Carga a:
-- dia/semana, ganancias app, devoluciones/gastos app, efectivo, alquiler
-- a facturar, estado de facturacion, combustible informativo, kilometros
-- y gastos generales de flota/empresa sin chofer.
--
-- Esta version se puede volver a ejecutar: si una columna o indice ya existe,
-- lo saltea para evitar errores como "Nombre duplicado de columna".

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'daily_entries'
                AND COLUMN_NAME = 'period_type'
        ),
        'SELECT 1',
        'ALTER TABLE daily_entries ADD COLUMN period_type ENUM(''day'', ''week'') NOT NULL DEFAULT ''day'' AFTER id'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'daily_entries'
                AND COLUMN_NAME = 'app_expenses'
        ),
        'SELECT 1',
        'ALTER TABLE daily_entries ADD COLUMN app_expenses DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER gross_income'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'daily_entries'
                AND COLUMN_NAME = 'cash_collected'
        ),
        'SELECT 1',
        'ALTER TABLE daily_entries ADD COLUMN cash_collected DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER app_expenses'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'daily_entries'
                AND COLUMN_NAME = 'rental_amount'
        ),
        'SELECT 1',
        'ALTER TABLE daily_entries ADD COLUMN rental_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER cash_collected'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'daily_entries'
                AND COLUMN_NAME = 'rental_billing_status'
        ),
        'SELECT 1',
        'ALTER TABLE daily_entries ADD COLUMN rental_billing_status ENUM(''pending'', ''invoiced'') NOT NULL DEFAULT ''pending'' AFTER rental_amount'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'daily_entries'
                AND COLUMN_NAME = 'distance_km'
        ),
        'SELECT 1',
        'ALTER TABLE daily_entries ADD COLUMN distance_km DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER fuel_cost'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_old_expense_columns = (
    SELECT COUNT(*) = 4
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'daily_entries'
        AND COLUMN_NAME IN ('wash_cost', 'tolls_cost', 'maintenance_cost', 'other_expenses')
);

SET @sql = IF(
    @has_old_expense_columns,
    'UPDATE daily_entries
        SET
            period_type = CASE WHEN period_type = ''week'' THEN ''week'' ELSE ''day'' END,
            app_expenses = CASE
                WHEN app_expenses = 0 THEN COALESCE(wash_cost, 0) + COALESCE(tolls_cost, 0) + COALESCE(maintenance_cost, 0) + COALESCE(other_expenses, 0)
                ELSE app_expenses
            END,
            cash_collected = COALESCE(cash_collected, 0),
            rental_amount = COALESCE(rental_amount, 0),
            rental_billing_status = CASE WHEN rental_billing_status = ''invoiced'' THEN ''invoiced'' ELSE ''pending'' END,
            distance_km = COALESCE(distance_km, 0),
            total_expenses = CASE
                WHEN total_expenses = 0 THEN COALESCE(wash_cost, 0) + COALESCE(tolls_cost, 0) + COALESCE(maintenance_cost, 0) + COALESCE(other_expenses, 0)
                ELSE total_expenses
            END,
            net_total = CASE
                WHEN net_total = 0 THEN gross_income - (COALESCE(wash_cost, 0) + COALESCE(tolls_cost, 0) + COALESCE(maintenance_cost, 0) + COALESCE(other_expenses, 0))
                ELSE net_total
            END',
    'UPDATE daily_entries
        SET
            period_type = CASE WHEN period_type = ''week'' THEN ''week'' ELSE ''day'' END,
            app_expenses = COALESCE(app_expenses, 0),
            cash_collected = COALESCE(cash_collected, 0),
            rental_amount = COALESCE(rental_amount, 0),
            rental_billing_status = CASE WHEN rental_billing_status = ''invoiced'' THEN ''invoiced'' ELSE ''pending'' END,
            distance_km = COALESCE(distance_km, 0),
            total_expenses = COALESCE(total_expenses, 0),
            net_total = CASE
                WHEN net_total = 0 THEN gross_income - total_expenses
                ELSE net_total
            END'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'daily_entries'
                AND INDEX_NAME = 'unique_daily_entry'
        ),
        'ALTER TABLE daily_entries DROP INDEX unique_daily_entry',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'daily_entries'
                AND INDEX_NAME = 'unique_daily_entry'
        ),
        'SELECT 1',
        'ALTER TABLE daily_entries ADD UNIQUE KEY unique_daily_entry (period_type, entry_date, driver_id, car_id)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'daily_entries'
                AND INDEX_NAME = 'idx_entries_rental_status'
        ),
        'SELECT 1',
        'ALTER TABLE daily_entries ADD INDEX idx_entries_rental_status (rental_billing_status)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS general_expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    observations TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_general_expenses_date (expense_date),
    CONSTRAINT fk_general_expenses_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_general_expenses_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
