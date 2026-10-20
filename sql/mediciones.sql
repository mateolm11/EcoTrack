-- ==============================================================
-- EcoTrack - Script DDL de Base de Datos
-- Recurso principal: mediciones ambientales
-- ==============================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- 1. Creación de la base de datos (para uso local en XAMPP / MySQL)
CREATE DATABASE IF NOT EXISTS ecotrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ecotrack;

-- 2. Creación de la tabla principal 'mediciones'
DROP TABLE IF EXISTS mediciones;

CREATE TABLE mediciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ubicacion VARCHAR(100) NOT NULL COMMENT 'Ciudad o punto de monitoreo ambiental',
  temperatura DECIMAL(4, 1) NOT NULL COMMENT 'Temperatura ambiente registrada en grados Celsius (°C)',
  humedad DECIMAL(4, 1) NOT NULL COMMENT 'Porcentaje de humedad relativa ambiente (% 0-100)',
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora del registro de medición',
  latitud DECIMAL(10, 7) NULL COMMENT 'Latitud geográfica opcional para geolocalización',
  longitud DECIMAL(10, 7) NULL COMMENT 'Longitud geográfica opcional para geolocalización',
  observaciones VARCHAR(255) NULL DEFAULT '' COMMENT 'Notas adicionales del operador o estación',
  INDEX idx_fecha (fecha),
  INDEX idx_ubicacion (ubicacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Datos iniciales con Tres Arroyos primero (#1)
INSERT INTO mediciones (ubicacion, temperatura, humedad, fecha, latitud, longitud, observaciones) VALUES
('Tres Arroyos - Centro', 19.2, 54.0, '2026-10-19 18:00:00', -38.3739000, -60.2798000, 'Estación principal Plaza San Martín'),
('Bahía Blanca - Centro', 18.5, 55.0, '2026-10-19 17:00:00', -38.7183177, -62.2663478, 'Estación urbana Plaza Rivadavia'),
('Monte Hermoso - Playa', 16.8, 75.0, '2026-10-19 16:00:00', -38.9858600, -61.2941900, 'Medición frente al mar con brisa sur'),
('Tornquist - Sierra de la Ventana', 15.0, 48.0, '2026-10-19 15:00:00', -38.1362700, -61.7963300, 'Microclima de serranías'),
('Coronel Suárez - Parque', 16.4, 52.0, '2026-10-19 14:00:00', -37.4547200, -61.9333300, 'Monitoreo ambiental agropecuario');
