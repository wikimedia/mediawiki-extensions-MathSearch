CREATE TEMPORARY TABLE IF NOT EXISTS math_wmc_query_summary
SELECT
  R.qId,
  fId,
  math_wmc_WikiLink(oldId, fId) AS  L,
  oldId,
  qVarCount,
  texQuery,
  math_wmc_TeXCol(l.math_inputtex)  rendering,
  count(Icount.mathindex_inputhash) exactMatches,
  math_wmc_pageTitleFromRevId(oldId) title,
  avg,
  min,
  max,
  count
FROM math_wmc_ref R
  JOIN mathindex I ON oldId = mathindex_revision_id AND fId = mathindex_anchor
  JOIN mathlatexml l ON I.mathindex_inputhash = l.math_inputhash
  JOIN mathindex Icount ON I.mathindex_inputhash = Icount.mathindex_inputhash
  JOIN math_wmc_queries_top_dist t on R.qId = t.qId
GROUP BY R.qId;