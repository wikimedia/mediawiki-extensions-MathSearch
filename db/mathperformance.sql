CREATE TABLE `wiki`.`mathperformance` (
  `math_inputhash` VARBINARY(16) NOT NULL,
  `mathperformance_name` CHAR(10) NOT NULL,
  `mathperformance_time` DOUBLE NOT NULL,
  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `PK` (`math_inputhash` ASC, `mathperformance_name` ASC));