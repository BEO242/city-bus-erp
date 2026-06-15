-- Migration: équipage multiple + colonnes supplémentaires sur trips
-- Date: 2026-05-04

-- 1. Colonnes supplémentaires sur trips
ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS arrival_scheduled  TIME          NULL AFTER departure_scheduled,
    ADD COLUMN IF NOT EXISTS mileage_start      INT UNSIGNED  NULL AFTER arrival_actual,
    ADD COLUMN IF NOT EXISTS mileage_end        INT UNSIGNED  NULL AFTER mileage_start,
    ADD COLUMN IF NOT EXISTS weather_conditions VARCHAR(100)  NULL AFTER mileage_end,
    ADD COLUMN IF NOT EXISTS incident_notes     TEXT          NULL AFTER weather_conditions;

-- 2. Table équipage (plusieurs membres par voyage)
CREATE TABLE IF NOT EXISTS trip_crew (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    trip_id     BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    role        ENUM('chauffeur','convoyeur','caissier','controleur','guide','autre') NOT NULL DEFAULT 'convoyeur',
    notes       VARCHAR(255) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id)     REFERENCES trips(id)     ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY uq_trip_employee (trip_id, employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
