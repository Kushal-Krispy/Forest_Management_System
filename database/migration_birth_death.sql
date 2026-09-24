-- Birth and death event tracking for wildlife population reports
USE forest_management;

CREATE TABLE IF NOT EXISTS animal_birth_death_events (
  event_id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL,
  forest_id INT NOT NULL,
  event_type ENUM('birth','death') NOT NULL,
  event_count INT NOT NULL DEFAULT 1 CHECK (event_count >= 1),
  cause_of_death VARCHAR(255) NULL,
  recorded_by INT NULL,
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bde_animal FOREIGN KEY (animal_id) REFERENCES animals(animal_id) ON DELETE CASCADE,
  CONSTRAINT fk_bde_forest FOREIGN KEY (forest_id) REFERENCES forests(forest_id) ON DELETE CASCADE,
  CONSTRAINT fk_bde_user FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_bde_forest (forest_id),
  INDEX idx_bde_animal (animal_id),
  INDEX idx_bde_date (recorded_at)
) ENGINE=InnoDB;
