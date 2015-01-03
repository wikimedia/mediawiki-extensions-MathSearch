CREATE TABLE IF NOT EXISTS math_wmc_results_formulae (
  UNIQUE KEY uniqueRanks( runId, qId, rank ) )
    (
      SELECT
        ref.qId, runId, min(rank) rank
      from
        math_wmc_assessed_formula ref
        join
        math_wmc_results res ON ref.math_inputhash = math_inputhash
                                and ref.qId = res.qId
      group by runId, ref.qId
    )