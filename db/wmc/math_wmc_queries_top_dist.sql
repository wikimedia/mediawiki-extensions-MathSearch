CREATE TEMPORARY TABLE IF NOT EXISTS math_wmc_queries_top_dist
    SELECT
      W.qId,
      round(avg(rank), 2) 'avg',
      min(rank)           min,
      max(rank)           max,
      count(rank)         count
    FROM
      math_wmc_ref `r`
        JOIN math_wmc_results_pages `W` ON (`W`.oldId = r.oldId
                                            AND `W`.`qId` = `r`.`qId`)
    WHERE
      W.runId IN (SELECT * FROM math_wmc_results_top)
    GROUP BY
      W.qId
    ORDER BY avg(rank), min(rank), max(rank), qId