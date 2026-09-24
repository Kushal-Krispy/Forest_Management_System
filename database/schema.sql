-- =============================================================================
-- FOREST MANAGEMENT SYSTEM - DATABASE SCHEMA
-- =============================================================================
-- DBMS concepts demonstrated:
--   PRIMARY KEY   : Unique identifier on each table (user_id, animal_id, etc.)
--   FOREIGN KEY   : Links child rows to parent tables (e.g. animals.forest_id -> forests)
--   TRIGGER       : Automatic actions on INSERT/UPDATE/DELETE
--   CURSOR        : Stored procedure sp_incident_category_report uses a cursor to iterate categories
--
-- Run: setup/install.php (recommended), or mysql -u root < database/schema.sql
-- If procedures fail in phpMyAdmin SQL tab, Import database/procedures.sql instead.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS forest_management
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE forest_management;

-- -----------------------------------------------------------------------------
-- TABLE: users
-- STORES: Login credentials and role (admin | officer | public)
-- PRIMARY KEY: user_id
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS pending_incidents;
DROP TABLE IF EXISTS incidents;
DROP TABLE IF EXISTS animals;
DROP TABLE IF EXISTS forests;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  email         VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(100) NOT NULL,
  role          ENUM('admin','officer','public') NOT NULL DEFAULT 'public',
  last_login    TIMESTAMP NULL DEFAULT NULL,
  deleted_at    TIMESTAMP NULL DEFAULT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- TABLE: forests
-- STORES: Forest area information entered by admin
-- PRIMARY KEY: forest_id | FOREIGN KEY: created_by -> users(user_id)
-- -----------------------------------------------------------------------------
CREATE TABLE forests (
  forest_id       INT AUTO_INCREMENT PRIMARY KEY,
  forest_code     VARCHAR(20) UNIQUE,
  forest_name     VARCHAR(150) NOT NULL,
  location        VARCHAR(200) NOT NULL,
  latitude        DECIMAL(10,8) NULL,
  longitude       DECIMAL(11,8) NULL,
  area_sq_km      DECIMAL(10,2) NOT NULL,
  ecosystem_type  VARCHAR(100) NOT NULL,
  region          VARCHAR(100) NULL,
  description     TEXT,
  boundary_geojson TEXT NULL,
  vegetation_density DECIMAL(5,2) DEFAULT 50.00,
  health_score    DECIMAL(5,2) DEFAULT 0.00,
  fire_risk_score DECIMAL(5,2) DEFAULT 0.00,
  established_year INT,
  created_by      INT NOT NULL,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_forests_created_by FOREIGN KEY (created_by) REFERENCES users(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- TABLE: animals
-- STORES: Species, population, conservation status, officer/admin uploaded image
-- PRIMARY KEY: animal_id | FOREIGN KEYS: forest_id, added_by
-- -----------------------------------------------------------------------------
CREATE TABLE animals (
  animal_id            INT AUTO_INCREMENT PRIMARY KEY,
  qr_code              VARCHAR(64) UNIQUE,
  forest_id            INT NOT NULL,
  species_name         VARCHAR(150) NOT NULL,
  common_name          VARCHAR(150) NOT NULL,
  population_estimate  INT NOT NULL DEFAULT 0,
  conservation_status  ENUM('Least Concern','Near Threatened','Vulnerable','Endangered','Critically Endangered') NOT NULL,
  health_status        ENUM('healthy','injured','sick','critical','deceased') DEFAULT 'healthy',
  image_path           VARCHAR(255) DEFAULT NULL,
  notes                TEXT,
  latitude             DECIMAL(10,8) NULL,
  longitude            DECIMAL(11,8) NULL,
  added_by             INT NOT NULL,
  updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at           TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_animals_forest FOREIGN KEY (forest_id) REFERENCES forests(forest_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_animals_added_by FOREIGN KEY (added_by) REFERENCES users(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- TABLE: incidents (approved / official records)
-- STORES: Verified incidents with category, description, image
-- PRIMARY KEY: incident_id | FOREIGN KEYS: forest_id, reported_by, approved_by
-- -----------------------------------------------------------------------------
CREATE TABLE incidents (
  incident_id     INT AUTO_INCREMENT PRIMARY KEY,
  forest_id       INT,
  category        VARCHAR(80) NOT NULL,
  severity        ENUM('low','medium','high','critical') DEFAULT 'medium',
  description     TEXT NOT NULL,
  image_path      VARCHAR(255) DEFAULT NULL,
  incident_date   DATE NOT NULL,
  reported_by     INT NOT NULL,
  approved_by     INT DEFAULT NULL,
  status          ENUM('approved','rejected') NOT NULL DEFAULT 'approved',
  workflow_status ENUM('pending','under_review','verified','resolved','closed') DEFAULT 'verified',
  source          ENUM('officer','public_approved') NOT NULL DEFAULT 'officer',
  assigned_officer INT NULL,
  investigation_notes TEXT NULL,
  resolution_notes TEXT NULL,
  resolution_date DATE NULL,
  latitude        DECIMAL(10,8) NULL,
  longitude       DECIMAL(11,8) NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  deleted_at      TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_incidents_forest FOREIGN KEY (forest_id) REFERENCES forests(forest_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_incidents_reported_by FOREIGN KEY (reported_by) REFERENCES users(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_incidents_approved_by FOREIGN KEY (approved_by) REFERENCES users(user_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- TABLE: pending_incidents
-- STORES: Public-submitted reports awaiting officer/admin approval
-- PRIMARY KEY: pending_id | FOREIGN KEY: reported_by -> users
-- On APPROVE: trigger/procedure moves row to incidents table
-- -----------------------------------------------------------------------------
CREATE TABLE pending_incidents (
  pending_id      INT AUTO_INCREMENT PRIMARY KEY,
  forest_id       INT,
  category        VARCHAR(80) NOT NULL,
  description     TEXT NOT NULL,
  image_path      VARCHAR(255) NOT NULL,
  reported_by     INT NOT NULL,
  status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by     INT DEFAULT NULL,
  review_notes    TEXT,
  submitted_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_at     TIMESTAMP NULL,
  CONSTRAINT fk_pending_forest FOREIGN KEY (forest_id) REFERENCES forests(forest_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_pending_reported_by FOREIGN KEY (reported_by) REFERENCES users(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_pending_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- TABLE: audit_log
-- STORES: Automatic log entries from TRIGGERS (transparency trail)
-- -----------------------------------------------------------------------------
CREATE TABLE audit_log (
  log_id        INT AUTO_INCREMENT PRIMARY KEY,
  table_name    VARCHAR(50) NOT NULL,
  record_id     INT NOT NULL,
  action_type   VARCHAR(20) NOT NULL,
  details       TEXT,
  performed_by  INT,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (performed_by) REFERENCES users(user_id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================================================
-- TRIGGERS
-- =============================================================================

DELIMITER $$

DROP TRIGGER IF EXISTS trg_animals_after_insert$$
DROP TRIGGER IF EXISTS trg_animals_after_update$$
DROP TRIGGER IF EXISTS trg_forests_after_update$$
DROP TRIGGER IF EXISTS trg_pending_after_insert$$

-- TRIGGER: Log when a new animal is added
CREATE TRIGGER trg_animals_after_insert
AFTER INSERT ON animals
FOR EACH ROW
BEGIN
  INSERT INTO audit_log (table_name, record_id, action_type, details, performed_by)
  VALUES ('animals', NEW.animal_id, 'INSERT',
    CONCAT('Animal added: ', NEW.common_name, ' (', NEW.species_name, ')'),
    NEW.added_by);
END$$

-- TRIGGER: Log when animal population or status is updated
CREATE TRIGGER trg_animals_after_update
AFTER UPDATE ON animals
FOR EACH ROW
BEGIN
  INSERT INTO audit_log (table_name, record_id, action_type, details, performed_by)
  VALUES ('animals', NEW.animal_id, 'UPDATE',
    CONCAT('Population: ', OLD.population_estimate, ' -> ', NEW.population_estimate,
           ' | Status: ', OLD.conservation_status, ' -> ', NEW.conservation_status),
    NEW.added_by);
END$$

-- TRIGGER: Log when forest information is modified
CREATE TRIGGER trg_forests_after_update
AFTER UPDATE ON forests
FOR EACH ROW
BEGIN
  INSERT INTO audit_log (table_name, record_id, action_type, details, performed_by)
  VALUES ('forests', NEW.forest_id, 'UPDATE',
    CONCAT('Forest updated: ', NEW.forest_name), NEW.created_by);
END$$

-- TRIGGER: Log new pending public incident submission
CREATE TRIGGER trg_pending_after_insert
AFTER INSERT ON pending_incidents
FOR EACH ROW
BEGIN
  INSERT INTO audit_log (table_name, record_id, action_type, details, performed_by)
  VALUES ('pending_incidents', NEW.pending_id, 'INSERT',
    CONCAT('Public report submitted - Category: ', NEW.category), NEW.reported_by);
END$$

DELIMITER ;

-- =============================================================================
-- STORED PROCEDURE WITH CURSOR (DBMS Cursor concept)
-- Iterates incident categories and builds analytical report counts
-- Called from PHP: CALL sp_incident_category_report();
-- =============================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_incident_category_report$$
DROP PROCEDURE IF EXISTS sp_approve_pending_incident$$

CREATE PROCEDURE sp_incident_category_report()
BEGIN
  -- All DECLARE must be first in MariaDB/MySQL procedures
  DECLARE done INT DEFAULT FALSE;
  DECLARE v_category VARCHAR(80);
  DECLARE v_count INT;
  DECLARE v_label VARCHAR(50);

  DECLARE cur_categories CURSOR FOR
    SELECT category, COUNT(*) AS cnt
    FROM incidents
    WHERE status = 'approved'
    GROUP BY category
    ORDER BY cnt DESC;

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

  DROP TEMPORARY TABLE IF EXISTS tmp_category_report;
  CREATE TEMPORARY TABLE tmp_category_report (
    category_name VARCHAR(80),
    incident_count INT,
    fire_frequency_label VARCHAR(50)
  );

  OPEN cur_categories;

  category_loop: LOOP
    FETCH cur_categories INTO v_category, v_count;
    IF done THEN
      LEAVE category_loop;
    END IF;

    -- Example analytics: Fire frequency labeling
    IF LOWER(v_category) LIKE '%fire%' THEN
      IF v_count >= 10 THEN SET v_label = 'High Frequency';
      ELSEIF v_count >= 5 THEN SET v_label = 'Moderate Frequency';
      ELSE SET v_label = 'Low Frequency';
      END IF;
    ELSE
      SET v_label = 'Standard';
    END IF;

    INSERT INTO tmp_category_report (category_name, incident_count, fire_frequency_label)
    VALUES (v_category, v_count, v_label);
  END LOOP;

  CLOSE cur_categories;

  SELECT * FROM tmp_category_report;
END$$

-- Approve pending incident: moves to incidents + updates pending status
CREATE PROCEDURE sp_approve_pending_incident(
  IN p_pending_id INT,
  IN p_reviewer_id INT,
  IN p_forest_id INT
)
BEGIN
  DECLARE v_category VARCHAR(80);
  DECLARE v_description TEXT;
  DECLARE v_image VARCHAR(255);
  DECLARE v_reported_by INT;
  DECLARE v_date DATE;

  SELECT category, description, image_path, reported_by, DATE(submitted_at)
  INTO v_category, v_description, v_image, v_reported_by, v_date
  FROM pending_incidents
  WHERE pending_id = p_pending_id AND status = 'pending';

  INSERT INTO incidents (forest_id, category, description, image_path, incident_date,
    reported_by, approved_by, status, source)
  VALUES (p_forest_id, v_category, v_description, v_image, v_date,
    v_reported_by, p_reviewer_id, 'approved', 'public_approved');

  UPDATE pending_incidents
  SET status = 'approved', reviewed_by = p_reviewer_id, reviewed_at = NOW()
  WHERE pending_id = p_pending_id;
END$$

DELIMITER ;

-- =============================================================================
-- SEED DATA (default password for all: password123)
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- =============================================================================

-- Password for all demo users: password123
INSERT INTO users (username, email, password_hash, full_name, role) VALUES
('admin',    'admin@forest.gov',    '$2y$10$G6M9pPFeLG8K0YV2FoG1eOUJX/BBS0uh2sRXvgvbtKVhS70EVxhaW', 'System Administrator', 'admin'),
('officer1', 'officer@forest.gov',  '$2y$10$G6M9pPFeLG8K0YV2FoG1eOUJX/BBS0uh2sRXvgvbtKVhS70EVxhaW', 'Ranger John Smith',    'officer'),
('public1',  'public@example.com',  '$2y$10$G6M9pPFeLG8K0YV2FoG1eOUJX/BBS0uh2sRXvgvbtKVhS70EVxhaW', 'Jane Citizen',         'public');

INSERT INTO forests (forest_code, forest_name, location, latitude, longitude, area_sq_km, ecosystem_type, region, description, established_year, created_by) VALUES
('FR-0001', 'Greenwood National Reserve', 'Northern Highlands, Sector 7', 45.5231, -122.6765, 1245.50, 'Temperate Rainforest', 'Northern Region',
 'Protected reserve with diverse wildlife and old-growth trees. Managed under national conservation policy.', 1985, 1),
('FR-0002', 'Pine Ridge Conservation Area', 'Eastern Valley', 40.7128, -74.0060, 892.30, 'Mixed Coniferous', 'Eastern Region',
 'Important corridor for migratory species. Active fire-watch stations installed.', 1992, 1);

INSERT INTO animals (qr_code, forest_id, species_name, common_name, population_estimate, conservation_status, health_status, latitude, longitude, notes, added_by) VALUES
('FMS-ANM-00001', 1, 'Ursus americanus', 'American Black Bear', 340, 'Least Concern', 'healthy', 45.5300, -122.6800, 'Stable population observed in northern sector.', 2),
('FMS-ANM-00002', 1, 'Cervus canadensis', 'Elk', 1200, 'Least Concern', 'healthy', 45.5200, -122.6700, 'Migratory herds tracked seasonally.', 2),
('FMS-ANM-00003', 2, 'Panthera onca', 'Jaguar', 45, 'Near Threatened', 'healthy', 40.7150, -74.0100, 'Monitoring program active since 2018.', 2);

INSERT INTO incidents (forest_id, category, severity, description, incident_date, reported_by, approved_by, status, workflow_status, source, latitude, longitude) VALUES
(1, 'Wildfire', 'high', 'Small brush fire contained within 2 hours. No wildlife casualties.', '2025-03-15', 2, 1, 'approved', 'resolved', 'officer', 45.5250, -122.6780),
(1, 'Poaching', 'critical', 'Suspicious activity reported near eastern boundary. Patrol dispatched.', '2025-04-02', 2, 1, 'approved', 'under_review', 'officer', 45.5280, -122.6750),
(2, 'Wildfire', 'medium', 'Controlled burn oversight - routine maintenance.', '2025-02-20', 2, 1, 'approved', 'resolved', 'officer', 40.7140, -74.0050),
(1, 'Illegal Logging', 'medium', 'Felled trees discovered on trail 4. Investigation ongoing.', '2025-05-01', 2, 1, 'approved', 'verified', 'officer', 45.5220, -122.6720),
(2, 'Wildlife Conflict', 'low', 'Bear approached campsite. Visitors evacuated safely.', '2025-05-10', 2, 1, 'approved', 'closed', 'officer', 40.7130, -74.0080);
