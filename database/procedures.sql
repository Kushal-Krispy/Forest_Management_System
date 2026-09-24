-- =============================================================================
-- STORED PROCEDURES ONLY (repair / phpMyAdmin)
-- =============================================================================
-- Use when tables exist but procedures failed to install:
--   phpMyAdmin → Import → choose this file, OR
--   mysql -u root forest_management < database/procedures.sql
-- Requires: forest_management database and incidents table already exist.
-- =============================================================================

USE forest_management;

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_incident_category_report$$
DROP PROCEDURE IF EXISTS sp_approve_pending_incident$$

CREATE PROCEDURE sp_incident_category_report()
BEGIN
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
