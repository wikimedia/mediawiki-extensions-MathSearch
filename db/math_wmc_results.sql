CREATE TABLE math_wmc_results
(
    resultId INT PRIMARY KEY NOT NULL,
    qId INT,
    curId INT,
    rank INT NOT NULL,
    runId INT,
    oldId INT,
    FOREIGN KEY ( runId ) REFERENCES math_wmc_runs ( runId ),
    FOREIGN KEY ( oldId ) REFERENCES revision ( rev_id )
);
CREATE INDEX idx_wmc_results_qid ON math_wmc_results ( qId );
CREATE INDEX idx_wmc_results_qid_curid ON math_wmc_results ( qId, curId );
