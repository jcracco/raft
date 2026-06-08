-- RAFT — Range And Forecasting Tool
-- Database Schema v1
-- MariaDB / MySQL

SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `raft_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `raft_db`;

-- -----------------------------------------------------
-- Table: users
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(100)  NOT NULL,
  `password`   VARCHAR(255)  NOT NULL,  -- bcrypt hash
  `is_admin`   TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table: projects
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `projects` (
  `id`                      INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `url_token`               VARCHAR(6) NOT NULL,
  `user_id`                 INT UNSIGNED      NOT NULL,

  -- Core
  `project_name`            VARCHAR(255)      NOT NULL,
  `initiative_name`         VARCHAR(255)      NULL DEFAULT NULL,
  `initiative_link`         VARCHAR(500)      NULL DEFAULT NULL,
  `team_name`               VARCHAR(255)      NULL DEFAULT NULL,
  `team_link`               VARCHAR(500)      NULL DEFAULT NULL,

  -- Points
  `pointed_sp`              INT UNSIGNED      NOT NULL,
  `estimated_additional_sp` INT UNSIGNED      NULL DEFAULT NULL,
  `buffer_pct`              DECIMAL(5,2)      NOT NULL DEFAULT 25.00,

  -- Velocity & focus
  `avg_velocity`            DECIMAL(8,2)      NOT NULL,
  `velocity_auto`           TINYINT(1)        NOT NULL DEFAULT 0,
  `current_weather_pct`     DECIMAL(5,2)      NOT NULL DEFAULT 50.00,
  `weather_auto`            TINYINT(1)        NOT NULL DEFAULT 0,

  -- Sprint naming
  `sprint_prefix`           VARCHAR(100)      NOT NULL DEFAULT 'Sprint',
  `use_year`                TINYINT(1)        NOT NULL DEFAULT 0,
  `year_format`             ENUM('yy','yyyy') NULL DEFAULT NULL,
  `number_format`           ENUM('x','xx')    NOT NULL DEFAULT 'xx',

  -- Sprint cadence
  `sprint_duration_weeks`   TINYINT UNSIGNED  NULL DEFAULT NULL,
  `cadence_start_date`      DATE              NULL DEFAULT NULL,

  -- Initiative start
  `initiative_start_year`   SMALLINT UNSIGNED NULL DEFAULT NULL,
  `initiative_start_number` SMALLINT UNSIGNED NOT NULL,

  `created_at`              DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_url_token` (`url_token`),
  CONSTRAINT `fk_projects_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table: sprint_entries
-- Completed sprints only. focus_pct and points_remaining
-- are calculated on load, not stored.
-- Sort: ORDER BY sprint_year ASC, sprint_number ASC
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sprint_entries` (
  `id`                   INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `project_id`           INT UNSIGNED      NOT NULL,
  `sprint_number`        SMALLINT UNSIGNED NOT NULL,
  `sprint_year`          SMALLINT UNSIGNED NULL DEFAULT NULL,
  `total_sprint_sp`      INT UNSIGNED      NOT NULL,
  `initiative_work_done` INT UNSIGNED      NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sprint_per_project` (`project_id`, `sprint_year`, `sprint_number`),
  CONSTRAINT `fk_entries_project`
    FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
