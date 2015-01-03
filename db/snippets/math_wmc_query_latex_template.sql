CREATE TABLE IF NOT EXISTS math_wmc_query_template_latex
    SELECT
      qId,
      oldID,
      fId,
      qVarCount,
      REPLACE(page_title, '_', ' ')       title,
      count(DISTINCT A.mathindex_revision_id) matches,
      concat('{$',
             REPLACE( CONVERT( math_inputtex USING utf8), '\n', ' ' ),
             '$}') AS rendering
    FROM
      math_wmc_ref
      JOIN
      mathindex I ON ((oldId = I.mathindex_revision_id)
                      AND (fId = I.mathindex_anchor))
      JOIN
      mathindex A ON I.mathindex_inputhash = A.mathindex_inputhash
      JOIN
      mathlatexml L ON L.math_inputhash = A.mathindex_inputhash
      JOIN
      revision R ON R.rev_id = oldId
      JOIN
      `page` P ON R.rev_page = P.page_id
    GROUP BY qId;