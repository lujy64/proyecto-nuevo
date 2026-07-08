SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- Separa los datos por espacio administrativo.
-- Objetivo: el usuario "personal" no queda mezclado con los datos demo de
-- "admin" / "chofer".
--
-- Se puede ejecutar mas de una vez. Despues de correrlo, cerrar sesion e
-- ingresar nuevamente para refrescar los datos guardados en la sesion.

SET @admin_id = COALESCE((SELECT id FROM users WHERE username = 'admin' LIMIT 1), 1);
SET @personal_id = (SELECT id FROM users WHERE username = 'personal' LIMIT 1);

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'users'
                AND COLUMN_NAME = 'owner_user_id'
        ),
        'SELECT 1',
        'ALTER TABLE users ADD COLUMN owner_user_id INT UNSIGNED NULL AFTER driver_id'
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
                AND TABLE_NAME = 'users'
                AND INDEX_NAME = 'idx_users_owner'
        ),
        'SELECT 1',
        'ALTER TABLE users ADD INDEX idx_users_owner (owner_user_id)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE users
SET owner_user_id = id
WHERE owner_user_id IS NULL;

UPDATE users
SET owner_user_id = @personal_id
WHERE username = 'personal' AND @personal_id IS NOT NULL;

UPDATE users
SET owner_user_id = @admin_id
WHERE username IN ('admin', 'chofer');

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'drivers'
                AND COLUMN_NAME = 'owner_user_id'
        ),
        'SELECT 1',
        'ALTER TABLE drivers ADD COLUMN owner_user_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id'
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
                AND TABLE_NAME = 'drivers'
                AND INDEX_NAME = 'idx_drivers_owner'
        ),
        'SELECT 1',
        'ALTER TABLE drivers ADD INDEX idx_drivers_owner (owner_user_id)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE drivers d
LEFT JOIN (
    SELECT e.driver_id, MIN(COALESCE(u.owner_user_id, e.created_by, @admin_id)) AS owner_user_id
    FROM daily_entries e
    LEFT JOIN users u ON u.id = e.created_by
    WHERE e.created_by IS NOT NULL
    GROUP BY e.driver_id
) owners ON owners.driver_id = d.id
SET d.owner_user_id = COALESCE(owners.owner_user_id, @admin_id);

UPDATE drivers
SET owner_user_id = @admin_id
WHERE full_name = 'Chofer Demo';

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'cars'
                AND COLUMN_NAME = 'owner_user_id'
        ),
        'SELECT 1',
        'ALTER TABLE cars ADD COLUMN owner_user_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id'
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
                AND TABLE_NAME = 'cars'
                AND INDEX_NAME = 'idx_cars_owner'
        ),
        'SELECT 1',
        'ALTER TABLE cars ADD INDEX idx_cars_owner (owner_user_id)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE cars c
LEFT JOIN (
    SELECT e.car_id, MIN(COALESCE(u.owner_user_id, e.created_by, @admin_id)) AS owner_user_id
    FROM daily_entries e
    LEFT JOIN users u ON u.id = e.created_by
    WHERE e.created_by IS NOT NULL
    GROUP BY e.car_id
) owners ON owners.car_id = c.id
SET c.owner_user_id = COALESCE(owners.owner_user_id, @admin_id);

UPDATE cars
SET owner_user_id = @admin_id
WHERE plate = 'AA000AA';

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'drivers'
                AND INDEX_NAME = 'unique_drivers_dni'
        ),
        'ALTER TABLE drivers DROP INDEX unique_drivers_dni',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE drivers ADD UNIQUE KEY unique_drivers_dni (owner_user_id, dni);

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'cars'
                AND INDEX_NAME = 'unique_cars_plate'
        ),
        'ALTER TABLE cars DROP INDEX unique_cars_plate',
        'SELECT 1'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE cars ADD UNIQUE KEY unique_cars_plate (owner_user_id, plate);

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'daily_entries'
                AND COLUMN_NAME = 'owner_user_id'
        ),
        'SELECT 1',
        'ALTER TABLE daily_entries ADD COLUMN owner_user_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id'
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
                AND INDEX_NAME = 'idx_entries_owner'
        ),
        'SELECT 1',
        'ALTER TABLE daily_entries ADD INDEX idx_entries_owner (owner_user_id)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE daily_entries e
LEFT JOIN users u ON u.id = e.created_by
LEFT JOIN drivers d ON d.id = e.driver_id
SET e.owner_user_id = COALESCE(u.owner_user_id, d.owner_user_id, @admin_id);

UPDATE users u
INNER JOIN drivers d ON d.id = u.driver_id
SET u.owner_user_id = d.owner_user_id
WHERE u.role = 'driver';

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'general_expenses'
                AND COLUMN_NAME = 'owner_user_id'
        ),
        'SELECT 1',
        'ALTER TABLE general_expenses ADD COLUMN owner_user_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id'
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
                AND TABLE_NAME = 'general_expenses'
                AND INDEX_NAME = 'idx_general_expenses_owner'
        ),
        'SELECT 1',
        'ALTER TABLE general_expenses ADD INDEX idx_general_expenses_owner (owner_user_id)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE general_expenses ge
LEFT JOIN users u ON u.id = ge.created_by
SET ge.owner_user_id = COALESCE(u.owner_user_id, @admin_id);
