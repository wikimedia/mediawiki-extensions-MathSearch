CREATE TEMPORARY TABLE math_wmc_topics_easy
( unique key qId( qId ) )
    SELECT
      qId
    from
      math_wmc_ref natural join math_wmc_formula_counts
    WHERE
      qVarCount = 0 and matches = 1