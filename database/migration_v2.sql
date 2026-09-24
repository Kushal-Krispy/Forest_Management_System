-- =============================================================================
-- FOREST MANAGEMENT SYSTEM 2.0 - MIGRATION SCRIPT
-- Safe to run on existing forest_management database (preserves data)
-- =============================================================================

USE forest_management;

-- -----------------------------------------------------------------------------
-- USERS: extend roles, soft delete, last login
-- -----------------------------------------------------------------------------
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL DEFAULT NULL AFTER is_active,
  ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL AFTER created_at;

-- -----------------------------------------------------------------------------
-- FORESTS: GIS, codes, soft delete, analytics cache
-- -----------------------------------------------------------------------------
ALTER TABLE forests
  ADD COLUMN IF NOT EXISTS forest_code VARCHAR(20) NULL AFTER forest_id,
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) NULL AFTER location,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) NULL AFTER latitude,
  ADD COLUMN IF NOT EXISTS region VARCHAR(100) NULL AFTER ecosystem_type,
  ADD COLUMN IF NOT EXISTS boundary_geojson TEXT NULL AFTER description,
  ADD COLUMN IF NOT EXISTS vegetation_density DECIMAL(5,2) DEFAULT 50.00 AFTER boundary_geojson,
  ADD COLUMN IF NOT EXISTS health_score DECIMAL(5,2) DEFAULT 0.00 AFTER vegetation_density,
  ADD COLUMN IF NOT EXISTS fire_risk_score DECIMAL(5,2) DEFAULT 0.00 AFTER health_score,
  ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;

UPDATE forests SET forest_code = CONCAT('FR-', LPAD(forest_id, 4, '0')) WHERE forest_code IS NULL;
UPDATE forests SET latitude = 45.5231, longitude = -122.6765 WHERE forest_id = 1 AND latitude IS NULL;
UPDATE forests SET latitude = 40.7128, longitude = -74.0060 WHERE forest_id = 2 AND latitude IS NULL;
UPDATE forests SET region = 'Northern Region' WHERE forest_id = 1 AND region IS NULL;
UPDATE forests SET region = 'Eastern Region' WHERE forest_id = 2 AND region IS NULL;

CREATE UNIQUE INDEX IF NOT EXISTS idx_forests_code ON forests(forest_code);

-- -----------------------------------------------------------------------------
-- ANIMALS: QR, health, location, soft delete
-- -----------------------------------------------------------------------------
ALTER TABLE animals
  ADD COLUMN IF NOT EXISTS qr_code VARCHAR(64) NULL AFTER animal_id,
  ADD COLUMN IF NOT EXISTS health_status ENUM('healthy','injured','sick','critical','deceased') DEFAULT 'healthy' AFTER conservation_status,
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) NULL AFTER notes,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) NULL AFTER latitude,
  ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;

UPDATE animals SET qr_code = CONCAT('FMS-ANM-', LPAD(animal_id, 5, '0')) WHERE qr_code IS NULL;
CREATE UNIQUE INDEX IF NOT EXISTS idx_animals_qr ON animals(qr_code);

-- -----------------------------------------------------------------------------
-- INCIDENTS: workflow, severity, GIS, soft delete
-- -----------------------------------------------------------------------------
ALTER TABLE incidents
  ADD COLUMN IF NOT EXISTS severity ENUM('low','medium','high','critical') DEFAULT 'medium' AFTER category,
  ADD COLUMN IF NOT EXISTS workflow_status ENUM('pending','under_review','verified','resolved','closed') DEFAULT 'verified' AFTER status,
  ADD COLUMN IF NOT EXISTS assigned_officer INT NULL AFTER approved_by,
  ADD COLUMN IF NOT EXISTS investigation_notes TEXT NULL AFTER assigned_officer,
  ADD COLUMN IF NOT EXISTS resolution_notes TEXT NULL AFTER investigation_notes,
  ADD COLUMN IF NOT EXISTS resolution_date DATE NULL AFTER resolution_notes,
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) NULL AFTER resolution_date,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) NULL AFTER latitude,
  ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL AFTER created_at;

UPDATE incidents SET severity = 'high' WHERE LOWER(category) LIKE '%fire%' AND severity = 'medium';
UPDATE incidents SET severity = 'critical' WHERE LOWER(category) LIKE '%poach%' AND severity = 'medium';
UPDATE incidents SET workflow_status = 'resolved' WHERE workflow_status = 'verified';

-- -----------------------------------------------------------------------------
-- PENDING INCIDENTS: extended workflow
-- -----------------------------------------------------------------------------
ALTER TABLE pending_incidents
  ADD COLUMN IF NOT EXISTS severity ENUM('low','medium','high','critical') DEFAULT 'medium' AFTER category,
  ADD COLUMN IF NOT EXISTS workflow_status ENUM('pending','under_review','verified','resolved','closed') DEFAULT 'pending' AFTER status,
  ADD COLUMN IF NOT EXISTS assigned_officer INT NULL AFTER reviewed_by,
  ADD COLUMN IF NOT EXISTS investigation_notes TEXT NULL AFTER assigned_officer,
  ADD COLUMN IF NOT EXISTS resolution_notes TEXT NULL AFTER investigation_notes,
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) NULL AFTER resolution_notes,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) NULL AFTER latitude;

-- -----------------------------------------------------------------------------
-- AUDIT LOG: extend
-- -----------------------------------------------------------------------------
ALTER TABLE audit_log
  ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL AFTER performed_by,
  ADD COLUMN IF NOT EXISTS device_info VARCHAR(255) NULL AFTER ip_address;

-- -----------------------------------------------------------------------------
-- NEW TABLES
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS activity_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action_type VARCHAR(50) NOT NULL,
  table_name VARCHAR(50) NULL,
  record_id INT NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  device_info VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_activity_user (user_id),
  INDEX idx_activity_action (action_type),
  INDEX idx_activity_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  attempt_id INT AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  attempt_key VARCHAR(50) NOT NULL,
  success TINYINT(1) DEFAULT 0,
  user_agent VARCHAR(255) NULL,
  attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_ip (ip_address, attempt_key, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS animal_population_history (
  history_id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL,
  forest_id INT NOT NULL,
  population INT NOT NULL DEFAULT 0 CHECK (population >= 0),
  birth_rate DECIMAL(5,2) DEFAULT 0.00,
  death_rate DECIMAL(5,2) DEFAULT 0.00,
  migration_count INT DEFAULT 0,
  relocation_count INT DEFAULT 0,
  recorded_date DATE NOT NULL,
  recorded_by INT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pop_animal FOREIGN KEY (animal_id) REFERENCES animals(animal_id) ON DELETE CASCADE,
  CONSTRAINT fk_pop_forest FOREIGN KEY (forest_id) REFERENCES forests(forest_id) ON DELETE CASCADE,
  CONSTRAINT fk_pop_user FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_pop_animal (animal_id),
  INDEX idx_pop_date (recorded_date),
  INDEX idx_pop_forest (forest_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS weather_data (
  weather_id INT AUTO_INCREMENT PRIMARY KEY,
  forest_id INT NOT NULL,
  temperature DECIMAL(5,2) NOT NULL,
  humidity DECIMAL(5,2) NOT NULL,
  rainfall DECIMAL(7,2) DEFAULT 0.00,
  wind_speed DECIMAL(5,2) DEFAULT 0.00,
  recorded_date DATE NOT NULL,
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_weather_forest FOREIGN KEY (forest_id) REFERENCES forests(forest_id) ON DELETE CASCADE,
  INDEX idx_weather_forest (forest_id),
  INDEX idx_weather_date (recorded_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS air_quality (
  air_id INT AUTO_INCREMENT PRIMARY KEY,
  forest_id INT NOT NULL,
  aqi INT NOT NULL CHECK (aqi >= 0 AND aqi <= 500),
  pm25 DECIMAL(6,2) DEFAULT 0.00,
  pm10 DECIMAL(6,2) DEFAULT 0.00,
  co2_level DECIMAL(7,2) DEFAULT 0.00,
  recorded_date DATE NOT NULL,
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_air_forest FOREIGN KEY (forest_id) REFERENCES forests(forest_id) ON DELETE CASCADE,
  INDEX idx_air_forest (forest_id),
  INDEX idx_air_date (recorded_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS water_quality (
  water_id INT AUTO_INCREMENT PRIMARY KEY,
  forest_id INT NOT NULL,
  ph_level DECIMAL(4,2) NOT NULL,
  dissolved_oxygen DECIMAL(5,2) DEFAULT 0.00,
  turbidity DECIMAL(5,2) DEFAULT 0.00,
  quality_index DECIMAL(5,2) DEFAULT 0.00,
  recorded_date DATE NOT NULL,
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_water_forest FOREIGN KEY (forest_id) REFERENCES forests(forest_id) ON DELETE CASCADE,
  INDEX idx_water_forest (forest_id),
  INDEX idx_water_date (recorded_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sensor_data (
  sensor_id INT AUTO_INCREMENT PRIMARY KEY,
  forest_id INT NOT NULL,
  sensor_type VARCHAR(50) NOT NULL,
  sensor_value DECIMAL(10,4) NOT NULL,
  unit VARCHAR(20) DEFAULT '',
  recorded_date DATE NOT NULL,
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sensor_forest FOREIGN KEY (forest_id) REFERENCES forests(forest_id) ON DELETE CASCADE,
  INDEX idx_sensor_forest (forest_id),
  INDEX idx_sensor_type (sensor_type),
  INDEX idx_sensor_date (recorded_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  title VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('incident','fire','wildlife','poaching','sighting','fire_risk','environmental','system') NOT NULL,
  reference_table VARCHAR(50) NULL,
  reference_id INT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_notif_user (user_id),
  INDEX idx_notif_read (is_read),
  INDEX idx_notif_type (type),
  INDEX idx_notif_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS forest_health_scores (
  score_id INT AUTO_INCREMENT PRIMARY KEY,
  forest_id INT NOT NULL,
  health_score DECIMAL(5,2) NOT NULL CHECK (health_score >= 0 AND health_score <= 100),
  biodiversity_score DECIMAL(5,2) DEFAULT 0.00,
  population_score DECIMAL(5,2) DEFAULT 0.00,
  incident_score DECIMAL(5,2) DEFAULT 0.00,
  fire_score DECIMAL(5,2) DEFAULT 0.00,
  environmental_score DECIMAL(5,2) DEFAULT 0.00,
  rating ENUM('poor','fair','good','excellent') NOT NULL,
  calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_health_forest FOREIGN KEY (forest_id) REFERENCES forests(forest_id) ON DELETE CASCADE,
  INDEX idx_health_forest (forest_id),
  INDEX idx_health_date (calculated_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fire_risk_scores (
  risk_id INT AUTO_INCREMENT PRIMARY KEY,
  forest_id INT NOT NULL,
  risk_score DECIMAL(5,2) NOT NULL CHECK (risk_score >= 0 AND risk_score <= 100),
  risk_level ENUM('low','moderate','high','critical') NOT NULL,
  fire_incidents_factor DECIMAL(5,2) DEFAULT 0.00,
  temperature_factor DECIMAL(5,2) DEFAULT 0.00,
  humidity_factor DECIMAL(5,2) DEFAULT 0.00,
  rainfall_factor DECIMAL(5,2) DEFAULT 0.00,
  vegetation_factor DECIMAL(5,2) DEFAULT 0.00,
  recommendations TEXT NULL,
  calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_risk_forest FOREIGN KEY (forest_id) REFERENCES forests(forest_id) ON DELETE CASCADE,
  INDEX idx_risk_forest (forest_id),
  INDEX idx_risk_level (risk_level),
  INDEX idx_risk_date (calculated_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS report_history (
  report_id INT AUTO_INCREMENT PRIMARY KEY,
  report_type ENUM('forest_status','incident_investigation','custom') NOT NULL,
  report_title VARCHAR(200) NOT NULL,
  forest_id INT NULL,
  incident_id INT NULL,
  generated_by INT NOT NULL,
  file_format ENUM('pdf','excel','csv','html') DEFAULT 'html',
  filters_json TEXT NULL,
  executive_summary TEXT NULL,
  download_count INT DEFAULT 0,
  generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_report_forest FOREIGN KEY (forest_id) REFERENCES forests(forest_id) ON DELETE SET NULL,
  CONSTRAINT fk_report_incident FOREIGN KEY (incident_id) REFERENCES incidents(incident_id) ON DELETE SET NULL,
  CONSTRAINT fk_report_user FOREIGN KEY (generated_by) REFERENCES users(user_id) ON DELETE RESTRICT,
  INDEX idx_report_type (report_type),
  INDEX idx_report_date (generated_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS report_schedules (
  schedule_id INT AUTO_INCREMENT PRIMARY KEY,
  report_type ENUM('forest_status','incident_investigation') NOT NULL,
  frequency ENUM('daily','weekly','monthly') NOT NULL,
  forest_id INT NULL,
  created_by INT NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  last_run TIMESTAMP NULL,
  next_run TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_schedule_forest FOREIGN KEY (forest_id) REFERENCES forests(forest_id) ON DELETE SET NULL,
  CONSTRAINT fk_schedule_user FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS animal_sightings (
  sighting_id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL,
  forest_id INT NOT NULL,
  latitude DECIMAL(10,8) NULL,
  longitude DECIMAL(11,8) NULL,
  health_status ENUM('healthy','injured','sick','critical','deceased') DEFAULT 'healthy',
  notes TEXT NULL,
  sighted_by INT NULL,
  sighted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sighting_animal FOREIGN KEY (animal_id) REFERENCES animals(animal_id) ON DELETE CASCADE,
  CONSTRAINT fk_sighting_forest FOREIGN KEY (forest_id) REFERENCES forests(forest_id) ON DELETE CASCADE,
  CONSTRAINT fk_sighting_user FOREIGN KEY (sighted_by) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_sighting_animal (animal_id),
  INDEX idx_sighting_date (sighted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS animal_qr_scans (
  scan_id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL,
  scan_type ENUM('health','location','sighting','relocation') NOT NULL,
  scan_data TEXT NULL,
  scanned_by INT NULL,
  latitude DECIMAL(10,8) NULL,
  longitude DECIMAL(11,8) NULL,
  scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_scan_animal FOREIGN KEY (animal_id) REFERENCES animals(animal_id) ON DELETE CASCADE,
  CONSTRAINT fk_scan_user FOREIGN KEY (scanned_by) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_scan_animal (animal_id),
  INDEX idx_scan_date (scanned_at)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- PERFORMANCE INDEXES
-- -----------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_incidents_forest ON incidents(forest_id);
CREATE INDEX IF NOT EXISTS idx_incidents_category ON incidents(category);
CREATE INDEX IF NOT EXISTS idx_incidents_status ON incidents(workflow_status);
CREATE INDEX IF NOT EXISTS idx_incidents_severity ON incidents(severity);
CREATE INDEX IF NOT EXISTS idx_incidents_date ON incidents(incident_date);
CREATE INDEX IF NOT EXISTS idx_animals_forest ON animals(forest_id);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- -----------------------------------------------------------------------------
-- SEED: population history, environmental data, demo users
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO users (username, email, password_hash, full_name, role) VALUES
('wildlife1', 'wildlife@forest.gov', '$2y$10$G6M9pPFeLG8K0YV2FoG1eOUJX/BBS0uh2sRXvgvbtKVhS70EVxhaW', 'Wildlife Officer Sarah', 'wildlife_officer'),
('analyst1', 'analyst@forest.gov', '$2y$10$G6M9pPFeLG8K0YV2FoG1eOUJX/BBS0uh2sRXvgvbtKVhS70EVxhaW', 'Data Analyst Mike', 'data_analyst');

UPDATE users SET role = 'forest_officer' WHERE username = 'officer1';

INSERT INTO animal_population_history (animal_id, forest_id, population, birth_rate, death_rate, migration_count, relocation_count, recorded_date, recorded_by)
SELECT a.animal_id, a.forest_id, a.population_estimate,
  ROUND(RAND()*5+1,2), ROUND(RAND()*2,2), FLOOR(RAND()*10), FLOOR(RAND()*3),
  DATE_SUB(CURDATE(), INTERVAL m MONTH), 2
FROM animals a
CROSS JOIN (SELECT 0 m UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) months
WHERE NOT EXISTS (SELECT 1 FROM animal_population_history LIMIT 1);

INSERT INTO weather_data (forest_id, temperature, humidity, rainfall, wind_speed, recorded_date)
SELECT f.forest_id, ROUND(15+RAND()*20,2), ROUND(40+RAND()*40,2), ROUND(RAND()*50,2), ROUND(RAND()*15,2),
  DATE_SUB(CURDATE(), INTERVAL d DAY)
FROM forests f
CROSS JOIN (SELECT 0 d UNION SELECT 7 UNION SELECT 14 UNION SELECT 21 UNION SELECT 28) days
WHERE NOT EXISTS (SELECT 1 FROM weather_data LIMIT 1);

INSERT INTO air_quality (forest_id, aqi, pm25, pm10, co2_level, recorded_date)
SELECT f.forest_id, FLOOR(30+RAND()*120), ROUND(RAND()*25,2), ROUND(RAND()*40,2), ROUND(350+RAND()*100,2),
  DATE_SUB(CURDATE(), INTERVAL d DAY)
FROM forests f
CROSS JOIN (SELECT 0 d UNION SELECT 7 UNION SELECT 14 UNION SELECT 21) days
WHERE NOT EXISTS (SELECT 1 FROM air_quality LIMIT 1);

INSERT INTO water_quality (forest_id, ph_level, dissolved_oxygen, turbidity, quality_index, recorded_date)
SELECT f.forest_id, ROUND(6.5+RAND()*2,2), ROUND(5+RAND()*5,2), ROUND(RAND()*10,2), ROUND(60+RAND()*35,2),
  DATE_SUB(CURDATE(), INTERVAL d DAY)
FROM forests f
CROSS JOIN (SELECT 0 d UNION SELECT 7 UNION SELECT 14) days
WHERE NOT EXISTS (SELECT 1 FROM water_quality LIMIT 1);
