CREATE TEMPORARY TABLE IF NOT EXISTS math_wmc_runstat
    SELECT
      rank,
      user_name,
      r.runId
    FROM
      math_wmc_page_ranks r
      JOIN math_wmc_runs runs ON
                                r.runId = runs.runId
                                AND r.runID IN (SELECT *
                                                FROM math_wmc_results_top)
      JOIN user ON runs.userId = user.user_id
    GROUP BY r.qId, r.runId
    ORDER BY user_id, runId