CREATE TEMPORARY TABLE IF NOT EXISTS math_wmc_results_pages (
  UNIQUE KEY uniqueRanks( runId, qId, rank ) )
(  SELECT
    @r := IF( @g = CONCAT( runId, qId ), @r + 1, 1 ) AS rank,
    oldRank,
    runId,
    resultId,
    qId,
    oldId,
    @g := CONCAT( runId, qId )
  FROM (
         SELECT
           resultId,
           runId,
           qId,
           oldId,
           MIN( rank ) AS oldRank
         FROM
           math_wmc_results
         GROUP BY
           runId,
           qId,
           oldId
       ) AS MinFormulaRank
    JOIN ( SELECT @r := 0, @g := 0 ) dummy
  ORDER BY
    runId,
    qId,
    oldRank ASC
);