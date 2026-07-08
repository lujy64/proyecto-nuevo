SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- Agrega una observacion semanal administrativa para registrar que se hizo
-- con la plata de la flota/empresa.
--
-- Se puede ejecutar mas de una vez.

CREATE TABLE IF NOT EXISTS weekly_money_notes (
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
