CREATE TEMPORARY TABLE math_wmc_formula_counts
( unique key qId( qId ) )
    SELECT qId, count(*) matches from math_wmc_assessed_formula A join
      mathindex I on A.inputhash = I.mathindex_inputhash
      -- TODO: Add relevance filter e.g. WHERE assessment > 1
    group by qId;