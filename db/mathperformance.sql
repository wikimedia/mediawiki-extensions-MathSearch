CREATE TABLE `mathperformance` (
  `math_inputhash` VARBINARY(32) NOT NULL,
  `mathperformance_name` CHAR(10) NOT NULL,
  `mathperformance_time` DOUBLE NOT NULL,
  `mathperformance_mode` TINYINT NOT NULL,
  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `PK` (`math_inputhash` ASC, `mathperformance_name` ASC));
