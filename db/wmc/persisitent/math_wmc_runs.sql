CREATE TABLE math_wmc_runs
(
    runId INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
    runName VARCHAR(45),
    userId INT UNSIGNED,
    isDraft TINYINT NOT NULL,
    FOREIGN KEY ( userId ) REFERENCES user ( user_id )
);