CREATE TABLE math_wmc_results (
    resultId INT PRIMARY KEY NOT NULL,
    qId INT NOT NULL,
    rank INT NOT NULL,
    runId INT NOT NULL,
    oldId BIGINT(20) UNSIGNED NOT NULL,
    fId INT NOT NULL,
    UNIQUE KEY uniqueRanks( runId, qId, rank ),
    KEY runId_idx( runId ),
    KEY qId_idx( qId ),
    KEY qId_oldId_idx ( qId, oldId ),
    FOREIGN KEY ( runId ) REFERENCES math_wmc_runs ( runId ) ON DELETE CASCADE,
    FOREIGN KEY ( oldId ) REFERENCES revision ( rev_id )
);
