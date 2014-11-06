CREATE TABLE math_wmc_results
(
    resultId INT PRIMARY KEY NOT NULL,
    qId INT NOT NULL,
    rank INT NOT NULL,
    runId INT NOT NULL,
    oldId INT NOT NULL,
    fId INT NOT NULL,
    FOREIGN KEY ( runId ) REFERENCES math_wmc_runs ( runId ),
    FOREIGN KEY ( oldId ) REFERENCES revision ( rev_id )
);
CREATE INDEX idx_wmc_results_qid ON math_wmc_results ( qId );
CREATE INDEX idx_wmc_results_run ON math_wmc_results ( runId );
CREATE INDEX idx_wmc_results_qid_curid ON math_wmc_results ( qId, oldId );
